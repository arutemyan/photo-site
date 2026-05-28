<?php

/**
 * マイグレーション 013: paint テーブルに timelapse_size カラムを追加
 *
 * Note: 既存データのtimelapse_sizeを更新する場合は、
 * ルートディレクトリの update_timelapse_sizes.php を実行してください
 */

return [
    'name' => 'add_timelapse_size_to_paint',

    'up' => function (PDO $db) {
        // Use MigrationHelper to add column idempotently
        $mhelper = new \App\Database\MigrationHelper();
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);

        if ($driver === 'sqlite') {
            $definition = 'INTEGER DEFAULT 0';
        } else {
            $definition = 'INT DEFAULT 0';
        }

        // addColumnIfNotExists will skip if column already present
        $mhelper->addColumnIfNotExists($db, 'paint', 'timelapse_size', $definition);
    }
];
