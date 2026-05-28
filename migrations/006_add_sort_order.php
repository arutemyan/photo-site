<?php

/**
 * マイグレーション 006: sort_orderカラムの追加
 *
 * - postsテーブルにsort_orderカラムを追加
 * - group_postsテーブルにsort_orderカラムを追加
 * - デフォルト値は0（通常の作成日時順）
 * - プラス値：優先度アップ（前方表示）
 * - マイナス値：優先度ダウン（後方表示）
 *
 * 対象DB: gallery.db
 */

use App\Database\DatabaseHelper;
use App\Database\MigrationHelper;
use App\Utils\Logger;

return [
    'name' => 'add_sort_order',

    'up' => function (PDO $db, MigrationHelper $helper) {
        $driver = DatabaseHelper::getDriver($db);
        $intType = DatabaseHelper::getIntegerType($db);

        Logger::getInstance()->info("Migration 006: Starting migration for driver: {$driver}");

        // postsテーブルにsort_orderカラムを追加
        if ($helper->addColumnIfNotExists($db, 'posts', 'sort_order', "{$intType} DEFAULT 0 NOT NULL")) {
            Logger::getInstance()->info("Migration 006: Added sort_order column to posts table");
        }

        // group_postsテーブルにsort_orderカラムを追加
        if ($helper->addColumnIfNotExists($db, 'group_posts', 'sort_order', "{$intType} DEFAULT 0 NOT NULL")) {
            Logger::getInstance()->info("Migration 006: Added sort_order column to group_posts table");
        }

        // インデックスを追加（ソート性能向上）
        if ($helper->addIndexIfNotExists($db, 'posts', 'idx_posts_sort_order', 'sort_order DESC, created_at DESC')) {
            Logger::getInstance()->info("Migration 006: Created index idx_posts_sort_order");
        }

        if ($helper->addIndexIfNotExists($db, 'group_posts', 'idx_group_posts_sort_order', 'sort_order DESC, created_at DESC')) {
            Logger::getInstance()->info("Migration 006: Created index idx_group_posts_sort_order");
        }

        Logger::getInstance()->info("Migration 006: Migration completed successfully");
    }
];
