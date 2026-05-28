<?php
/**
 * Migration 010: Add description and tags columns to paint table (was paint)
 */

use App\Utils\Logger;

return [
    'name' => 'add_description_tags_to_paint',

    'up' => function(PDO $db) {
        $helper = new \App\Database\MigrationHelper();

    // descriptionカラムを追加
    $helper->addColumnIfNotExists($db, 'paint', 'description', 'TEXT');

    // tagsカラムを追加
    $helper->addColumnIfNotExists($db, 'paint', 'tags', 'TEXT');

        return true;
    },

    'down' => function(PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;

        // カラム削除（SQLiteではサポートされていない場合があるので注意）
        try {
            $db->exec("ALTER TABLE paint DROP COLUMN description");
        } catch (Exception $e) {
            Logger::getInstance()->error("Migration down: Could not drop description column: " . $e->getMessage());
        }

        try {
            $db->exec("ALTER TABLE paint DROP COLUMN tags");
        } catch (Exception $e) {
            Logger::getInstance()->error("Migration down: Could not drop tags column: " . $e->getMessage());
        }

        return true;
    }
];
