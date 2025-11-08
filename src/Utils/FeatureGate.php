<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * FeatureGate
 *
 * 小さなユーティリティとして機能フラグの確認を集約します。
 * 設定は config/config.php を通して読み込みます。
 */
class FeatureGate
{
    /**
     * 指定した機能が有効かどうかを返す
     *
     * @param string $feature paint, admin など
     * @return bool
     */
    public static function isEnabled(string $feature): bool
    {
        $configPath = __DIR__ . '/../../config/config.php';
        if (!file_exists($configPath)) {
            return true;
        }

        $cfg = require $configPath;
        if (!is_array($cfg)) {
            return true;
        }

        return isset($cfg[$feature]) ? (bool)($cfg[$feature]['enabled'] ?? true) : true;
    }

    /**
     * 機能が無効な場合は 404 を返して終了するユーティリティ
     *
     * @param string $feature
     * @return void
     */
    public static function ensureEnabled(string $feature): void
    {
        if (!self::isEnabled($feature)) {
            if (!headers_sent()) {
                header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            }
            echo '404 Not Found';
            exit;
        }
    }
}
