<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use Exception;

/**
 * マイグレーション実行クラス
 *
 * public/setup/migrations/ ディレクトリ内のマイグレーションファイルを自動検出・実行します
 */
class MigrationRunner
{
    private PDO $db;
    private string $migrationsDir;

    /**
     * コンストラクタ
     *
     * @param PDO $db データベース接続
     * @param string|null $migrationsDir マイグレーションディレクトリ（デフォルト: public/setup/migrations）
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
                // マイグレーション実行
                $name = $this->executeMigration($file, $version);

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
                throw $e; // エラー時は例外をスロー
            }
        }

        return $results;
    }

    /**
     * マイグレーションファイルを検出
     *
     * @return array バージョン番号 => ファイルパスの配列
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

            // ファイル名から バージョン番号を抽出（例: 001_add_tag_columns.php → 1）
            if (preg_match('/^(\d+)_.*\.php$/', $filename, $matches)) {
                $version = (int)$matches[1];
                $migrations[$version] = $file;
            }
        }

        // バージョン順にソート
        ksort($migrations);

        return $migrations;
    }

    /**
     * マイグレーションファイルを実行
     *
     * @param string $file マイグレーションファイルのパス
     * @param int $version バージョン番号
     * @return string マイグレーション名
     * @throws Exception
     */
    private function executeMigration(string $file, int $version): string
    {
        // マイグレーションファイルを読み込み
        $migration = require $file;

        // マイグレーション配列の検証
        if (!is_array($migration)) {
            throw new Exception("Migration file must return an array: {$file}");
        }

        if (!isset($migration['name']) || !isset($migration['up'])) {
            throw new Exception("Migration file must contain 'name' and 'up' keys: {$file}");
        }

        if (!is_callable($migration['up'])) {
            throw new Exception("Migration 'up' must be callable: {$file}");
        }

        // up関数を実行
        $migration['up']($this->db);

        return $migration['name'];
    }

    /**
     * 実行済みマイグレーションを記録
     *
     * @param int $version バージョン番号
     * @param string $name マイグレーション名
     */
    private function recordMigration(int $version, string $name): void
    {
        $stmt = $this->db->prepare("INSERT INTO migrations (version, name) VALUES (?, ?)");
        $stmt->execute([$version, $name]);
    }

    /**
     * 実行済みマイグレーションのバージョン番号を取得
     *
     * @return array バージョン番号の配列
     */
    private function getExecutedMigrations(): array
    {
        $stmt = $this->db->query("SELECT version FROM migrations ORDER BY version");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * すべての実行済みマイグレーション情報を取得
     *
     * @return array マイグレーション情報の配列
     */
    public function getExecutedMigrationDetails(): array
    {
        $stmt = $this->db->query("SELECT version, name, executed_at FROM migrations ORDER BY version ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 指定したマイグレーションが実行済みかチェック
     *
     * @param int $version マイグレーションバージョン
     * @return bool 実行済みの場合true
     */
    public function isMigrationExecuted(int $version): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM migrations WHERE version = ?");
        $stmt->execute([$version]);
        return $stmt->fetchColumn() > 0;
    }
}
