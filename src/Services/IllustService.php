<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\IllustFile;
use App\Utils\EnvChecks;
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

        // Begin transaction to ensure DB consistency with file writes
        $this->db->beginTransaction();
        $createdFiles = [];
        try {
            // generate id by inserting DB record placeholder
            $stmt = $this->db->prepare('INSERT INTO illusts (user_id, title) VALUES (:user_id, :title)');
            $stmt->execute([':user_id' => $userId, ':title' => $payload['title'] ?? '']);
            $id = (int)$this->db->lastInsertId();

            // paths
            $sub = sprintf('%03d', $id % 1000);
            $basePath = $this->uploadsDir . '/paintfiles';
            $imagesDir = $basePath . '/images/' . $sub;
            $dataDir = $basePath . '/data/' . $sub;
            $timelapseDir = $basePath . '/timelapse/' . $sub;
            @mkdir($imagesDir, 0755, true);
            @mkdir($dataDir, 0755, true);
            @mkdir($timelapseDir, 0755, true);

            $dataPath = $dataDir . '/illust_' . $id . '.illust';
            $imagePath = $imagesDir . '/illust_' . $id . '.png';
            $thumbPath = $imagesDir . '/illust_' . $id . '_thumb.webp';
            $timelapsePath = $timelapseDir . '/timelapse_' . $id . '.msgpack.gz';

            // save .illust
            if (file_put_contents($dataPath, $payload['illust_json']) === false) {
                throw new \RuntimeException('Failed to write .illust file');
            }
            $createdFiles[] = $dataPath;

            // save image (data URI expected)
            $thumbGenerated = false;
            if (!empty($payload['image_data'])) {
                [$mime, $bin] = \App\Utils\FileValidator::validateDataUriImage($payload['image_data']);
                if (file_put_contents($imagePath, $bin) === false) {
                    throw new \RuntimeException('Failed to write image file');
                }
                $createdFiles[] = $imagePath;
                // generate thumbnail webp (may be skipped if not supported)
                $thumbGenerated = $this->generateThumbnailWebp($imagePath, $thumbPath);
                if ($thumbGenerated && file_exists($thumbPath)) {
                    $createdFiles[] = $thumbPath;
                } else {
                    // Log thumbnail generation failure for operational visibility
                    error_log(sprintf('IllustService: thumbnail not generated for illust id=%d src=%s dst=%s', $id, $imagePath, $thumbPath));
                }
            }

            // save timelapse if provided
            if (!empty($payload['timelapse_data'])) {
                // payload contains raw binary for timelapse
                \App\Utils\FileValidator::validateTimelapseBinary($payload['timelapse_data']);
                if (file_put_contents($timelapsePath, $payload['timelapse_data']) === false) {
                    throw new \RuntimeException('Failed to write timelapse file');
                }
                $createdFiles[] = $timelapsePath;
            }

            // update DB row with paths and sizes
            $update = $this->db->prepare('UPDATE illusts SET data_path = :data_path, image_path = :image_path, thumbnail_path = :thumbnail_path, timelapse_path = :timelapse_path, file_size = :file_size WHERE id = :id');
            $update->execute([
                ':data_path' => $this->toPublicPath($dataPath),
                ':image_path' => $this->toPublicPath($imagePath),
                ':thumbnail_path' => ($thumbGenerated && file_exists($thumbPath)) ? $this->toPublicPath($thumbPath) : null,
                ':timelapse_path' => $this->toPublicPath($timelapsePath),
                ':file_size' => filesize($dataPath) ?: 0,
                ':id' => $id,
            ]);

            $this->db->commit();

            return [
                'id' => $id,
                'data_path' => $this->toPublicPath($dataPath),
                'image_path' => $this->toPublicPath($imagePath),
                'thumbnail_path' => ($thumbGenerated && file_exists($thumbPath)) ? $this->toPublicPath($thumbPath) : null,
                'timelapse_path' => $this->toPublicPath($timelapsePath),
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            // cleanup created files
            foreach ($createdFiles as $f) {
                if (file_exists($f)) {
                    @unlink($f);
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
            return substr($absPath, strlen($cwd));
        }
        return $absPath;
    }

    private function generateThumbnailWebp(string $srcPath, string $dstPath): bool
    {
        if (!EnvChecks::isWebpSupported()) {
            // cannot generate
            return false;
        }

        // Prefer Imagick if available (generally more robust)
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick($srcPath);
                $im->setImageFormat('webp');
                $im->thumbnailImage(320, 0);
                $im->writeImage($dstPath);
                $im->clear();
                $im->destroy();
                return true;
            } catch (\Throwable $e) {
                // fall through to GD
            }
        }

        // GD fallback: be defensive and suppress warnings from imagecreatefromstring
        if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            $data = @file_get_contents($srcPath);
            if ($data === false) {
                return false;
            }
            $im = @imagecreatefromstring($data);
            if ($im !== false) {
                $thumb = imagescale($im, 320, -1);
                // imagewebp might be unavailable despite EnvChecks; guard
                if (function_exists('imagewebp')) {
                    @imagewebp($thumb, $dstPath, 80);
                } else {
                    // fall back to saving PNG if webp not available
                    @imagepng($thumb, $dstPath);
                }
                imagedestroy($thumb);
                imagedestroy($im);
                return file_exists($dstPath);
            }
        }

        return false;
    }
}
