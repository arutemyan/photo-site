<?php

require_once __DIR__ . '/../../../src/Utils/Logger.php';

/**
 * マイグレーション 004: ナビゲーション設定（一覧に戻るボタン）を追加
 *
 * - themesテーブルにback_button_text, back_button_bg_color, back_button_text_colorカラムを追加
 */

use App\Utils\Logger;

return [
    'name' => 'add_back_button_settings',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);
        $textType = $helper::getTextType($db);

        // themesテーブルに一覧に戻るボタンの設定カラムを追加
        try {
            $db->exec("ALTER TABLE themes ADD COLUMN back_button_text {$textType} DEFAULT '一覧に戻る'");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                Logger::getInstance()->warning("Migration 004 back_button_text error: " . $e->getMessage());
            }
        }

        try {
            $db->exec("ALTER TABLE themes ADD COLUMN back_button_bg_color {$textType} DEFAULT '#8B5AFA'");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                Logger::getInstance()->warning("Migration 004 back_button_bg_color error: " . $e->getMessage());
            }
        }

        try {
            $db->exec("ALTER TABLE themes ADD COLUMN back_button_text_color {$textType} DEFAULT '#FFFFFF'");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false) {
                Logger::getInstance()->warning("Migration 004 back_button_text_color error: " . $e->getMessage());
            }
        }

        Logger::getInstance()->info("Migration 004: Added back_button settings columns to themes table");
    }
];
