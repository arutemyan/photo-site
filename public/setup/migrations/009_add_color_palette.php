<?php
/**
 * Migration 009: Add color palette table
 */

return [
    'name' => 'add_color_palette',

    'up' => function(PDO $db) {
        // DB種別を取得
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        $intType = $helper::getIntegerType($db);
        $textType = $helper::getTextType($db);
        $auto = $helper::getAutoIncrement($db);

        // color_palettesテーブル作成
        $db->exec("
            CREATE TABLE IF NOT EXISTS color_palettes (
                id {$auto},
                user_id {$intType} DEFAULT NULL,
                slot_index {$intType} NOT NULL,
                color {$textType} NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP" .
                ($driver === 'sqlite' ? '' : ' ON UPDATE CURRENT_TIMESTAMP') . ",
                UNIQUE(user_id, slot_index)
            )
        ");
        
        // Insert default 16 colors
        $defaultColors = [
            '#000000', '#FFFFFF', '#FF0000', '#00FF00',
            '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF',
            '#800000', '#008000', '#000080', '#808000',
            '#800080', '#008080', '#C0C0C0', '#808080'
        ];
        
        // DBごとに重複挿入を無視する構文が異なるため分岐
        if ($driver === 'sqlite') {
            $sql = "INSERT OR IGNORE INTO color_palettes (user_id, slot_index, color) VALUES (NULL, ?, ?)";
        } elseif ($driver === 'mysql') {
            $sql = "INSERT IGNORE INTO color_palettes (user_id, slot_index, color) VALUES (NULL, ?, ?)";
        } elseif ($driver === 'postgresql') {
            // PostgreSQL は ON CONFLICT を使う
            $sql = "INSERT INTO color_palettes (user_id, slot_index, color) VALUES (NULL, ?, ?) ON CONFLICT (user_id, slot_index) DO NOTHING";
        } else {
            // デフォルトは普通の INSERT（失敗した場合は例外化される）
            $sql = "INSERT INTO color_palettes (user_id, slot_index, color) VALUES (NULL, ?, ?)";
        }

        $stmt = $db->prepare($sql);

        foreach ($defaultColors as $index => $color) {
            $stmt->execute([$index, $color]);
        }
        
        return true;
    },
    
    'down' => function(PDO $db) {
        $db->exec("DROP TABLE IF EXISTS color_palettes");
        return true;
    }
];
