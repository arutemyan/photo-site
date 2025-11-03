<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

/**
 * マイグレーションヘルパークラス
 *
 * マイグレーションの冪等性を確保するためのユーティリティ
 */
class MigrationHelper
{
    /**
     * カラムが存在するかチェック
     *
     * @param PDO $db データベース接続
     * @param string $table テーブル名
     * @param string $column カラム名
     * @return bool カラムが存在する場合true
     */
    public function columnExists(PDO $db, string $table, string $column): bool
    {
        $driver = DatabaseHelper::getDriver($db);

        try {
            if ($driver === 'sqlite') {
                $stmt = $db->query("PRAGMA table_info({$table})");
                $columns = $stmt->fetchAll();
                foreach ($columns as $col) {
                    if ($col['name'] === $column) {
                        return true;
                    }
                }
                return false;
            } elseif ($driver === 'mysql') {
                $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
                $stmt->execute([$column]);
                return $stmt->fetch() !== false;
            } elseif ($driver === 'postgresql') {
                $stmt = $db->prepare("
                    SELECT column_name
                    FROM information_schema.columns
                    WHERE table_name = ? AND column_name = ?
                ");
                $stmt->execute([$table, $column]);
                return $stmt->fetch() !== false;
            }
        } catch (PDOException $e) {
            error_log("MigrationHelper: Error checking column {$table}.{$column}: " . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * テーブルが存在するかチェック
     *
     * @param PDO $db データベース接続
     * @param string $table テーブル名
     * @return bool テーブルが存在する場合true
     */
    public function tableExists(PDO $db, string $table): bool
    {
        $driver = DatabaseHelper::getDriver($db);

        try {
            if ($driver === 'sqlite') {
                $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
                $stmt->execute([$table]);
                return $stmt->fetch() !== false;
            } elseif ($driver === 'mysql') {
                $stmt = $db->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                return $stmt->fetch() !== false;
            } elseif ($driver === 'postgresql') {
                $stmt = $db->prepare("
                    SELECT tablename
                    FROM pg_tables
                    WHERE tablename = ?
                ");
                $stmt->execute([$table]);
                return $stmt->fetch() !== false;
            }
        } catch (PDOException $e) {
            error_log("MigrationHelper: Error checking table {$table}: " . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * インデックスが存在するかチェック
     *
     * @param PDO $db データベース接続
     * @param string $table テーブル名
     * @param string $index インデックス名
     * @return bool インデックスが存在する場合true
     */
    public function indexExists(PDO $db, string $table, string $index): bool
    {
        $driver = DatabaseHelper::getDriver($db);

        try {
            if ($driver === 'sqlite') {
                $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='index' AND name=?");
                $stmt->execute([$index]);
                return $stmt->fetch() !== false;
            } elseif ($driver === 'mysql') {
                $stmt = $db->prepare("SHOW INDEX FROM {$table} WHERE Key_name = ?");
                $stmt->execute([$index]);
                return $stmt->fetch() !== false;
            } elseif ($driver === 'postgresql') {
                $stmt = $db->prepare("
                    SELECT indexname
                    FROM pg_indexes
                    WHERE tablename = ? AND indexname = ?
                ");
                $stmt->execute([$table, $index]);
                return $stmt->fetch() !== false;
            }
        } catch (PDOException $e) {
            error_log("MigrationHelper: Error checking index {$index} on {$table}: " . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * カラムを安全に追加（存在しない場合のみ）
     *
     * @param PDO $db データベース接続
     * @param string $table テーブル名
     * @param string $column カラム名
     * @param string $definition カラム定義（例: "INTEGER DEFAULT 0 NOT NULL"）
     * @return bool 追加された場合true、既存の場合false
     */
    public function addColumnIfNotExists(PDO $db, string $table, string $column, string $definition): bool
    {
        if ($this->columnExists($db, $table, $column)) {
            error_log("MigrationHelper: Column {$table}.{$column} already exists (skipped)");
            return false;
        }

        try {
            $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            error_log("MigrationHelper: Added column {$table}.{$column}");
            return true;
        } catch (PDOException $e) {
            error_log("MigrationHelper: Failed to add column {$table}.{$column}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * インデックスを安全に作成（存在しない場合のみ）
     *
     * @param PDO $db データベース接続
     * @param string $table テーブル名
     * @param string $index インデックス名
     * @param string $columns カラムリスト（例: "column1 DESC, column2 ASC"）
     * @param bool $unique ユニークインデックスの場合true
     * @return bool 作成された場合true、既存の場合false
     */
    public function addIndexIfNotExists(
        PDO $db,
        string $table,
        string $index,
        string $columns,
        bool $unique = false
    ): bool {
        if ($this->indexExists($db, $table, $index)) {
            error_log("MigrationHelper: Index {$index} on {$table} already exists (skipped)");
            return false;
        }

        try {
            $uniqueKeyword = $unique ? 'UNIQUE' : '';
            $db->exec("CREATE {$uniqueKeyword} INDEX {$index} ON {$table}({$columns})");
            error_log("MigrationHelper: Created index {$index} on {$table}");
            return true;
        } catch (PDOException $e) {
            error_log("MigrationHelper: Failed to create index {$index} on {$table}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * テーブルを安全に作成（存在しない場合のみ）
     *
     * @param PDO $db データベース接続
     * @param string $table テーブル名
     * @param string $definition テーブル定義SQL（CREATE TABLE部分を除く）
     * @return bool 作成された場合true、既存の場合false
     */
    public function createTableIfNotExists(PDO $db, string $table, string $definition): bool
    {
        if ($this->tableExists($db, $table)) {
            error_log("MigrationHelper: Table {$table} already exists (skipped)");
            return false;
        }

        try {
            $db->exec("CREATE TABLE {$table} {$definition}");
            error_log("MigrationHelper: Created table {$table}");
            return true;
        } catch (PDOException $e) {
            error_log("MigrationHelper: Failed to create table {$table}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * テーブルを安全に削除（存在する場合のみ）。ロック等のトランジェントな失敗に対してリトライする。
     *
     * @param PDO $db
     * @param string $table
     * @param int $maxRetries
     * @param int $delayUs マイクロ秒
     * @return bool true: 削除された / 既に存在しなかった, false: 削除に失敗
     */
    public function dropTableIfExistsSafe(PDO $db, string $table, int $maxRetries = 5, int $delayUs = 500000): bool
    {
        if (!$this->tableExists($db, $table)) {
            error_log("MigrationHelper: Table {$table} does not exist (nothing to drop)");
            return true;
        }

        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                $db->exec("DROP TABLE {$table}");
                error_log("MigrationHelper: Dropped table {$table} successfully");
                return true;
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                if (strpos($msg, 'database is locked') !== false || stripos($msg, 'busy') !== false) {
                    $attempt++;
                    error_log("MigrationHelper: DROP TABLE {$table} locked, retrying ({$attempt}/{$maxRetries})");
                    usleep($delayUs);
                    continue;
                }

                // その他の例外は再スロー
                error_log("MigrationHelper: DROP TABLE {$table} failed: " . $msg);
                throw $e;
            }
        }

        error_log("MigrationHelper: Failed to drop table {$table} after {$maxRetries} attempts");
        return false;
    }
}
