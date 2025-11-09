<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
require_once __DIR__ . '/../Security/SecurityUtil.php';

/**
 * データベース接続クラス
 *
 * シングルトンパターンでPDO接続を管理
 * SQLite / MySQL / PostgreSQL をサポート
 */
class Connection
{
    private static ?PDO $instance = null;
    private static ?string $dbPath = null;
    private static ?array $config = null;
    private static ?string $driver = null;

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
        }
    }

    /**
     * データベースドライバーを取得
     */
    public static function getDriver(): string
    {
        if (self::$driver === null) {
            self::loadConfig();
            self::$driver = self::$config['database']['driver'] ?? 'sqlite';
        }
        return self::$driver;
    }

    /**
     * DSN文字列を生成
     */
    private static function getDSN(): string
    {
        self::loadConfig();
        $driver = self::getDriver();

        switch ($driver) {
            case 'sqlite':
                $dbPath = self::$dbPath ?? self::$config['database']['sqlite']['gallery']['path'];
                return 'sqlite:' . $dbPath;

            case 'mysql':
                $config = self::$config['database']['mysql'];
                return sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $config['host'],
                    $config['port'],
                    $config['database'],
                    $config['charset']
                );

            case 'postgresql':
                $config = self::$config['database']['postgresql'];
                return sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    $config['host'],
                    $config['port'],
                    $config['database']
                );

            default:
                throw new PDOException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * 認証情報を取得
     */
    private static function getCredentials(): array
    {
        $driver = self::getDriver();

        switch ($driver) {
            case 'sqlite':
                return [null, null];

            case 'mysql':
                $config = self::$config['database']['mysql'];
                return [$config['username'], $config['password']];

            case 'postgresql':
                $config = self::$config['database']['postgresql'];
                return [$config['username'], $config['password']];

            default:
                return [null, null];
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
        if (self::$instance === null) {
            try {
                self::loadConfig();
                $driver = self::getDriver();

                // SQLiteの場合はデータベースディレクトリを作成して保護
                if ($driver === 'sqlite') {
                    $dbPath = self::$dbPath ?? self::$config['database']['sqlite']['gallery']['path'];
                    $dbDir = dirname($dbPath);
                    $permission = self::$config['database']['directory_permission'] ?? 0755;
                    ensureSecureDirectory($dbDir, $permission);
                }

                // PDOオプションを設定
                $pdoOptions = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                [$username, $password] = self::getCredentials();
                $dsn = self::getDSN();

                self::$instance = new PDO($dsn, $username, $password, $pdoOptions);

                // PostgreSQLの場合は検索パスを設定
                if ($driver === 'postgresql') {
                    $schema = self::$config['database']['postgresql']['schema'] ?? 'public';
                    self::$instance->exec("SET search_path TO {$schema}");
                }

                // SQLiteではロック待ちタイムアウトやWALモード、外部キーを有効にしておく
                if ($driver === 'sqlite') {
                    try {
                        // 待機タイムアウト (ms)
                        self::$instance->exec('PRAGMA busy_timeout = 5000');
                        // WALモード（可能であれば）を試す。失敗しても致命的ではない。
                        @self::$instance->exec("PRAGMA journal_mode = WAL");
                        // 外部キー制約を有効化
                        self::$instance->exec('PRAGMA foreign_keys = ON');
                    } catch (PDOException $e) {
                        // ログに残すが処理は継続
                        \App\Utils\Logger::getInstance()->warning('SQLite PRAGMA setup failed: ' . $e->getMessage());
                    }
                }

                // データベーススキーマを初期化
                self::initializeSchema();

                // マイグレーションを自動実行するかどうかは設定で制御
                $runMigrations = self::$config['database']['run_migrations_on_connect'] ?? true;
                if ($runMigrations) {
                    self::runMigrations();
                } else {
                    \App\Utils\Logger::getInstance()->info('Connection: auto-run migrations disabled by configuration');
                }
            } catch (PDOException $e) {
                throw new PDOException('データベース接続エラー: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * データベーススキーマを初期化
     */
    private static function initializeSchema(): void
    {
        $db = self::$instance;
        $driver = self::getDriver();
        $helper = DatabaseHelper::class;

        $autoInc = $helper::getAutoIncrement($db);
        $intType = $helper::getIntegerType($db);
        $textType = $helper::getTextType($db);
        // 短めのインデックス可能な文字型（MySQLのインデックスに長さ制限があるため）
        $shortText = $helper::getTextType($db, 191);
        $datetimeType = $helper::getDateTimeType($db);
        $timestampType = $helper::getTimestampType($db);
        $currentTimestamp = $helper::getCurrentTimestamp($db);

        // usersテーブル（管理者認証用）
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id {$autoInc},
                username {$shortText} NOT NULL UNIQUE,
                password_hash {$textType} NOT NULL,
                created_at {$datetimeType} DEFAULT {$currentTimestamp}
            )
        ");

        // postsテーブル
        $db->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id {$autoInc},
                title {$textType} NOT NULL,
                tags {$textType},
                detail {$textType},
                image_path {$textType},
                thumb_path {$textType},
                is_sensitive {$intType} DEFAULT 0,
                is_visible {$intType} NOT NULL DEFAULT 1,
                created_at {$datetimeType} DEFAULT {$currentTimestamp}
            )
        ");

        // postsテーブルのインデックス
        $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_created_at ON posts(created_at DESC)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_visible ON posts(is_visible, created_at DESC)");

        // tagsテーブル（タグマスタ）
        $db->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id {$autoInc},
                name {$shortText} NOT NULL UNIQUE,
                created_at {$timestampType} DEFAULT {$currentTimestamp}
            )
        ");

        // tagsテーブルのインデックス
        $db->exec("CREATE INDEX IF NOT EXISTS idx_tags_name ON tags(name)");

        // migrationsテーブル（マイグレーションバージョン管理）
        $db->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                version {$intType} PRIMARY KEY,
                name {$textType} NOT NULL,
                executed_at {$timestampType} DEFAULT {$currentTimestamp}
            )
        ");

        // settingsテーブル
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id {$autoInc},
                key {$shortText} NOT NULL UNIQUE,
                value {$textType} NOT NULL,
                updated_at {$timestampType} DEFAULT {$currentTimestamp}
            )
        ");

        // themesテーブル（テーマカスタマイズ用）
        $db->exec("
            CREATE TABLE IF NOT EXISTS themes (
                id {$autoInc},
                header_html {$textType},
                footer_html {$textType},
                site_title {$textType} DEFAULT 'イラストポートフォリオ',
                site_subtitle {$textType} DEFAULT 'Illustration Portfolio',
                site_description {$textType} DEFAULT 'イラストレーターのポートフォリオサイト',
                primary_color {$textType} DEFAULT '#8B5AFA',
                secondary_color {$textType} DEFAULT '#667eea',
                accent_color {$textType} DEFAULT '#FFD700',
                background_color {$textType} DEFAULT '#1a1a1a',
                text_color {$textType} DEFAULT '#ffffff',
                heading_color {$textType} DEFAULT '#ffffff',
                footer_bg_color {$textType} DEFAULT '#2a2a2a',
                footer_text_color {$textType} DEFAULT '#cccccc',
                card_border_color {$textType} DEFAULT '#333333',
                card_bg_color {$textType} DEFAULT '#252525',
                card_shadow_opacity {$textType} DEFAULT '0.3',
                link_color {$textType} DEFAULT '#8B5AFA',
                link_hover_color {$textType} DEFAULT '#a177ff',
                tag_bg_color {$textType} DEFAULT '#8B5AFA',
                tag_text_color {$textType} DEFAULT '#ffffff',
                filter_active_bg_color {$textType} DEFAULT '#8B5AFA',
                filter_active_text_color {$textType} DEFAULT '#ffffff',
                header_image {$textType},
                logo_image {$textType},
                updated_at {$datetimeType} DEFAULT {$currentTimestamp}
            )
        ");

        // MySQL/PostgreSQLの場合のみ、view_countsテーブルも作成（1DB構成のため）
        if ($driver !== 'sqlite') {
            $db->exec("
                CREATE TABLE IF NOT EXISTS view_counts (
                    post_id {$intType} NOT NULL,
                    post_type {$intType} DEFAULT 0 NOT NULL,
                    count {$intType} DEFAULT 0,
                    updated_at {$datetimeType} DEFAULT {$currentTimestamp},
                    PRIMARY KEY (post_id, post_type)
                )
            ");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_view_counts_updated ON view_counts(updated_at DESC)");
        }

        // デフォルトテーマを作成（存在しない場合）
        $stmt = $db->query("SELECT COUNT(*) as count FROM themes");
        $result = $stmt->fetch();

        if ($result['count'] == 0) {
            $db->exec("INSERT INTO themes (header_html, footer_html) VALUES ('', '')");
        }
    }

    /**
     * マイグレーション実行
     */
    private static function runMigrations(): void
    {
        try {
            $runner = new MigrationRunner(self::$instance);
            $runner->run();
        } catch (\Exception $e) {
            \App\Utils\Logger::getInstance()->error("Migration execution failed: " . $e->getMessage());
            // マイグレーションに失敗した場合は例外を再スローして接続初期化を中断する
            throw $e;
        }
    }

    /**
     * マイグレーションランナーを取得
     *
     * @return MigrationRunner
     */
    public static function getMigrationRunner(): MigrationRunner
    {
        return new MigrationRunner(self::getInstance());
    }

    /**
     * 指定したマイグレーションが実行済みかチェック
     *
     * @param int $version マイグレーションバージョン
     * @return bool 実行済みの場合true
     */
    public static function isMigrationExecuted(int $version): bool
    {
        return self::getMigrationRunner()->isMigrationExecuted($version);
    }

    /**
     * 実行済みマイグレーション一覧を取得
     *
     * @return array マイグレーション情報の配列
     */
    public static function getExecutedMigrations(): array
    {
        return self::getMigrationRunner()->getExecutedMigrationDetails();
    }

    /**
     * テスト用にデータベースパスを設定（SQLiteのみ）
     *
     * @param string $path データベースファイルのパス
     */
    public static function setDatabasePath(string $path): void
    {
        self::$dbPath = $path;
        self::$instance = null; // インスタンスをリセット
    }

    /**
     * 接続をクローズ（主にテスト用）
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
