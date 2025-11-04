<?php

require_once __DIR__ . '/../../../src/Utils/Logger.php';

/**
 * マイグレーション 002: updated_atカラムの追加
 *
 * - postsテーブルにupdated_atカラムを追加
 * - group_postsテーブルにupdated_atカラムを追加（存在する場合）
 * - 既存レコードはupdated_at = created_atで初期化
 */

return [
    'name' => 'add_updated_at_columns',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        $datetimeType = $helper::getDateTimeType($db);

        // postsテーブルにupdated_atカラムを追加
        try {
            $db->exec("ALTER TABLE posts ADD COLUMN updated_at {$datetimeType}");
            // 既存のレコードのupdated_atをcreated_atと同じ値に設定
            $db->exec("UPDATE posts SET updated_at = created_at WHERE updated_at IS NULL");
        } catch (PDOException $e) {
            // カラムが既に存在する場合はスキップ
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                // それ以外のエラーはスロー
                Logger::getInstance()->error("Migration 002 posts error: " . $e->getMessage());
            }
        }

        // group_postsテーブルにupdated_atカラムを追加（テーブルが存在する場合）
        // テーブルの存在確認（DB非依存）
        try {
            $stmt = $db->query("SELECT 1 FROM group_posts LIMIT 1");
            $groupPostsExists = true;
        } catch (PDOException $e) {
            $groupPostsExists = false;
        }

        if ($groupPostsExists) {
            try {
                $db->exec("ALTER TABLE group_posts ADD COLUMN updated_at {$datetimeType}");
                // 既存のレコードのupdated_atをcreated_atと同じ値に設定
                $db->exec("UPDATE group_posts SET updated_at = created_at WHERE updated_at IS NULL");
            } catch (PDOException $e) {
                // カラムが既に存在する場合はスキップ
                if (strpos($e->getMessage(), 'Duplicate column') === false &&
                    strpos($e->getMessage(), 'already exists') === false) {
                    Logger::getInstance()->error("Migration 002 group_posts error: " . $e->getMessage());
                }
            }
        }
    }
];
