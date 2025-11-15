<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\IllustFile;
use App\Repositories\IllustRepository;
use App\Utils\Logger;
use PDO;

class IllustService
{
    private IllustRepository $repository;
    private IllustFileManager $fileManager;
    private IllustImageProcessor $imageProcessor;
    private IllustTimelapseProcessor $timelapseProcessor;

    public function __construct(
        PDO $db,
        string $uploadsDir,
        ?IllustRepository $repository = null,
        ?IllustFileManager $fileManager = null,
        ?IllustImageProcessor $imageProcessor = null,
        ?IllustTimelapseProcessor $timelapseProcessor = null
    ) {
        // Allow dependency injection for testing, with default implementations
        $this->repository = $repository ?? new IllustRepository($db);
        $this->fileManager = $fileManager ?? new IllustFileManager($uploadsDir);
        $this->imageProcessor = $imageProcessor ?? new IllustImageProcessor();
        $this->timelapseProcessor = $timelapseProcessor ?? new IllustTimelapseProcessor();
    }

    /**
     * Save illust data: .illust file, image, timelapse and DB metadata.
     * $payload keys: user_id, title, canvas_width, canvas_height, background_color,
     *  illust_json (string), image_data (data URI), timelapse_data (binary gz)
     */
    public function save(array $payload): array
    {
        // Validate .illust file
        $illust = IllustFile::validate($payload['illust_json']);

        // Extract and sanitize parameters
        $userId = (int)$payload['user_id'];
        $nsfw = isset($payload['nsfw']) ? (int)$payload['nsfw'] : 0;
        $isVisible = isset($payload['is_visible']) ? (int)$payload['is_visible'] : 1;
        $artistName = $this->sanitizeArtistName($payload['artist_name'] ?? null);

        // Determine if update or create
        $isUpdate = !empty($payload['id']);
        $id = $isUpdate ? (int)$payload['id'] : null;

        // Begin transaction
        $this->repository->beginTransaction();
        $createdFiles = [];
        $backups = [];

        try {
            // Create or validate record
            if ($isUpdate) {
                $existingRecord = $this->repository->findById($id);
                if (!$existingRecord) {
                    throw new \RuntimeException('Paint not found for update');
                }
                if ((int)$existingRecord['user_id'] !== $userId) {
                    throw new \RuntimeException('Permission denied');
                }
            } else {
                $id = $this->repository->create($userId, $payload['title'] ?? '', $nsfw, $isVisible, $artistName);
            }

            // Generate file paths
            $paths = $this->fileManager->generatePaths($id);
            $this->fileManager->ensureDirectories($paths);

            // Create backups if updating
            if ($isUpdate) {
                $backups = $this->fileManager->createBackups([
                    $paths['dataPath'],
                    $paths['imagePath'],
                    $paths['thumbPath'],
                    $paths['timelapsePath']
                ]);
            }

            // Save .illust file
            $this->fileManager->saveIllustFile($paths['dataPath'], $payload['illust_json']);
            $createdFiles[] = $paths['dataPath'];

            // Save and process image
            $thumbGenerated = false;
            if (!empty($payload['image_data'])) {
                [$mime, $bin] = \App\Utils\FileValidator::validateDataUriImage($payload['image_data']);

                // Convert to JPEG with optional signature
                if (!$this->imageProcessor->convertToJpeg($bin, $paths['imagePath'], $artistName)) {
                    throw new \RuntimeException('Failed to create master image from uploaded data');
                }
                $createdFiles[] = $paths['imagePath'];

                // Generate thumbnail
                if ($this->imageProcessor->generateThumbnail($paths['imagePath'], $paths['thumbPath'])) {
                    $thumbGenerated = true;
                    $createdFiles[] = $paths['thumbPath'];
                } else {
                    Logger::getInstance()->error(sprintf(
                        'IllustService: thumbnail not generated for paint id=%d',
                        $id
                    ));
                }
            }

            // Process timelapse if provided
            if (!empty($payload['timelapse_data'])) {
                \App\Utils\FileValidator::validateTimelapseBinary($payload['timelapse_data']);

                $incomingData = $this->timelapseProcessor->parseTimelapse($payload['timelapse_data']);
                if ($incomingData === null) {
                    throw new \RuntimeException('Invalid timelapse payload (unsupported format)');
                }

                // Check if it's a JSON package (with events/snapshots)
                if ($this->timelapseProcessor->isJSONPackage($incomingData)) {
                    // Save as JSON package
                    $jsonPath = str_replace('.csv.gz', '.json.gz', $paths['timelapsePath']);
                    $gzData = $this->timelapseProcessor->convertToJSON($incomingData);
                    $this->fileManager->saveTimelapseFile($jsonPath, $gzData);
                    $createdFiles[] = $jsonPath;
                    $paths['timelapsePath'] = $jsonPath;
                } else {
                    // Merge with existing timelapse if present
                    $mergedEvents = $incomingData;
                    if (file_exists($paths['timelapsePath'])) {
                        $existingData = @file_get_contents($paths['timelapsePath']);
                        if ($existingData !== false) {
                            $existingEvents = $this->timelapseProcessor->parseTimelapse($existingData);
                            if (is_array($existingEvents)) {
                                $mergedEvents = $this->timelapseProcessor->mergeTimelapse($existingEvents, $incomingData);
                            }
                        }
                    }

                    // Convert to CSV and save
                    $gzData = $this->timelapseProcessor->convertToCSV($mergedEvents);
                    $this->fileManager->saveTimelapseFile($paths['timelapsePath'], $gzData);
                    $createdFiles[] = $paths['timelapsePath'];
                }
            }

            // Update DB record with all paths and metadata
            $this->repository->update(
                $id,
                $payload['title'] ?? '',
                $payload['description'] ?? '',
                $payload['tags'] ?? '',
                $this->fileManager->toPublicPath($paths['dataPath']),
                file_exists($paths['imagePath']) ? $this->fileManager->toPublicPath($paths['imagePath']) : null,
                file_exists($paths['thumbPath']) ? $this->fileManager->toPublicPath($paths['thumbPath']) : null,
                file_exists($paths['timelapsePath']) ? $this->fileManager->toPublicPath($paths['timelapsePath']) : null,
                file_exists($paths['timelapsePath']) ? filesize($paths['timelapsePath']) : 0,
                filesize($paths['dataPath']) ?: 0,
                $nsfw,
                $isVisible,
                $artistName
            );

            // Commit transaction
            $this->repository->commit();

            // Cleanup backups on success
            $this->fileManager->cleanupBackups($backups);

            return [
                'id' => $id,
                'data_path' => $this->fileManager->toPublicPath($paths['dataPath']),
                'image_path' => file_exists($paths['imagePath']) ? $this->fileManager->toPublicPath($paths['imagePath']) : null,
                'thumbnail_path' => file_exists($paths['thumbPath']) ? $this->fileManager->toPublicPath($paths['thumbPath']) : null,
                'timelapse_path' => file_exists($paths['timelapsePath']) ? $this->fileManager->toPublicPath($paths['timelapsePath']) : null,
            ];
        } catch (\Throwable $e) {
            // Rollback transaction
            $this->repository->rollback();

            // Cleanup created files
            $this->fileManager->deleteFiles($createdFiles);

            // Restore backups
            $this->fileManager->restoreBackups($backups);

            throw $e;
        }
    }

    /**
     * Sanitize artist name (ASCII only)
     */
    private function sanitizeArtistName(?string $artistName): ?string
    {
        if ($artistName === null) {
            return null;
        }

        $artistName = trim($artistName);

        // Only allow ASCII alphanumeric, space, hyphen, underscore, dot
        if (!preg_match('/^[A-Za-z0-9\s\-_\.]*$/', $artistName)) {
            return null;
        }

        if ($artistName === '') {
            return null;
        }

        return $artistName;
    }
}
