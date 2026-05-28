<?php

use App\Database\DatabaseHelper;
use App\Database\MigrationHelper;
use App\Database\CountersConnection;
use App\Utils\Logger;

return [
    'name' => 'add_view_counts_visitor_columns',

    'up' => function (PDO $db, MigrationHelper $helper) {
        $driver = DatabaseHelper::getDriver($db);
        Logger::getInstance()->info("Migration 015: Starting migration for driver: {$driver}");

        // SQLite の counters.db は CountersConnection 側で管理されるが、
        // マイグレーションファイル側でも確実にカラムを追加する。
        if ($driver === 'sqlite') {
            Logger::getInstance()->info("Migration 015: SQLite detected - ensuring visitor columns in counters.db");
            try {
                // CountersConnection を初期化して DB ファイルを確実に作る
                $db = CountersConnection::getInstance();

                // view_counts のカラム一覧を取得して、必要なら ALTER TABLE で追加する
                $stmt = $db->query("PRAGMA table_info(view_counts)");
                $cols = $stmt->fetchAll();
                $hasLastVisitor = false;
                $hasLastViewedAt = false;
                foreach ($cols as $c) {
                    $name = $c['name'] ?? ($c[1] ?? null);
                    if ($name === 'last_visitor_hash') {
                        $hasLastVisitor = true;
                    }
                    if ($name === 'last_viewed_at') {
                        $hasLastViewedAt = true;
                    }
                }

                if (!$hasLastVisitor) {
                    Logger::getInstance()->info('Migration 015: Adding last_visitor_hash column to view_counts');
                    $db->exec("ALTER TABLE view_counts ADD COLUMN last_visitor_hash TEXT;");
                } else {
                    Logger::getInstance()->info('Migration 015: last_visitor_hash already exists (skipped)');
                }

                if (!$hasLastViewedAt) {
                    Logger::getInstance()->info('Migration 015: Adding last_viewed_at column to view_counts');
                    $db->exec("ALTER TABLE view_counts ADD COLUMN last_viewed_at INTEGER;");
                } else {
                    Logger::getInstance()->info('Migration 015: last_viewed_at already exists (skipped)');
                }

                Logger::getInstance()->info("Migration 015: SQLite visitor column ensure completed");
            } catch (Exception $e) {
                Logger::getInstance()->error("Migration 015: CountersConnection or schema update failed: " . $e->getMessage());
                // ここでは例外を投げずにログに残すのみ（マイグレーションランナーの他処理に影響を与えないため）
            }

            return;
        }

        // MySQL/PostgreSQL: view_counts テーブルにカラムを追加
        try {
            if (!$helper->columnExists($db, 'view_counts', 'last_visitor_hash')) {
                Logger::getInstance()->info('Migration 015: Adding last_visitor_hash column');
                $helper->addColumnIfNotExists($db, 'view_counts', 'last_visitor_hash', 'TEXT NULL');
            } else {
                Logger::getInstance()->info('Migration 015: last_visitor_hash already exists (skipped)');
            }

            if (!$helper->columnExists($db, 'view_counts', 'last_viewed_at')) {
                Logger::getInstance()->info('Migration 015: Adding last_viewed_at column');
                $helper->addColumnIfNotExists($db, 'view_counts', 'last_viewed_at', DatabaseHelper::getIntegerType($db) . ' NULL');
            } else {
                Logger::getInstance()->info('Migration 015: last_viewed_at already exists (skipped)');
            }

            Logger::getInstance()->info('Migration 015: Migration completed');
        } catch (Exception $e) {
            Logger::getInstance()->error('Migration 015: Error: ' . $e->getMessage());
            throw $e;
        }
    }
];
