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
                    // SQLインジェクション対策: スキーマ名を正規表現で検証
                    // 正規表現で検証済みなので、直接使用可能（クォート不要）
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
                        throw new \PDOException("Invalid PostgreSQL schema name: {$schema}");
                    }
                    // 検証済みのスキーマ名を直接使用
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

                // スキーマ初期化はマイグレーションで管理します。
                // ここでは自動的にスキーマを作成しません（public/setup/run_migrations.php を使ってください）。

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

    // initializeSchema() was removed: schema is managed via migrations in public/setup/migrations

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
