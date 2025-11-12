<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

require_once __DIR__ . '/../Security/SecurityUtil.php';

/**
 * アクセスログデータベース接続クラス
 *
 * オプション機能として詳細なアクセスログを記録
 * 設定でON/OFFを切り替え可能
 *
 * - SQLite: 専用のaccess_logs.dbを使用
 * - MySQL/PostgreSQL: 1DB構成のためConnection::getInstance()を返す
 */
class AccessLogsConnection
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
     * アクセスログ機能が有効かチェック
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        self::loadConfig();
        $driver = self::$config['database']['driver'] ?? 'sqlite';

        if ($driver === 'sqlite') {
            return self::$config['database']['sqlite']['access_logs']['enabled'] ?? false;
        } else {
            // MySQL/PostgreSQLの場合は常に有効（1DB構成で管理）
            return true;
        }
    }

    /**
     * データベース接続を取得
     *
     * @return PDO|null 無効の場合はnull
     * @throws PDOException データベース接続エラー
     */
    public static function getInstance(): ?PDO
    {
        // 無効の場合はnullを返す
        if (!self::isEnabled()) {
            return null;
        }

        // MySQL/PostgreSQLの場合は、Connectionと同じインスタンスを返す（1DB構成）
        self::loadConfig();
        $driver = self::$config['database']['driver'] ?? 'sqlite';

        if ($driver !== 'sqlite') {
            return Connection::getInstance();
        }

        // SQLiteの場合は専用のaccess_logs.dbを使用
        if (self::$instance === null) {
            try {
                self::loadConfig();
                $dbPath = self::$config['database']['sqlite']['access_logs']['path'];

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
                throw new PDOException('アクセスログデータベース接続エラー: ' . $e->getMessage());
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

        // access_logsテーブル
        $db->exec("
            CREATE TABLE IF NOT EXISTS access_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT,
                user_agent TEXT,
                referer TEXT,
                request_uri TEXT,
                request_method TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

    // インデックス作成
    \App\Database\DatabaseHelper::createIndexIfNotExists($db, 'idx_access_logs_created', 'access_logs', 'created_at DESC');
    \App\Database\DatabaseHelper::createIndexIfNotExists($db, 'idx_access_logs_ip', 'access_logs', 'ip_address');
    }

    /**
     * 接続をクローズ（主にテスト用）
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
