<?php

/**
 * マイグレーション 012: paint テーブルに artist_name カラムを追加
 */

return [
    'name' => 'add_artist_name_to_paint',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        $textType = $helper::getTextType($db, 191);

        if ($driver === 'sqlite') {
            // SQLite supports ADD COLUMN
            $db->exec("ALTER TABLE paint ADD COLUMN artist_name TEXT");
        } else {
            // MySQL / MariaDB / others
            $db->exec("ALTER TABLE paint ADD COLUMN artist_name VARCHAR(191)");
        }
    }
];
