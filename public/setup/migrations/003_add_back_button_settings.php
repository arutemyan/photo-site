<?php

/**
 * マイグレーション 003: ナビゲーション設定（一覧に戻るボタン）を追加
 *
 * - themesテーブルにback_button_text, back_button_bg_color, back_button_text_colorカラムを追加
 */

return [
    'name' => 'add_back_button_settings',

    'up' => function (PDO $db) {
        // themesテーブルに一覧に戻るボタンの設定カラムを追加
        $db->exec("ALTER TABLE themes ADD COLUMN back_button_text TEXT DEFAULT '一覧に戻る'");
        $db->exec("ALTER TABLE themes ADD COLUMN back_button_bg_color TEXT DEFAULT '#8B5AFA'");
        $db->exec("ALTER TABLE themes ADD COLUMN back_button_text_color TEXT DEFAULT '#FFFFFF'");

        error_log("Migration 003: Added back_button settings columns to themes table");
    }
];
