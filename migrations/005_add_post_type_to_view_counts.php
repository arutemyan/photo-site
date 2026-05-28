<?php

/**
 * マイグレーション 005: view_countsテーブルにpost_typeカラムを追加
 *
 * - post_typeカラム追加（0=single, 1=group）
 * - 既存データは全てsingle（post_type=0）として扱う
 * - 主キーを(post_id, post_type)の複合キーに変更
 *
 * 対象DB:
 * - MySQL/PostgreSQL: gallery.db内のview_countsテーブル
 * - SQLite: counters.dbのview_countsテーブル（CountersConnectionが自動処理）
 */

use App\Database\DatabaseHelper;
use App\Database\MigrationHelper;
use App\Database\CountersConnection;
use App\Utils\Logger;

return [
    'name' => 'add_post_type_to_view_counts',

    'up' => function (PDO $db, MigrationHelper $helper) {
        $driver = DatabaseHelper::getDriver($db);
        $intType = DatabaseHelper::getIntegerType($db);

        Logger::getInstance()->error("Migration 005: Starting migration for driver: {$driver}");

        // SQLiteの場合はCountersConnection::initializeSchema()が自動処理するためスキップ
        if ($driver === 'sqlite') {
            Logger::getInstance()->error("Migration 005: SQLite detected - counters.db is managed by CountersConnection");
            Logger::getInstance()->error("Migration 005: Triggering CountersConnection to ensure migration");

            // CountersConnectionを呼び出して自動マイグレーションをトリガー
            try {
                CountersConnection::getInstance();
                Logger::getInstance()->error("Migration 005: CountersConnection initialized successfully");
            } catch (Exception $e) {
                Logger::getInstance()->error("Migration 005: CountersConnection initialization failed: " . $e->getMessage());
                // CountersConnectionの初期化失敗は致命的ではない（次回アクセス時に再試行される）
            }

            return;
        }

        // MySQL/PostgreSQLの場合: gallery.db内のview_countsテーブルを処理
        Logger::getInstance()->error("Migration 005: Processing MySQL/PostgreSQL migration");

        // post_typeカラムを追加
        if (!$helper->columnExists($db, 'view_counts', 'post_type')) {
            Logger::getInstance()->error("Migration 005: Adding post_type column");
            $helper->addColumnIfNotExists($db, 'view_counts', 'post_type', "{$intType} DEFAULT 0 NOT NULL");
        } else {
            Logger::getInstance()->error("Migration 005: post_type column already exists (skipped)");
            return; // 既にマイグレーション済み
        }

        // 主キー制約の変更
        try {
            if ($driver === 'mysql') {
                Logger::getInstance()->error("Migration 005: Updating MySQL primary key");

                // 既存の主キーを確認
                $stmt = $db->query("SHOW KEYS FROM view_counts WHERE Key_name = 'PRIMARY'");
                $primaryKeys = $stmt->fetchAll();

                // 主キーが(post_id, post_type)でない場合のみ変更
                $needsUpdate = true;
                if (count($primaryKeys) === 2) {
                    $keyColumns = array_column($primaryKeys, 'Column_name');
                    if (in_array('post_id', $keyColumns) && in_array('post_type', $keyColumns)) {
                        $needsUpdate = false;
                        Logger::getInstance()->error("Migration 005: Primary key already set to (post_id, post_type)");
                    }
                }

                if ($needsUpdate) {
                    $db->exec("ALTER TABLE view_counts DROP PRIMARY KEY");
                    Logger::getInstance()->error("Migration 005: Dropped old primary key");
                    $db->exec("ALTER TABLE view_counts ADD PRIMARY KEY (post_id, post_type)");
                    Logger::getInstance()->error("Migration 005: Added new composite primary key");
                }

            } elseif ($driver === 'postgresql') {
                Logger::getInstance()->error("Migration 005: Updating PostgreSQL primary key");

                // 既存の主キーを確認
                $stmt = $db->query("
                    SELECT kcu.column_name
                    FROM information_schema.table_constraints tc
                    JOIN information_schema.key_column_usage kcu
                        ON tc.constraint_name = kcu.constraint_name
                    WHERE tc.table_name = 'view_counts'
                        AND tc.constraint_type = 'PRIMARY KEY'
                ");
                $primaryKeys = $stmt->fetchAll();

                // 主キーが(post_id, post_type)でない場合のみ変更
                $needsUpdate = true;
                if (count($primaryKeys) === 2) {
                    $keyColumns = array_column($primaryKeys, 'column_name');
                    if (in_array('post_id', $keyColumns) && in_array('post_type', $keyColumns)) {
                        $needsUpdate = false;
                        Logger::getInstance()->error("Migration 005: Primary key already set to (post_id, post_type)");
                    }
                }

                if ($needsUpdate) {
                    $db->exec("ALTER TABLE view_counts DROP CONSTRAINT IF EXISTS view_counts_pkey");
                    Logger::getInstance()->error("Migration 005: Dropped old primary key constraint");
                    $db->exec("ALTER TABLE view_counts ADD PRIMARY KEY (post_id, post_type)");
                    Logger::getInstance()->error("Migration 005: Added new composite primary key");
                }
            }

            Logger::getInstance()->error("Migration 005: Successfully updated primary key");

        } catch (PDOException $e) {
            Logger::getInstance()->error("Migration 005: Primary key update error: " . $e->getMessage());

            // 既に複合キーの場合はエラーを無視
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false ||
                strpos($e->getMessage(), 'Multiple primary key') !== false) {
                Logger::getInstance()->error("Migration 005: Primary key already exists (ignored)");
            } else {
                throw $e;
            }
        }

        Logger::getInstance()->error("Migration 005: Migration completed successfully");
    }
];
