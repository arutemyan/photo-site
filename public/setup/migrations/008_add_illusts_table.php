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
        $auto = $helper::getAutoIncrement($db);

        // paintテーブル作成 (旧: paint)
        $db->exec(
            "CREATE TABLE IF NOT EXISTS paint (\n" .
            "                id {$auto},\n" .
            "                user_id {$intType} NOT NULL,\n" .
            "                title {$textType} NOT NULL DEFAULT '',\n" .
            "                canvas_width {$intType} NOT NULL DEFAULT 800,\n" .
            "                canvas_height {$intType} NOT NULL DEFAULT 600,\n" .
            "                background_color {$textType} DEFAULT '#FFFFFF',\n" .
            "                data_path {$textType},\n" .
            "                image_path {$textType},\n" .
            "                thumbnail_path {$textType},\n" .
            "                timelapse_path {$textType},\n" .
            "                timelapse_size {$intType} DEFAULT 0,\n" .
            "                file_size {$intType} DEFAULT 0,\n" .
            "                status {$textType} DEFAULT 'draft' CHECK (status IN ('draft', 'published')),\n" .
            "                created_at {$textType} DEFAULT CURRENT_TIMESTAMP,\n" .
            "                updated_at {$textType} DEFAULT CURRENT_TIMESTAMP,\n" .
            "\n" .
            "                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n" .
            ")"
        );

        // インデックス作成
        $db->exec("CREATE INDEX IF NOT EXISTS idx_paint_user_id ON paint(user_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_paint_status ON paint(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_paint_created_at ON paint(created_at)");
    }
];