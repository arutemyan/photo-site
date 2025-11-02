<?php

/**
 * マイグレーション 006: sort_orderカラムの追加
 *
 * - postsテーブルにsort_orderカラムを追加
 * - group_postsテーブルにsort_orderカラムを追加
 * - デフォルト値は0（通常の作成日時順）
 * - プラス値：優先度アップ（前方表示）
 * - マイナス値：優先度ダウン（後方表示）
 */

return [
    'name' => 'add_sort_order',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $intType = $helper::getIntegerType($db);

        // postsテーブルにsort_orderカラムを追加
        try {
            $db->exec("ALTER TABLE posts ADD COLUMN sort_order {$intType} DEFAULT 0 NOT NULL");
            error_log("Migration 006: Added sort_order column to posts table");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                error_log("Migration 006 posts.sort_order error: " . $e->getMessage());
                throw $e;
            }
        }

        // group_postsテーブルにsort_orderカラムを追加
        try {
            $db->exec("ALTER TABLE group_posts ADD COLUMN sort_order {$intType} DEFAULT 0 NOT NULL");
            error_log("Migration 006: Added sort_order column to group_posts table");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                error_log("Migration 006 group_posts.sort_order error: " . $e->getMessage());
                throw $e;
            }
        }

        // インデックスを追加（ソート性能向上）
        try {
            $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_sort_order ON posts(sort_order DESC, created_at DESC)");
        } catch (PDOException $e) {
            error_log("Migration 006 posts index error: " . $e->getMessage());
        }

        try {
            $db->exec("CREATE INDEX IF NOT EXISTS idx_group_posts_sort_order ON group_posts(sort_order DESC, created_at DESC)");
        } catch (PDOException $e) {
            error_log("Migration 006 group_posts index error: " . $e->getMessage());
        }

        error_log("Migration 006: Successfully added sort_order columns");
    }
];
