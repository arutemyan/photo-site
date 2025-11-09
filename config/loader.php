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

    // 環境変数による上書きを許可している場合はここで処理する
    // 許可フラグは config.default.php または config.local.php の
    // ['database']['allow_env_override'] で制御します（デフォルト false）
    $allowEnv = false;
    if (isset($merged['database']) && is_array($merged['database'])) {
        $allowEnv = !empty($merged['database']['allow_env_override']);
    }

    if ($allowEnv) {
        // ヘルパ: 候補 env 名のうち最初に見つかった値を返す
        $firstEnv = function (array $names) {
            foreach ($names as $n) {
                $v = getenv($n);
                if ($v !== false && $v !== null && $v !== '') {
                    return $v;
                }
            }
            return null;
        };

        // Driver (TEST_DB_DRIVER, DB_DRIVER)
        $drv = $firstEnv(['TEST_DB_DRIVER', 'DB_DRIVER']);
        if ($drv !== null) {
            $merged['database']['driver'] = $drv;
        }

        // Handle sqlite DSN/path
        $driver = $merged['database']['driver'] ?? null;
        if ($driver === 'sqlite') {
            $dsn = $firstEnv(['TEST_DB_DSN', 'DB_DSN', 'TEST_DB_PATH', 'DB_PATH']);
            if ($dsn !== null) {
                // DSN 形式 (sqlite::memory: or sqlite:/path) の path 部分を取り出す
                if (preg_match('#^sqlite:(.*)$#', $dsn, $m)) {
                    $path = $m[1];
                    $merged['database']['sqlite']['gallery']['path'] = $path;
                } else {
                    // 直接パス指定とみなす
                    $merged['database']['sqlite']['gallery']['path'] = $dsn;
                }
            }
        }

        // For mysql/postgresql, map common env names
        if ($driver === 'mysql' || $driver === 'postgresql') {
            $svc = $driver === 'mysql' ? 'mysql' : 'postgresql';

            $host = $firstEnv(['TEST_DB_HOST', 'DB_HOST']);
            $port = $firstEnv(['TEST_DB_PORT', 'DB_PORT']);
            $name = $firstEnv(['TEST_DB_NAME', 'TEST_DB_DATABASE', 'DB_NAME', 'DB_DATABASE']);
            $user = $firstEnv(['TEST_DB_USER', 'DB_USER', 'TEST_DB_USERNAME', 'DB_USERNAME']);
            $pass = $firstEnv(['TEST_DB_PASS', 'DB_PASS', 'TEST_DB_PASSWORD', 'DB_PASSWORD']);

            if ($host !== null) $merged['database'][$svc]['host'] = $host;
            if ($port !== null) $merged['database'][$svc]['port'] = is_numeric($port) ? (int)$port : $port;
            if ($name !== null) $merged['database'][$svc]['database'] = $name;
            if ($user !== null) $merged['database'][$svc]['username'] = $user;
            if ($pass !== null) $merged['database'][$svc]['password'] = $pass;
        }
    }

    $cache[$cacheKey] = $merged;
    return $merged;
}
