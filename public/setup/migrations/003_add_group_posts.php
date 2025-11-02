<?php

/**
 * マイグレーション 003: グループ投稿機能の追加
 *
 * - group_postsテーブルを作成
 * - group_post_imagesテーブルを作成
 * - post_tagsテーブルを作成（投稿とタグの中間テーブル）
 */

return [
    'name' => 'add_group_posts',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);

        $autoInc = $helper::getAutoIncrement($db);
        $intType = $helper::getIntegerType($db);
        $textType = $helper::getTextType($db);
        $datetimeType = $helper::getDateTimeType($db);
        $currentTimestamp = $helper::getCurrentTimestamp($db);

        // group_postsテーブルを作成
        $db->exec("
            CREATE TABLE IF NOT EXISTS group_posts (
                id {$autoInc},
                title {$textType} NOT NULL,
                detail {$textType},
                is_sensitive {$intType} DEFAULT 0,
                is_visible {$intType} DEFAULT 1,
                created_at {$datetimeType} DEFAULT {$currentTimestamp},
                updated_at {$datetimeType} DEFAULT {$currentTimestamp},
                tag1 {$intType},
                tag2 {$intType},
                tag3 {$intType},
                tag4 {$intType},
                tag5 {$intType},
                tag6 {$intType},
                tag7 {$intType},
                tag8 {$intType},
                tag9 {$intType},
                tag10 {$intType}
            )
        ");

        // group_postsのインデックス
        $db->exec("CREATE INDEX IF NOT EXISTS idx_group_posts_visible ON group_posts(is_visible, created_at DESC)");

        // group_post_imagesテーブルを作成
        $db->exec("
            CREATE TABLE IF NOT EXISTS group_post_images (
                id {$autoInc},
                group_post_id {$intType} NOT NULL,
                image_path {$textType} NOT NULL,
                thumb_path {$textType},
                display_order {$intType} DEFAULT 0,
                created_at {$datetimeType} DEFAULT {$currentTimestamp}
            )
        ");

        // 外部キー制約（MySQLとPostgreSQLのみ）
        if ($driver === 'mysql' || $driver === 'postgresql') {
            try {
                $db->exec("
                    ALTER TABLE group_post_images
                    ADD CONSTRAINT fk_group_post_images_group_id
                    FOREIGN KEY (group_post_id) REFERENCES group_posts(id) ON DELETE CASCADE
                ");
            } catch (PDOException $e) {
                // 既に存在する場合はスキップ
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    error_log("Migration 003 FK error: " . $e->getMessage());
                }
            }
        }

        // group_post_imagesのインデックス
        $db->exec("CREATE INDEX IF NOT EXISTS idx_group_post_images_group_id ON group_post_images(group_post_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_group_post_images_order ON group_post_images(group_post_id, display_order)");

        // post_tagsテーブルを作成（投稿とタグの中間テーブル）
        $db->exec("
            CREATE TABLE IF NOT EXISTS post_tags (
                post_id {$intType} NOT NULL,
                tag_id {$intType} NOT NULL,
                PRIMARY KEY (post_id, tag_id)
            )
        ");

        // 外部キー制約（MySQLとPostgreSQLのみ）
        if ($driver === 'mysql' || $driver === 'postgresql') {
            try {
                $db->exec("
                    ALTER TABLE post_tags
                    ADD CONSTRAINT fk_post_tags_post_id
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
                ");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    error_log("Migration 003 FK post_tags post_id error: " . $e->getMessage());
                }
            }

            try {
                $db->exec("
                    ALTER TABLE post_tags
                    ADD CONSTRAINT fk_post_tags_tag_id
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
                ");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    error_log("Migration 003 FK post_tags tag_id error: " . $e->getMessage());
                }
            }
        }

        // post_tagsのインデックス
        $db->exec("CREATE INDEX IF NOT EXISTS idx_post_tags_post_id ON post_tags(post_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_post_tags_tag_id ON post_tags(tag_id)");

        // postsテーブルにgroup_idとdisplay_orderカラムを追加
        try {
            $db->exec("ALTER TABLE posts ADD COLUMN group_id {$intType} DEFAULT NULL");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                error_log("Migration 003 group_id error: " . $e->getMessage());
            }
        }

        try {
            $db->exec("ALTER TABLE posts ADD COLUMN display_order {$intType} DEFAULT 0");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                error_log("Migration 003 display_order error: " . $e->getMessage());
            }
        }

        // group_idのインデックス
        $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_group_id ON posts(group_id)");

        error_log("Migration 003: Added group posts tables and columns");
    }
];
