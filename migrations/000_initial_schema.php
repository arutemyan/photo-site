<?php

/**
 * Migration 000: initial schema
 *
 * This migration bootstraps the database schema previously created by
 * Connection::initializeSchema(). It is idempotent and uses helper functions
 * for DB-specific types.
 */

return [
    'name' => 'initial_schema',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;

        $autoInc = $helper::getAutoIncrement($db);
        $intType = $helper::getIntegerType($db);
        $textType = $helper::getTextType($db);
        $shortText = $helper::getTextType($db, 191);
        $datetimeType = $helper::getDateTimeType($db);
        $timestampType = $helper::getTimestampType($db);
        $currentTimestamp = $helper::getCurrentTimestamp($db);

        // users
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id {$autoInc},
                username {$shortText} NOT NULL UNIQUE,
                password_hash {$textType} NOT NULL,
                created_at {$datetimeType} DEFAULT {$currentTimestamp}
            )
        ");

        // posts
        $db->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id {$autoInc},
                title {$textType} NOT NULL,
                tags {$textType},
                detail {$textType},
                image_path {$textType},
                thumb_path {$textType},
                is_sensitive {$intType} DEFAULT 0,
                is_visible {$intType} NOT NULL DEFAULT 1,
                created_at {$datetimeType} DEFAULT {$currentTimestamp}
            )
        ");

        \App\Database\DatabaseHelper::createIndexIfNotExists($db, 'idx_posts_created_at', 'posts', 'created_at DESC');
        \App\Database\DatabaseHelper::createIndexIfNotExists($db, 'idx_posts_visible', 'posts', 'is_visible, created_at DESC');

        // tags
        $db->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id {$autoInc},
                name {$shortText} NOT NULL UNIQUE,
                created_at {$timestampType} DEFAULT {$currentTimestamp}
            )
        ");

        \App\Database\DatabaseHelper::createIndexIfNotExists($db, 'idx_tags_name', 'tags', 'name');

        // migrations
        $db->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                version {$intType} PRIMARY KEY,
                name {$textType} NOT NULL,
                executed_at {$timestampType} DEFAULT {$currentTimestamp}
            )
        ");

        // settings
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id {$autoInc},
                setting_key {$shortText} NOT NULL UNIQUE,
                setting_value {$textType} NOT NULL,
                updated_at {$timestampType} DEFAULT {$currentTimestamp}
            )
        ");

        // themes
        $db->exec("
            CREATE TABLE IF NOT EXISTS themes (
                id {$autoInc},
                header_html {$textType},
                footer_html {$textType},
                site_title {$shortText} DEFAULT 'イラストポートフォリオ',
                site_subtitle {$shortText} DEFAULT 'Illustration Portfolio',
                site_description {$shortText} DEFAULT 'イラストレーターのポートフォリオサイト',
                primary_color {$shortText} DEFAULT '#8B5AFA',
                secondary_color {$shortText} DEFAULT '#667eea',
                accent_color {$shortText} DEFAULT '#FFD700',
                background_color {$shortText} DEFAULT '#1a1a1a',
                text_color {$shortText} DEFAULT '#ffffff',
                heading_color {$shortText} DEFAULT '#ffffff',
                footer_bg_color {$shortText} DEFAULT '#2a2a2a',
                footer_text_color {$shortText} DEFAULT '#cccccc',
                card_border_color {$shortText} DEFAULT '#333333',
                card_bg_color {$shortText} DEFAULT '#252525',
                card_shadow_opacity {$shortText} DEFAULT '0.3',
                link_color {$shortText} DEFAULT '#8B5AFA',
                link_hover_color {$shortText} DEFAULT '#a177ff',
                tag_bg_color {$shortText} DEFAULT '#8B5AFA',
                tag_text_color {$shortText} DEFAULT '#ffffff',
                filter_active_bg_color {$shortText} DEFAULT '#8B5AFA',
                filter_active_text_color {$shortText} DEFAULT '#ffffff',
                header_image {$shortText},
                logo_image {$shortText},
                updated_at {$datetimeType} DEFAULT {$currentTimestamp}
            )
        ");

        // view_counts (MySQL/Postgres only)
        $driver = $helper::getDriver($db);
        if ($driver !== 'sqlite') {
            $db->exec("
                CREATE TABLE IF NOT EXISTS view_counts (
                    post_id {$intType} NOT NULL,
                    post_type {$intType} DEFAULT 0 NOT NULL,
                    count {$intType} DEFAULT 0,
                    updated_at {$datetimeType} DEFAULT {$currentTimestamp},
                    PRIMARY KEY (post_id, post_type)
                )
            ");
            \App\Database\DatabaseHelper::createIndexIfNotExists($db, 'idx_view_counts_updated', 'view_counts', 'updated_at DESC');
        }

        // insert default theme row if empty
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM themes");
            $result = $stmt->fetch();
            if (
                !$result ||
                !isset($result['count']) ||
                (int)$result['count'] === 0
            ) {
                $db->exec("INSERT INTO themes (header_html, footer_html) VALUES ('', '')");
            }
        } catch (PDOException $e) {
            // ignore: if themes table doesn't exist or query fails, leave it to later migrations
        }
    }
];
