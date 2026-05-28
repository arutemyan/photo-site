<?php

declare(strict_types=1);

namespace App\Database;

use App\Utils\Logger;
use PDO;
use Exception;

/**
 * マイグレーション実行クラス
 *
 * - マイグレーション記録はgallery.dbで一元管理
 * - マイグレーションファイル内で必要に応じて他のDB接続を取得可能
 * - MigrationHelperによる冪等性サポート
 */
class MigrationRunner
{
    private PDO $db;
    private string $migrationsDir;

    /**
     * コンストラクタ
     *
     * @param PDO $db データベース接続（gallery.db）
     * @param string|null $migrationsDir マイグレーションディレクトリ
     */
    public function __construct(PDO $db, ?string $migrationsDir = null)
    {
        $this->db = $db;
        $this->migrationsDir = $migrationsDir ?? __DIR__ . '/../../migrations';
    }

    /**
     * 未実行のマイグレーションを実行
     *
     * @return array 実行結果の配列
     */
    public function run(): array
    {
        // Make the PDO connection more tolerant to transient locks during migrations
        try {
            // Increase busy timeout to 30s (30000 ms) to wait longer for locks
            $this->db->exec('PRAGMA busy_timeout = 30000');
            // Ensure WAL mode is used if possible to reduce writer/readers contention
            @ $this->db->exec('PRAGMA journal_mode = WAL');
        } catch (\Exception $e) {
            Logger::getInstance()->warning('MigrationRunner: Could not set PRAGMA options: ' . $e->getMessage());
        }

        // Ensure migrations table exists so we can track executed migrations.
        // This allows running migrations on a fresh DB where the table hasn't been created yet.
        try {
            $helper = \App\Database\DatabaseHelper::class;
            $intType = $helper::getIntegerType($this->db);
            $textType = $helper::getTextType($this->db);
            $timestampType = $helper::getTimestampType($this->db);
            $currentTimestamp = $helper::getCurrentTimestamp($this->db);

            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS migrations (\n" .
                "    version {$intType} PRIMARY KEY,\n" .
                "    name {$textType} NOT NULL,\n" .
                "    executed_at {$timestampType} DEFAULT {$currentTimestamp}\n" .
                ")"
            );
        } catch (\Exception $e) {
            Logger::getInstance()->warning('MigrationRunner: Could not ensure migrations table exists: ' . $e->getMessage());
            // proceed; getExecutedMigrations will handle missing table scenario
        }

        // 実行済みマイグレーションを取得
        $executed = $this->getExecutedMigrations();

        // マイグレーションファイルを検出
        $migrationFiles = $this->discoverMigrations();

        $results = [];

        foreach ($migrationFiles as $version => $file) {
            // 既に実行済みならスキップ
            if (in_array($version, $executed)) {
                continue;
            }

            try {
                // Log start of migration for visibility
                Logger::getInstance()->info("Starting migration {$version}: {$file}");
                echo "Starting migration {$version}: " . basename($file) . "\n";

                $migrationStart = microtime(true);
                // マイグレーション実行（トランジェントなロックに備えてリトライ）
                $maxRetries = 20; // より多くのリトライを許容
                $attempt = 0;
                $lastException = null;
                $baseDelayUs = 500000; // 0.5s

                while ($attempt < $maxRetries) {
                    try {
                        $name = $this->executeMigration($file, $version);
                        $lastException = null;
                        break;
                    } catch (\Exception $e) {
                        $lastException = $e;
                        $msg = $e->getMessage();
                        // トランジェントなロックを判定（メッセージ内の 'database is locked' または SQLite のエラーコード 6）
                        $isLocked = false;
                        if (strpos($msg, 'database is locked') !== false) {
                            $isLocked = true;
                        } else {
                            // PDOException の場合、コードが 6（SQLite internal error）になることがある
                            try {
                                $code = (string)$e->getCode();
                                if ($code === '6' || $code === 'SQLITE_BUSY' || stripos($code, 'HY000') !== false) {
                                    $isLocked = true;
                                }
                            } catch (\Throwable $t) {
                                // ignore
                            }
                        }

                        if ($isLocked) {
                            $attempt++;
                            $delay = (int)($baseDelayUs * (1 + ($attempt / 4))); // 緩やかな増加
                            Logger::getInstance()->warning("Migration {$version}: database is locked, retrying ({$attempt}/{$maxRetries}) - msg: {$msg}, sleeping {$delay}us");
                            usleep($delay);
                            continue;
                        }

                        // その他の例外は即時再スロー
                        throw $e;
                    }
                }

                if ($lastException !== null) {
                    // 最後までロックが解除されなかった場合は例外を投げる
                    throw $lastException;
                }
                // 実行済みとして記録
                $this->recordMigration($version, $name);
                $migrationDuration = microtime(true) - $migrationStart;

                $results[] = [
                    'version' => $version,
                    'name' => $name,
                    'status' => 'success',
                    'duration_ms' => (int)($migrationDuration * 1000)
                ];

                Logger::getInstance()->info("Migration {$version} ({$name}) executed successfully in " . round($migrationDuration, 3) . "s");
                echo "Finished migration {$version}: " . basename($file) . " (" . round($migrationDuration, 3) . "s)\n";
            } catch (Exception $e) {
                $results[] = [
                    'version' => $version,
                    'name' => basename($file, '.php'),
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];

                Logger::getInstance()->error("Migration {$version} failed: " . $e->getMessage());
                throw $e;
            }
        }

        return $results;
    }

