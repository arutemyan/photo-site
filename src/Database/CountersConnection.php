<?php

declare(strict_types=1);

namespace App\Database;

use App\Utils\Logger;
use PDO;
use PDOException;

require_once __DIR__ . '/../Security/SecurityUtil.php';

/**
 * カウンターデータベース接続クラス
 *
 * 閲覧数などの頻繁に更新されるカウンターを管理
 *
 * - SQLite: メインDBとは分離して書き込みロックの競合を回避（counters.db）
 * - MySQL/PostgreSQL: 1DB構成のためConnection::getInstance()を返す
 */
class CountersConnection
{
    private static ?PDO $instance = null;
    private static ?array $config = null;

    /**
     * コンストラクタをプライベートに（シングルトン）
     */
    private function __construct()
    {
    }

    /**
     * 設定ファイルを読み込み
     */
    private static function loadConfig(): void
    {
        if (self::$config === null) {
            self::$config = \App\Config\ConfigManager::getInstance()->getConfig();
            if (self::$config === null) {
                throw new PDOException('Database configuration not available');
            }
        }
    }

    /**
     * データベース接続を取得
     *
     * @return PDO
     * @throws PDOException データベース接続エラー
     */
    public static function getInstance(): PDO
    {
        // MySQL/PostgreSQLの場合は、Connectionと同じインスタンスを返す（1DB構成）
        self::loadConfig();
        $driver = self::$config['database']['driver'] ?? 'sqlite';

        if ($driver !== 'sqlite') {
            return Connection::getInstance();
        }

        // SQLiteの場合は専用のcounters.dbを使用
        if (self::$instance === null) {
            try {
                self::loadConfig();
                $dbPath = self::$config['database']['sqlite']['counters']['path'];

                // データベースディレクトリを作成して保護
                $dbDir = dirname($dbPath);
                $permission = self::$config['database']['directory_permission'] ?? 0755;
                ensureSecureDirectory($dbDir, $permission);

                $pdoOptions = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                self::$instance = new PDO(
                    'sqlite:' . $dbPath,
                    null,
                    null,
                    $pdoOptions
                );

                // データベーススキーマを初期化
                self::initializeSchema();
            } catch (PDOException $e) {
                throw new PDOException('カウンターデータベース接続エラー: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * データベーススキーマを初期化（SQLiteのみ）
     */
    private static function initializeSchema(): void
    {
        $db = self::$instance;

        // view_countsテーブルの存在確認
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='view_counts'");
        $tableExists = $stmt->fetch() !== false;

        if (!$tableExists) {
            // 新規作成：最初からpost_typeを含める
            Logger::getInstance()->info("CountersConnection: Creating new view_counts table with post_type");
            $db->exec("
                CREATE TABLE view_counts (
                    post_id INTEGER NOT NULL,
                    post_type INTEGER DEFAULT 0 NOT NULL,
                    count INTEGER DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (post_id, post_type)
                )
            ");
            \App\Database\DatabaseHelper::createIndexIfNotExists($db, 'idx_view_counts_updated', 'view_counts', 'updated_at DESC');
            Logger::getInstance()->info("CountersConnection: Successfully created view_counts table");
            return;
        }

        // 既存テーブル：post_typeカラムの追加が必要かチェック
        $stmt = $db->query("PRAGMA table_info(view_counts)");
        $columns = $stmt->fetchAll();
        $hasPostType = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'post_type') {
                $hasPostType = true;
                break;
            }
        }

        if (!$hasPostType) {
            Logger::getInstance()->info("CountersConnection: Migrating view_counts table to add post_type column");

            // 一時テーブルが既に存在する場合は削除（前回の失敗を考慮）
            try {
                $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='view_counts_temp'");
                if ($stmt->fetch() !== false) {
                    Logger::getInstance()->info("CountersConnection: Cleaning up existing view_counts_temp table");
                    $db->exec("DROP TABLE view_counts_temp");
                }
            } catch (\Exception $e) {
                Logger::getInstance()->warning("CountersConnection: Warning during cleanup: " . $e->getMessage());
            }

            // マイグレーション実行
            try {
                Logger::getInstance()->info("CountersConnection: Step 1 - Starting transaction");
                $db->exec("BEGIN TRANSACTION");

                Logger::getInstance()->info("CountersConnection: Step 2 - Creating temporary table");
                $db->exec("CREATE TABLE view_counts_temp AS SELECT * FROM view_counts");

                // データ件数を確認
                $stmt = $db->query("SELECT COUNT(*) as count FROM view_counts_temp");
                $count = $stmt->fetchColumn();
                Logger::getInstance()->info("CountersConnection: Step 3 - Copied {$count} records to temporary table");

                Logger::getInstance()->info("CountersConnection: Step 4 - Dropping old table");
                $db->exec("DROP TABLE view_counts");

                Logger::getInstance()->info("CountersConnection: Step 5 - Creating new table with post_type");
                $db->exec("
                    CREATE TABLE view_counts (
                        post_id INTEGER NOT NULL,
                        post_type INTEGER DEFAULT 0 NOT NULL,
                        count INTEGER DEFAULT 0,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (post_id, post_type)
                    )
                ");

                Logger::getInstance()->info("CountersConnection: Step 6 - Migrating data");
                $db->exec("
                    INSERT INTO view_counts (post_id, post_type, count, updated_at)
                    SELECT post_id, 0, count, updated_at FROM view_counts_temp
                ");

                // 移行後のデータ件数を確認
                $stmt = $db->query("SELECT COUNT(*) as count FROM view_counts");
                $newCount = $stmt->fetchColumn();
                Logger::getInstance()->info("CountersConnection: Step 7 - Migrated {$newCount} records to new table");

                Logger::getInstance()->info("CountersConnection: Step 8 - Dropping temporary table");
                $db->exec("DROP TABLE view_counts_temp");

                Logger::getInstance()->info("CountersConnection: Step 9 - Creating index");
                \App\Database\DatabaseHelper::createIndexIfNotExists($db, 'idx_view_counts_updated', 'view_counts', 'updated_at DESC');

                Logger::getInstance()->info("CountersConnection: Step 10 - Committing transaction");
                $db->exec("COMMIT");

                Logger::getInstance()->info("CountersConnection: Successfully migrated view_counts table (migrated {$newCount}/{$count} records)");

            } catch (\Exception $e) {
                Logger::getInstance()->error("CountersConnection: Migration failed at: " . $e->getMessage());
                Logger::getInstance()->error("CountersConnection: Stack trace: " . $e->getTraceAsString());

                // トランザクションをロールバック
                try {
                    $db->exec("ROLLBACK");
                    Logger::getInstance()->warning("CountersConnection: Transaction rolled back");
                } catch (\Exception $rollbackError) {
                    Logger::getInstance()->error("CountersConnection: Rollback also failed: " . $rollbackError->getMessage());
                }

                // フォールバック：エラーが発生した場合は安全策として一時テーブルを確認
                try {
                    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='view_counts_temp'");
                    if ($stmt->fetch() !== false) {
                        Logger::getInstance()->info("CountersConnection: Attempting to recover from view_counts_temp");

                        // view_countsが存在しない場合、tempから戻す
                        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='view_counts'");
                        if ($stmt->fetch() === false) {
                            Logger::getInstance()->info("CountersConnection: Restoring view_counts from temp table");
                            $db->exec("ALTER TABLE view_counts_temp RENAME TO view_counts");
                            Logger::getInstance()->info("CountersConnection: Restored original table, migration will retry on next access");
                            return;
                        }
                    }
                } catch (\Exception $recoveryError) {
                    Logger::getInstance()->error("CountersConnection: Recovery attempt failed: " . $recoveryError->getMessage());
                }

                // 致命的エラー：マイグレーション失敗
                Logger::getInstance()->error("CountersConnection: CRITICAL - Migration failed completely");
                Logger::getInstance()->error("CountersConnection: The view_counts table may be in an inconsistent state");
                Logger::getInstance()->error("CountersConnection: To fix manually, delete the counters.db file and it will be recreated");

                throw new \Exception(
                    "Failed to migrate view_counts table. " .
                    "Please check error logs or delete counters.db to recreate: " .
                    $e->getMessage()
                );
            }
        } else {
            // post_typeカラムが既に存在する場合、インデックスのみ確認
            \App\Database\DatabaseHelper::createIndexIfNotExists($db, 'idx_view_counts_updated', 'view_counts', 'updated_at DESC');
        }
    }

    /**
     * 接続をクローズ（主にテスト用）
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
