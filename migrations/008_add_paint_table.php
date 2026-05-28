<?php

/**
 * マイグレーション 008: お絵描き機能用paintテーブル追加
 *
 * - paintテーブルを作成
 * - 必要なインデックスを追加
 */

return [
    'name' => 'add_paint_table',

    'up' => function (PDO $db) {
        // DB種別を取得
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        $intType = $helper::getIntegerType($db);
        $textType = $helper::getTextType($db);
        $shortText = $helper::getTextType($db, 191);
        $timestampType = $helper::getTimestampType($db);
        $auto = $helper::getAutoIncrement($db);

        // paintテーブル作成 (旧: paint)
        $db->exec(
            "CREATE TABLE IF NOT EXISTS paint (\n" .
            "                id {$auto},\n" .
            "                user_id {$intType} NOT NULL,\n" .
            "                title {$shortText} NOT NULL DEFAULT '',\n" .
            "                canvas_width {$intType} NOT NULL DEFAULT 800,\n" .
            "                canvas_height {$intType} NOT NULL DEFAULT 600,\n" .
            "                background_color {$shortText} DEFAULT '#FFFFFF',\n" .
            "                data_path {$textType},\n" .
            "                image_path {$textType},\n" .
            "                thumbnail_path {$textType},\n" .
            "                timelapse_path {$textType},\n" .
            "                timelapse_size {$intType} DEFAULT 0,\n" .
            "                file_size {$intType} DEFAULT 0,\n" .
            "                status {$shortText} DEFAULT 'draft' CHECK (status IN ('draft', 'published')),\n" .
            "                created_at {$timestampType} DEFAULT CURRENT_TIMESTAMP,\n" .
            "                updated_at {$timestampType} DEFAULT CURRENT_TIMESTAMP,\n" .
            "                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n" .
            ")"
        );

        // インデックス作成 (use MigrationHelper for cross-DB compatibility)
        $mhelper = new \App\Database\MigrationHelper();
        $mhelper->addIndexIfNotExists($db, 'paint', 'idx_paint_user_id', 'user_id');
        $mhelper->addIndexIfNotExists($db, 'paint', 'idx_paint_status', 'status');
        $mhelper->addIndexIfNotExists($db, 'paint', 'idx_paint_created_at', 'created_at');
    }
];
