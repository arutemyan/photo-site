<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * File validation utilities: data URI image validation, binary timelapse validation, extension checks.
 */
class FileValidator
{
    public const IMAGE_MAX_BYTES = 10485760; // 10MB
    public const TIMELAPSE_MAX_BYTES = 52428800; // 50MB

    /**
     * Validate a data URI for image and return decoded binary and mime type.
     * Throws InvalidArgumentException on failure.
     * @return array [mime, binary]
     */
    public static function validateDataUriImage(string $dataUri): array
    {
        if (!preg_match('#^data:(.*?);base64,(.*)$#', $dataUri, $m)) {
            throw new \InvalidArgumentException('Invalid data URI');
        }
        $mime = $m[1];
        $b64 = $m[2];
        $bin = base64_decode($b64, true);
        if ($bin === false) {
            throw new \InvalidArgumentException('Invalid base64 image data');
        }

        if (strlen($bin) > self::IMAGE_MAX_BYTES) {
            throw new \InvalidArgumentException('Image exceeds maximum allowed size');
        }

        // use finfo when available
        if (extension_loaded('fileinfo')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $det = finfo_buffer($f, $bin);
            finfo_close($f);
            if ($det === false) {
                throw new \InvalidArgumentException('Unable to detect image mime type');
            }
            // accept png/jpg/webp
            if (!in_array($det, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                throw new \InvalidArgumentException('Unsupported image mime: ' . $det);
            }
            return [$det, $bin];
        }

        // fallback: check headers for PNG/JPEG/WEBP
        if (substr($bin, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return ['image/png', $bin];
        }
        if (substr($bin, 0, 2) === "\xff\xd8") {
            return ['image/jpeg', $bin];
        }
        if (substr($bin, 0, 4) === 'RIFF' && substr($bin, 8, 4) === 'WEBP') {
            return ['image/webp', $bin];
        }

        throw new \InvalidArgumentException('Unknown or unsupported image format');
    }

    /**
    * Validate timelapse binary (expected gzipped payload).
    * Supported inner payloads: gzipped JSON or gzipped CSV (headered CSV text).
     * Returns true if the basic checks pass.
     */
    public static function validateTimelapseBinary(string $data): bool
    {
        if (strlen($data) > self::TIMELAPSE_MAX_BYTES) {
            throw new \InvalidArgumentException('Timelapse exceeds maximum allowed size');
        }

        // check gzip header (0x1f 0x8b)
        if (substr($data, 0, 2) !== "\x1f\x8b") {
            throw new \InvalidArgumentException('Timelapse is not gzipped');
        }

        return true;
    }

    /**
     * Basic file name safety check: rejects dangerous characters and double extensions
     */
    public static function isSafeFilename(string $filename): bool
    {
        // reject path traversal
        if (strpos($filename, '..') !== false) {
            return false;
        }
        // allowed characters
        if (preg_match('#^[a-zA-Z0-9_\-\.]+$#', $filename) !== 1) {
            return false;
        }
        // double extension check: allow at most one dot
        if (substr_count($filename, '.') > 1) {
            return false;
        }
        return true;
    }
}
