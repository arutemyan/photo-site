<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * 環境チェックユーティリティ
 * - zlib, gd/imagick などのサポートを確認するための簡易ユーティリティ
 */
class EnvChecks
{
    public static function isZlibAvailable(): bool
    {
        return function_exists('gzcompress') && function_exists('gzuncompress');
    }

    public static function isFileinfoAvailable(): bool
    {
        return extension_loaded('fileinfo');
    }

    /**
     * WebP を生成できるか (gd または imagick)
     */
    public static function isWebpSupported(): bool
    {
        // gd があり imagewebp が使えるか
        if (extension_loaded('gd') && function_exists('imagewebp')) {
            return true;
        }

        // imagick があり WebP をサポートしているか
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                $formats = $imagick->queryFormats('WEBP');
                return !empty($formats);
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    public static function checkAll(): array
    {
        return [
            'zlib' => self::isZlibAvailable(),
            'fileinfo' => self::isFileinfoAvailable(),
            'webp' => self::isWebpSupported(),
        ];
    }
}
