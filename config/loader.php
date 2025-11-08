<?php

declare(strict_types=1);

/**
 * 設定ファイルローダー
 *
 * デフォルト設定（*.default.php）とローカル設定（*.local.php）をマージして返す
 * ローカル設定が存在しない場合はデフォルト設定のみを返す
 */

/**
 * 設定ファイルを読み込む
 *
 * @param string $configName 設定名（例: 'config', 'nsfw', 'security'）
 * @param string|null $configDir 設定ディレクトリパス（nullの場合は自動検出）
 * @return array 設定配列
 * @throws RuntimeException デフォルト設定ファイルが存在しない場合
 */
function loadConfig(string $configName, ?string $configDir = null): array
{
    // キャッシュ: 同一プロセス内で複数回呼ばれたときにファイル読み込みを避ける
    static $cache = [];
    $cacheKey = $configName . '|' . ($configDir ?? __DIR__);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // 設定ディレクトリのパスを決定
    if ($configDir === null) {
        $configDir = __DIR__;
    }

    // デフォルト設定ファイルのパス
    $defaultPath = $configDir . '/' . $configName . '.default.php';

    // ローカル設定ファイルのパス
    $localPath = $configDir . '/' . $configName . '.local.php';

    // デフォルト設定ファイルが存在しない場合はエラー
    if (!file_exists($defaultPath)) {
        throw new RuntimeException(
            "Default config file not found: {$defaultPath}\n" .
            "Please ensure the file exists or restore it from version control."
        );
    }

    // デフォルト設定を読み込み
    $defaultConfig = require $defaultPath;

    // デフォルト設定が配列でない場合はエラー
    if (!is_array($defaultConfig)) {
        throw new RuntimeException(
            "Default config file must return an array: {$defaultPath}"
        );
    }

    // ローカル設定が存在しない場合はデフォルト設定のみを返す
    if (!file_exists($localPath)) {
        return $defaultConfig;
    }

    // ローカル設定を読み込み
    $localConfig = require $localPath;

    // ローカル設定が配列でない場合はエラー
    if (!is_array($localConfig)) {
        throw new RuntimeException(
            "Local config file must return an array: {$localPath}"
        );
    }

    // ローカル設定でデフォルト設定を上書き（再帰的にマージ）
    $merged = array_replace_recursive($defaultConfig, $localConfig);
    $cache[$cacheKey] = $merged;
    return $merged;
}
