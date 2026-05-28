<?php


/**
 * マイグレーション 016: 詳細表示ボタン設定を追加
 *
 * - themesテーブルにdetail_button_text, detail_button_bg_color, detail_button_text_colorカラムを追加
 */

use App\Utils\Logger;

return [
    'name' => 'add_detail_button_settings',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        $textType = $helper::getTextType($db);
        $shortText = $helper::getTextType($db, 191);

        try {
            $db->exec("ALTER TABLE themes ADD COLUMN detail_button_text {$shortText} DEFAULT '詳細表示'");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                Logger::getInstance()->warning("Migration 016 detail_button_text error: " . $e->getMessage());
            }
        }

        try {
            $db->exec("ALTER TABLE themes ADD COLUMN detail_button_bg_color {$shortText} DEFAULT '#8B5AFA'");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                Logger::getInstance()->warning("Migration 016 detail_button_bg_color error: " . $e->getMessage());
            }
        }

        try {
            $db->exec("ALTER TABLE themes ADD COLUMN detail_button_text_color {$shortText} DEFAULT '#FFFFFF'");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                Logger::getInstance()->warning("Migration 016 detail_button_text_color error: " . $e->getMessage());
            }
        }

        Logger::getInstance()->info("Migration 016: Added detail_button settings columns to themes table");
    }
];
