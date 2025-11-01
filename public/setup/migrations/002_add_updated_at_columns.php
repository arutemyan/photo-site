<?php

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
        // postsテーブルにupdated_atカラムを追加
        // カラムが既に存在するかチェック
        $stmt = $db->query("PRAGMA table_info(posts)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasUpdatedAt = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'updated_at') {
                $hasUpdatedAt = true;
                break;
            }
        }

        if (!$hasUpdatedAt) {
            // updated_atカラムを追加
            $db->exec("ALTER TABLE posts ADD COLUMN updated_at DATETIME");

            // 既存のレコードのupdated_atをcreated_atと同じ値に設定
            $db->exec("UPDATE posts SET updated_at = created_at");
        }

        // group_postsテーブルにupdated_atカラムを追加（テーブルが存在する場合）
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='group_posts'");
        $groupPostsExists = $stmt->fetch();

        if ($groupPostsExists) {
            $stmt = $db->query("PRAGMA table_info(group_posts)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasUpdatedAt = false;

            foreach ($columns as $column) {
                if ($column['name'] === 'updated_at') {
                    $hasUpdatedAt = true;
                    break;
                }
            }

            if (!$hasUpdatedAt) {
                // updated_atカラムを追加
                $db->exec("ALTER TABLE group_posts ADD COLUMN updated_at DATETIME");

                // 既存のレコードのupdated_atをcreated_atと同じ値に設定
                $db->exec("UPDATE group_posts SET updated_at = created_at");
            }
        }
    }
];
