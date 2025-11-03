<?php
declare(strict_types=1);

namespace App\Services;

use App\Utils\EnvChecks;

class TimelapseService
{
    private string $timelapsePath;

    public function __construct(string $timelapsePath)
    {
        $this->timelapsePath = rtrim($timelapsePath, '/');
    }

    /**
     * Save raw gzipped msgpack data to path. Caller should validate size/header.
     */
    public function save(string $idSubdir, string $filename, string $binary): string
    {
        $dir = $this->timelapsePath . '/' . $idSubdir;
        @mkdir($dir, 0755, true);
        $path = $dir . '/' . $filename;
        file_put_contents($path, $binary);
        return $path;
    }

    public function load(string $path): string
    {
        if (!file_exists($path)) {
            throw new \RuntimeException('Timelapse not found');
        }
        return file_get_contents($path);
    }

    public static function isMsgpackAvailable(): bool
    {
        return EnvChecks::isMsgpackAvailable();
    }
}
