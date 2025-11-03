<?php

/**
 * マイグレーション 008: お絵描き機能用illustsテーブル追加
 *
 * - illustsテーブルを作成
 * - 必要なインデックスを追加
 */

return [
    'name' => 'add_illusts_table',

    'up' => function (PDO $db) {
        // DB種別を取得
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        $intType = $helper::getIntegerType($db);
        $textType = $helper::getTextType($db);

        // illustsテーブル作成
        $db->exec("
            CREATE TABLE IF NOT EXISTS illusts (
                id {$intType} PRIMARY KEY " . ($driver === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT') . ",
                user_id {$intType} NOT NULL,
                title {$textType} NOT NULL DEFAULT '',
                canvas_width {$intType} NOT NULL DEFAULT 800,
                canvas_height {$intType} NOT NULL DEFAULT 600,
                background_color {$textType} DEFAULT '#FFFFFF',
                data_path {$textType},
                image_path {$textType},
                thumbnail_path {$textType},
                timelapse_path {$textType},
                timelapse_size {$intType} DEFAULT 0,
                file_size {$intType} DEFAULT 0,
                status {$textType} DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
                created_at {$textType} DEFAULT CURRENT_TIMESTAMP,
                updated_at {$textType} DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // インデックス作成
        $db->exec("CREATE INDEX IF NOT EXISTS idx_illusts_user_id ON illusts(user_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_illusts_status ON illusts(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_illusts_created_at ON illusts(created_at)");
    }
];