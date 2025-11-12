<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * データベースヘルパークラス
 *
 * 各データベースの構文差異を吸収するヘルパー関数を提供
 */
class DatabaseHelper
{
    /**
     * 現在のデータベースドライバーを取得
     *
     * @param PDO $pdo
     * @return string 'sqlite', 'mysql', 'postgresql'
     */
    public static function getDriver(PDO $pdo): string
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        return match($driver) {
            'pgsql' => 'postgresql',
            default => $driver
        };
    }

    /**
     * AUTO INCREMENT構文を取得
     *
     * @param PDO $pdo
     * @return string
     */
    public static function getAutoIncrement(PDO $pdo): string
    {
        return match(self::getDriver($pdo)) {
            'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'postgresql' => 'SERIAL PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT'
        };
    }

    /**
     * UPSERT構文を生成（INSERT ... ON CONFLICT / ON DUPLICATE KEY UPDATE）
     *
     * @param PDO $pdo
     * @param string $table テーブル名
     * @param array $insertColumns 挿入するカラム
     * @param array $updateColumns 更新するカラム（キー以外）
     * @param string|array $conflictKey 競合判定するキー
     * @return string SQL文
     */
    public static function getUpsertSQL(
        PDO $pdo,
        string $table,
        array $insertColumns,
        array $updateColumns,
        string|array $conflictKey
    ): string {
        $driver = self::getDriver($pdo);
        $conflictKeys = is_array($conflictKey) ? $conflictKey : [$conflictKey];

        $insertCols = implode(', ', $insertColumns);
        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));

        switch ($driver) {
            case 'sqlite':
            case 'postgresql':
                // ON CONFLICT構文
                $conflictClause = '(' . implode(', ', $conflictKeys) . ')';
                $updateSet = [];
                foreach ($updateColumns as $col) {
                    $updateSet[] = "{$col} = EXCLUDED.{$col}";
                }
                return "INSERT INTO {$table} ({$insertCols}) VALUES ({$placeholders}) " .
                       "ON CONFLICT {$conflictClause} DO UPDATE SET " . implode(', ', $updateSet);

            case 'mysql':
                // ON DUPLICATE KEY UPDATE構文
                $updateSet = [];
                foreach ($updateColumns as $col) {
                    $updateSet[] = "{$col} = VALUES({$col})";
                }
                return "INSERT INTO {$table} ({$insertCols}) VALUES ({$placeholders}) " .
                       "ON DUPLICATE KEY UPDATE " . implode(', ', $updateSet);

            default:
                throw new \Exception("Unsupported database driver: {$driver}");
        }
    }

    /**
     * INSERT OR IGNORE構文を生成
     *
     * @param PDO $pdo
     * @param string $table テーブル名
     * @param array $columns カラム名の配列
     * @return string SQL文
     */
    public static function getInsertIgnoreSQL(PDO $pdo, string $table, array $columns): string
    {
        $driver = self::getDriver($pdo);
        $cols = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        switch ($driver) {
            case 'sqlite':
                return "INSERT OR IGNORE INTO {$table} ({$cols}) VALUES ({$placeholders})";
            case 'mysql':
                return "INSERT IGNORE INTO {$table} ({$cols}) VALUES ({$placeholders})";
            case 'postgresql':
                // PostgreSQLはUNIQUE制約のカラムを指定する必要がある
                // 汎用的には最初のカラムを使用（通常はIDやユニークキー）
                $conflictKey = $columns[0];
                return "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders}) " .
                       "ON CONFLICT ({$conflictKey}) DO NOTHING";
            default:
                throw new \Exception("Unsupported database driver: {$driver}");
        }
    }

    /**
     * DATETIME型のデフォルト値を取得
     *
     * @param PDO $pdo
     * @return string
     */
    public static function getCurrentTimestamp(PDO $pdo): string
    {
        return match(self::getDriver($pdo)) {
            'sqlite', 'mysql' => 'CURRENT_TIMESTAMP',
            'postgresql' => 'CURRENT_TIMESTAMP',
            default => 'CURRENT_TIMESTAMP'
        };
    }

    /**
     * TEXT型を取得
     *
     * @param PDO $pdo
     * @param int|null $length 最大長（MySQL/PostgreSQLで使用）
     * @return string
     */
    public static function getTextType(PDO $pdo, ?int $length = null): string
    {
        $driver = self::getDriver($pdo);

        switch ($driver) {
            case 'sqlite':
                return 'TEXT';
            case 'mysql':
                if ($length && $length <= 65535) {
                    return "VARCHAR({$length})";
                }
                return 'TEXT';
            case 'postgresql':
                if ($length) {
                    return "VARCHAR({$length})";
                }
                return 'TEXT';
            default:
                return 'TEXT';
        }
    }

    /**
     * INTEGER型を取得
     *
     * @param PDO $pdo
     * @return string
     */
    public static function getIntegerType(PDO $pdo): string
    {
        return match(self::getDriver($pdo)) {
            'sqlite' => 'INTEGER',
            'mysql' => 'INT',
            'postgresql' => 'INTEGER',
            default => 'INTEGER'
        };
    }

    /**
     * DATETIME型を取得
     *
     * @param PDO $pdo
     * @return string
     */
    public static function getDateTimeType(PDO $pdo): string
    {
        return match(self::getDriver($pdo)) {
            'sqlite' => 'DATETIME',
            'mysql' => 'DATETIME',
            'postgresql' => 'TIMESTAMP',
            default => 'DATETIME'
        };
    }

    /**
     * TIMESTAMP型を取得
     *
     * @param PDO $pdo
     * @return string
     */
    public static function getTimestampType(PDO $pdo): string
    {
        return match(self::getDriver($pdo)) {
            'sqlite' => 'TIMESTAMP',
            'mysql' => 'TIMESTAMP',
            'postgresql' => 'TIMESTAMP',
            default => 'TIMESTAMP'
        };
    }

    /**
     * 指定したインデックスが存在しなければ作成する（DBごとの差分を吸収）
     *
     * - MySQL: information_schema を参照して存在チェック後に作成（MySQLは "CREATE INDEX IF NOT EXISTS" をサポートしない場合がある）
     * - SQLite/PostgreSQL: "CREATE INDEX IF NOT EXISTS" を使用
     *
     * @param PDO $pdo
     * @param string $indexName
     * @param string $table
     * @param string $columns 列定義（必要に応じて DESC を含めて良い）
     */
    public static function createIndexIfNotExists(PDO $pdo, string $indexName, string $table, string $columns): void
    {
        $driver = self::getDriver($pdo);

        if ($driver === 'mysql') {
            // MySQL: database() で現在のスキーマ名を取得して情報スキーマを検索
            try {
                $dbStmt = $pdo->query('SELECT DATABASE() as db');
                $dbName = $dbStmt ? $dbStmt->fetchColumn() : null;

                if ($dbName) {
                    $check = $pdo->prepare(
                        'SELECT COUNT(*) as cnt FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?'
                    );
                    $check->execute([$dbName, $table, $indexName]);
                    $row = $check->fetch(PDO::FETCH_ASSOC);
                    if ($row && intval($row['cnt']) > 0) {
                        return; // 既に存在
                    }
                }
            } catch (\Exception $e) {
                // チェックに失敗しても作成を試みる
            }

            // MySQLでは列定義に DESC が含まれると古いバージョンでエラーになることがあるため除去して作成
            $columnsForMy = preg_replace('/\s+DESC/i', '', $columns);
            $pdo->exec("CREATE INDEX {$indexName} ON {$table}({$columnsForMy})");
            return;
        }

        // SQLite/PostgreSQL は IF NOT EXISTS を利用（Postgres はバージョンによるがここでは信頼する）
        $pdo->exec("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table}({$columns})");
    }
}
