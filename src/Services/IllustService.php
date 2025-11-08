<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\IllustFile;
use App\Utils\EnvChecks;
use App\Utils\Logger;
use PDO;

class IllustService
{
    private PDO $db;
    private string $uploadsDir;

    public function __construct(PDO $db, string $uploadsDir)
    {
        $this->db = $db;
        $this->uploadsDir = rtrim($uploadsDir, '/');
    }

    /**
     * Save illust data: .illust file, image, timelapse and DB metadata.
     * $payload keys: user_id, title, canvas_width, canvas_height, background_color,
     *  illust_json (string), image_data (data URI), timelapse_data (binary gz)
     */
    public function save(array $payload): array
    {
        // validate .illust
        $illust = IllustFile::validate($payload['illust_json']);

        $userId = (int)$payload['user_id'];

        // Determine if this is an update (id provided) or create
        $isUpdate = !empty($payload['id']);
        $id = $isUpdate ? (int)$payload['id'] : null;

        // Begin transaction to ensure DB consistency with file writes
        $this->db->beginTransaction();
        $createdFiles = [];
        $backups = [];
        try {
            if ($isUpdate) {
                // fetch existing record
                $stmt = $this->db->prepare('SELECT * FROM paint WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new \RuntimeException('Paint not found for update');
                }
                if ((int)$row['user_id'] !== $userId) {
                    throw new \RuntimeException('Permission denied');
                }
            } else {
                // generate id by inserting DB record placeholder
                $stmt = $this->db->prepare('INSERT INTO paint (user_id, title) VALUES (:user_id, :title)');
                $stmt->execute([':user_id' => $userId, ':title' => $payload['title'] ?? '']);
                $id = (int)$this->db->lastInsertId();
            }

            // paths (now uploads is under public/)
            $sub = sprintf('%03d', $id % 1000);
            $basePath = $this->uploadsDir . '/paintfiles';
            $imagesDir = $basePath . '/images/' . $sub;
            $dataDir = $basePath . '/data/' . $sub;
            $timelapseDir = $basePath . '/timelapse/' . $sub;
            @mkdir($imagesDir, 0755, true);
            @mkdir($dataDir, 0755, true);
            @mkdir($timelapseDir, 0755, true);

            $dataPath = $dataDir . '/illust_' . $id . '.illust';
            // Save master image as high-quality JPEG and thumbnail as WebP
            $imagePath = $imagesDir . '/illust_' . $id . '.jpg';
            $thumbPath = $imagesDir . '/illust_' . $id . '_thumb.webp';
            $timelapsePath = $timelapseDir . '/timelapse_' . $id . '.csv.gz';

            // if update, create backups of existing files to allow rollback
            if ($isUpdate) {
                $filesToCheck = [$dataPath, $imagePath, $thumbPath, $timelapsePath];
                foreach ($filesToCheck as $fp) {
                    if (file_exists($fp)) {
                        $bak = $fp . '.bak';
                        if (@copy($fp, $bak)) {
                            $backups[] = [$fp, $bak];
                        }
                    }
                }
            }

            // save .illust (write to tmp then rename)
            $tmpData = $dataPath . '.tmp';
            if (file_put_contents($tmpData, $payload['illust_json']) === false) {
                throw new \RuntimeException('Failed to write .illust file');
            }
            if (!@rename($tmpData, $dataPath)) {
                throw new \RuntimeException('Failed to move .illust file into place');
            }
            $createdFiles[] = $dataPath;

            // save image (data URI expected). Convert to JPEG (quality 95) and generate WebP thumbnail.
            $thumbGenerated = false;
            if (!empty($payload['image_data'])) {
                [$mime, $bin] = \App\Utils\FileValidator::validateDataUriImage($payload['image_data']);

                // write original upload to a tmp file for potential debugging
                $tmpRaw = $imagePath . '.raw.tmp';
                if (file_put_contents($tmpRaw, $bin) === false) {
                    throw new \RuntimeException('Failed to write temporary image file');
                }

                $imageCreated = false;
                // Try Imagick to produce high quality JPEG and WebP thumbnail
                if (extension_loaded('imagick')) {
                    try {
                        $im = new \Imagick();
                        $im->readImageBlob($bin);
                        $im->setImageFormat('jpeg');
                        $im->setImageCompressionQuality(95);
                        // write main image
                        $tmpJpeg = $imagePath . '.tmp';
                        $im->writeImage($tmpJpeg);
                        if (@rename($tmpJpeg, $imagePath)) {
                            $imageCreated = true;
                            $createdFiles[] = $imagePath;
                        }

                        // generate thumbnail as webp
                        try {
                            $thumb = clone $im;
                            $thumb->thumbnailImage(320, 0);
                            $thumb->setImageFormat('webp');
                            $thumb->setImageCompressionQuality(80);
                            $tmpThumb = $thumbPath . '.tmp';
                            $thumb->writeImage($tmpThumb);
                            if (@rename($tmpThumb, $thumbPath)) {
                                $thumbGenerated = true;
                                $createdFiles[] = $thumbPath;
                            }
                            $thumb->clear();
                            $thumb->destroy();
                        } catch (\Throwable $e) {
                            Logger::getInstance()->error('IllustService: imagick thumbnail failed: ' . $e->getMessage());
                        }

                        $im->clear();
                        $im->destroy();
                    } catch (\Throwable $e) {
                        // fall through to GD
                        Logger::getInstance()->error('IllustService: imagick conversion failed: ' . $e->getMessage());
                    }
                }

                // GD fallback: create JPEG and WebP thumbnail if possible
                if (!$imageCreated && extension_loaded('gd') && function_exists('imagecreatefromstring')) {
                    $gd = @imagecreatefromstring($bin);
                    if ($gd !== false) {
                        $tmpJpeg = $imagePath . '.tmp';
                        // write high-quality JPEG
                        if (function_exists('imagejpeg')) {
                            @imagejpeg($gd, $tmpJpeg, 95);
                        } else {
                            // fallback to PNG if jpeg function missing
                            @imagepng($gd, $tmpJpeg);
                        }
                        if (@rename($tmpJpeg, $imagePath)) {
                            $imageCreated = true;
                            $createdFiles[] = $imagePath;
                        }

                        // create thumbnail
                        $thumbIm = imagescale($gd, 320, -1);
                        if ($thumbIm !== false) {
                            $tmpThumb = $thumbPath . '.tmp';
                            if (function_exists('imagewebp')) {
                                @imagewebp($thumbIm, $tmpThumb, 80);
                            } else {
                                @imagepng($thumbIm, $tmpThumb);
                            }
                            if (@rename($tmpThumb, $thumbPath)) {
                                $thumbGenerated = true;
                                $createdFiles[] = $thumbPath;
                            }
                            imagedestroy($thumbIm);
                        }
                        imagedestroy($gd);
                    } else {
                        Logger::getInstance()->error('IllustService: GD failed to read uploaded image blob');
                    }
                }

                // cleanup raw tmp
                if (file_exists($tmpRaw)) @unlink($tmpRaw);

                if (!$imageCreated) {
                    throw new \RuntimeException('Failed to create master image from uploaded data');
                }
                if (!$thumbGenerated) {
                    Logger::getInstance()->error(sprintf('IllustService: thumbnail not generated for paint id=%d src=%s dst=%s', $id, $imagePath, $thumbPath));
                }
            } elseif (!$isUpdate) {
                // no image provided for new record -> leave image/timelapse empty
            }

            // save timelapse if provided
            if (!empty($payload['timelapse_data'])) {
                // incoming payload is expected to be gzipped binary (could be JSON, MessagePack or CSV)
                \App\Utils\FileValidator::validateTimelapseBinary($payload['timelapse_data']);

                // Attempt to decode incoming payload into PHP array of events.
                $incomingEvents = null;
                $rawDecoded = @gzdecode($payload['timelapse_data']);
                if ($rawDecoded === false) {
                    // if gzdecode fails, fall back to raw bytes (unlikely since validator checks gzip header)
                    $rawDecoded = $payload['timelapse_data'];
                }

                // Try JSON first (backwards compatibility)
                $maybe = @json_decode($rawDecoded, true);
                if (is_array($maybe)) {
                    $incomingEvents = $maybe;
                }

                // If still null, try CSV detection / parsing (headered CSV)
                if ($incomingEvents === null) {
                    // Ensure we have a string for CSV parsing
                    if (is_string($rawDecoded) || (is_scalar($rawDecoded) && !is_array($rawDecoded))) {
                        $rawStr = (string)$rawDecoded;
                        if (strpos($rawStr, "\n") !== false) {
                            $lines = preg_split("/\r\n|\n|\r/", trim($rawStr));
                            if ($lines && count($lines) > 0) {
                                $header = str_getcsv(array_shift($lines));
                                if ($header && count($header) > 0) {
                                    $events = [];
                                    foreach ($lines as $ln) {
                                        if (trim($ln) === '') continue;
                                        $vals = str_getcsv($ln);
                                        // if columns mismatch, skip line
                                        if (count($vals) !== count($header)) continue;
                                        $events[] = array_combine($header, $vals);
                                    }
                                    if (count($events) > 0) {
                                        $incomingEvents = $events;
                                    }
                                }
                            }
                        }
                    }
                }

                if (!is_array($incomingEvents)) {
                    throw new \RuntimeException('Invalid timelapse payload (unsupported format)');
                }

                // If an existing timelapse CSV.gz exists, read and parse it (CSV or JSON fallback)
                $mergedEvents = $incomingEvents;
                if (file_exists($timelapsePath)) {
                    $existing = @file_get_contents($timelapsePath);
                    if ($existing !== false) {
                        $existingDecoded = @gzdecode($existing);
                        if ($existingDecoded === false) {
                            $existingDecoded = $existing;
                        }
                        // try JSON first
                        $exEvents = @json_decode($existingDecoded, true);
                        if (!is_array($exEvents)) {
                            // try CSV parse
                            $lines = preg_split("/\r\n|\n|\r/", trim($existingDecoded));
                            if ($lines && count($lines) > 0) {
                                $hdr = str_getcsv(array_shift($lines));
                                $parsed = [];
                                foreach ($lines as $ln) {
                                    if (trim($ln) === '') continue;
                                    $vals = str_getcsv($ln);
                                    if (count($vals) !== count($hdr)) continue;
                                    $parsed[] = array_combine($hdr, $vals);
                                }
                                if (count($parsed) > 0) $exEvents = $parsed;
                            }
                        }
                        if (is_array($exEvents)) {
                            $mergedEvents = array_merge($exEvents, $incomingEvents);
                            // dedupe
                            $seen = [];
                            $unique = [];
                            foreach ($mergedEvents as $ev) {
                                $sig = md5(json_encode($ev));
                                if (isset($seen[$sig])) continue;
                                $seen[$sig] = true;
                                $unique[] = $ev;
                            }
                            $mergedEvents = $unique;
                        }
                    }
                }

                // Convert merged events to CSV text (header is union of keys in order seen)
                $headers = [];
                foreach ($mergedEvents as $ev) {
                    foreach ($ev as $k => $_) {
                        if (!in_array($k, $headers, true)) $headers[] = $k;
                    }
                }
                $csvLines = [];
                $csvLines[] = implode(',', $headers);
                foreach ($mergedEvents as $ev) {
                    $row = [];
                    foreach ($headers as $h) {
                        $v = $ev[$h] ?? '';
                        if (is_array($v)) {
                            // encode arrays as JSON string to preserve
                            $v = json_encode($v);
                        }
                        $s = (string)$v;
                        if (strpos($s, ',') !== false || strpos($s, '"') !== false || strpos($s, "\n") !== false) {
                            $s = '"' . str_replace('"', '""', $s) . '"';
                        }
                        $row[] = $s;
                    }
                    $csvLines[] = implode(',', $row);
                }
                $csvText = implode("\n", $csvLines);

                $gz = gzencode($csvText);
                if ($gz === false) {
                    throw new \RuntimeException('Failed to gzip merged timelapse');
                }

                $tmpTL = $timelapsePath . '.tmp';
                if (file_put_contents($tmpTL, $gz) === false) {
                    throw new \RuntimeException('Failed to write timelapse file');
                }
                if (!@rename($tmpTL, $timelapsePath)) {
                    throw new \RuntimeException('Failed to move timelapse into place');
                }
                $createdFiles[] = $timelapsePath;
            }

            // update DB row with paths and sizes
            $update = $this->db->prepare('UPDATE paint SET title = :title, description = :description, tags = :tags, data_path = :data_path, image_path = :image_path, thumbnail_path = :thumbnail_path, timelapse_path = :timelapse_path, file_size = :file_size WHERE id = :id');
            $update->execute([
                ':title' => $payload['title'] ?? '',
                ':description' => $payload['description'] ?? '',
                ':tags' => $payload['tags'] ?? '',
                ':data_path' => $this->toPublicPath($dataPath),
                ':image_path' => file_exists($imagePath) ? $this->toPublicPath($imagePath) : null,
                ':thumbnail_path' => (file_exists($thumbPath) ? $this->toPublicPath($thumbPath) : null),
                ':timelapse_path' => file_exists($timelapsePath) ? $this->toPublicPath($timelapsePath) : null,
                ':file_size' => filesize($dataPath) ?: 0,
                ':id' => $id,
            ]);

            $this->db->commit();

            // cleanup backups on success
            foreach ($backups as [$orig, $bak]) {
                if (file_exists($bak)) {
                    @unlink($bak);
                }
            }

            return [
                'id' => $id,
                'data_path' => $this->toPublicPath($dataPath),
                'image_path' => file_exists($imagePath) ? $this->toPublicPath($imagePath) : null,
                'thumbnail_path' => (file_exists($thumbPath) ? $this->toPublicPath($thumbPath) : null),
                'timelapse_path' => file_exists($timelapsePath) ? $this->toPublicPath($timelapsePath) : null,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            // cleanup created files
            foreach ($createdFiles as $f) {
                if (file_exists($f)) {
                    @unlink($f);
                }
            }
            // attempt to restore backups
            foreach ($backups as [$orig, $bak]) {
                if (file_exists($bak)) {
                    @copy($bak, $orig);
                    @unlink($bak);
                }
            }
            throw $e;
        }
    }

    private function saveDataUriToFile(string $dataUri, string $path): void
    {
        if (preg_match('#^data:(.*?);base64,(.*)$#', $dataUri, $m)) {
            $b64 = $m[2];
            file_put_contents($path, base64_decode($b64));
        } else {
            throw new \InvalidArgumentException('Invalid data URI');
        }
    }

    private function toPublicPath(string $absPath): string
    {
        // make path relative to project public/ if possible
        $cwd = getcwd();
        if (strpos($absPath, $cwd) === 0) {
            $rel = substr($absPath, strlen($cwd));
        } else {
            $rel = $absPath;
        }

        // normalize path to collapse any ../ or ./ segments
        $normalized = $this->normalizePath($rel);

        // ensure leading slash for web paths
        if ($normalized === '' || $normalized[0] !== '/') {
            $normalized = '/' . ltrim($normalized, '/');
        }

        return $normalized;
    }

    /**
     * Normalize a filesystem path by collapsing `.` and `..` segments.
     * Preserves a leading slash if present.
     */
    private function normalizePath(string $path): string
    {
        $parts = preg_split('#/+#', $path, -1, PREG_SPLIT_NO_EMPTY);
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                if (!empty($stack)) {
                    array_pop($stack);
                }
                // if stack empty, ignore .. to avoid escaping root
                continue;
            }
            $stack[] = $part;
        }
        return '/' . implode('/', $stack);
    }

    private function generateThumbnailWebp(string $srcPath, string $dstPath): bool
    {
        // Try Imagick first. If destination extension is webp and Imagick supports it, write webp.
        $dstExt = strtolower(pathinfo($dstPath, PATHINFO_EXTENSION));

        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick($srcPath);
                $format = ($dstExt === 'webp') ? 'webp' : 'png';
                $im->setImageFormat($format);
                $im->thumbnailImage(320, 0);
                $im->writeImage($dstPath);
                $im->clear();
                $im->destroy();
                return file_exists($dstPath);
            } catch (\Throwable $e) {
                // fall through to GD
            }
        }

        // GD fallback: attempt to create thumbnail, save as webp if imagewebp is available, otherwise PNG
        if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            $data = @file_get_contents($srcPath);
            if ($data === false) {
                return false;
            }
            $im = @imagecreatefromstring($data);
            if ($im !== false) {
                $thumb = imagescale($im, 320, -1);
                if ($dstExt === 'webp' && function_exists('imagewebp')) {
                    @imagewebp($thumb, $dstPath, 80);
                } elseif (function_exists('imagepng')) {
                    // save PNG when webp is not available or dstExt != webp
                    @imagepng($thumb, $dstPath);
                } else {
                    // no suitable output function
                    imagedestroy($thumb);
                    imagedestroy($im);
                    return false;
                }
                imagedestroy($thumb);
                imagedestroy($im);
                return file_exists($dstPath);
            }
        }

        return false;
    }
}
