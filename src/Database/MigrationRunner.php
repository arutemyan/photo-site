<?php

declare(strict_types=1);

namespace App\Database;

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
        $this->migrationsDir = $migrationsDir ?? __DIR__ . '/../../public/setup/migrations';
    }

    /**
     * 未実行のマイグレーションを実行
     *
     * @return array 実行結果の配列
     */
    public function run(): array
    {
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
                // マイグレーション実行（トランジェントなロックに備えてリトライ）
                $maxRetries = 5;
                $attempt = 0;
                $lastException = null;

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
                            error_log("Migration {$version}: database is locked, retrying ({$attempt}/{$maxRetries}) - msg: {$msg}");
                            usleep(500000); // 0.5s
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

                $results[] = [
                    'version' => $version,
                    'name' => $name,
                    'status' => 'success'
                ];

                error_log("Migration {$version} ({$name}) executed successfully");
            } catch (Exception $e) {
                $results[] = [
                    'version' => $version,
                    'name' => basename($file, '.php'),
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];

                error_log("Migration {$version} failed: " . $e->getMessage());
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