    /**
     * マイグレーションファイルを検出
     */
    private function discoverMigrations(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = glob($this->migrationsDir . '/*.php');
        if ($files === false) {
            return [];
        }

        $migrations = [];

        foreach ($files as $file) {
            $filename = basename($file);

            if (preg_match('/^(\d+)_.*\.php$/', $filename, $matches)) {
                $version = (int)$matches[1];
                $migrations[$version] = $file;
            }
        }

        ksort($migrations);

        return $migrations;
    }

    /**
     * マイグレーションファイルを実行
     */
    private function executeMigration(string $file, int $version): string
    {
        // マイグレーションファイルを読み込み
        $migration = require $file;

        if (!is_array($migration)) {
            throw new Exception("Migration file must return an array: {$file}");
        }

        if (!isset($migration['name']) || !isset($migration['up'])) {
            throw new Exception("Migration file must contain 'name' and 'up' keys: {$file}");
        }

        if (!is_callable($migration['up'])) {
            throw new Exception("Migration 'up' must be callable: {$file}");
        }

        // up関数の引数の数をチェックして、新旧両方の形式に対応
        $reflection = new \ReflectionFunction($migration['up']);
        $paramCount = $reflection->getNumberOfParameters();

        if ($paramCount >= 2) {
            // 新形式: function(PDO $db, MigrationHelper $helper)
            $migration['up']($this->db, new MigrationHelper());
        } else {
            // 旧形式: function(PDO $db)
            $migration['up']($this->db);
        }

        return $migration['name'];
    }

    /**
     * 実行済みマイグレーションを記録
     */
    private function recordMigration(int $version, string $name): void
    {
        $stmt = $this->db->prepare("INSERT INTO migrations (version, name) VALUES (?, ?)");
        $stmt->execute([$version, $name]);
    }

    /**
     * 実行済みマイグレーションのバージョン番号を取得
     */
    private function getExecutedMigrations(): array
    {
        $stmt = $this->db->query("SELECT version FROM migrations ORDER BY version");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * すべての実行済みマイグレーション情報を取得
     */
    public function getExecutedMigrationDetails(): array
    {
        $stmt = $this->db->query("SELECT version, name, executed_at FROM migrations ORDER BY version ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 指定したマイグレーションが実行済みかチェック
     */
    public function isMigrationExecuted(int $version): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM migrations WHERE version = ?");
        $stmt->execute([$version]);
        return $stmt->fetchColumn() > 0;
    }
}
