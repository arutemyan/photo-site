<?php
declare(strict_types=1);

namespace App\Models;

class IllustFile
{
    /**
     * Validate and normalize .illust JSON content.
     * Returns decoded array on success or throws \InvalidArgumentException on failure.
     */
    public static function validate(string $json): array
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON for .illust file');
        }

        if (empty($data['metadata']) || empty($data['layers']) || !is_array($data['layers'])) {
            throw new \InvalidArgumentException('Missing required fields in .illust file');
        }

        // basic constraints
        $meta = $data['metadata'];
        if (empty($meta['canvas_width']) || empty($meta['canvas_height'])) {
            throw new \InvalidArgumentException('Canvas dimensions are required');
        }

        // layer count constraint: 4 layers expected
        if (count($data['layers']) > 8) {
            // allow some flexibility but prevent huge payloads
            throw new \InvalidArgumentException('Too many layers');
        }

        return $data;
    }

    public static function toJson(array $data, bool $pretty = false): string
    {
        return $pretty ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : json_encode($data);
    }
}
