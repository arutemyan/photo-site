<?php

/**
 * マイグレーション 011: paint テーブルに nsfw と is_visible カラムを追加
 */

return [
    'name' => 'add_paint_flags',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        // Use integer-like columns for boolean flags depending on driver
        if ($driver === 'sqlite') {
            // SQLite supports ADD COLUMN
            $db->exec("ALTER TABLE paint ADD COLUMN nsfw INTEGER DEFAULT 0");
            $db->exec("ALTER TABLE paint ADD COLUMN is_visible INTEGER DEFAULT 1");
        } elseif ($driver === 'mysql') {
            // MySQL / MariaDB: use TINYINT
            $db->exec("ALTER TABLE paint ADD COLUMN nsfw TINYINT(1) DEFAULT 0");
            $db->exec("ALTER TABLE paint ADD COLUMN is_visible TINYINT(1) DEFAULT 1");
        } else {
            // PostgreSQL and other drivers: use INTEGER (Postgres has no TINYINT)
            $db->exec("ALTER TABLE paint ADD COLUMN nsfw INTEGER DEFAULT 0");
            $db->exec("ALTER TABLE paint ADD COLUMN is_visible INTEGER DEFAULT 1");
        }
    }
];
