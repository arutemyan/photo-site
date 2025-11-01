<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

/**
 * テーマモデルクラス
 *
 * サイトテーマのカスタマイズ管理
 */
class Theme
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * 現在のテーマ設定を取得
     *
     * @return array テーマ設定
     */
    public function getCurrent(): array
    {
        $stmt = $this->db->query("
            SELECT
                header_html,
                footer_html,
                site_title,
                site_subtitle,
                site_description,
                primary_color,
                secondary_color,
                accent_color,
                background_color,
                text_color,
                heading_color,
                header_image,
                logo_image,
                footer_bg_color,
                footer_text_color,
                card_border_color,
                card_bg_color,
                card_shadow_opacity,
                link_color,
                link_hover_color,
                tag_bg_color,
                tag_text_color,
                filter_active_bg_color,
                filter_active_text_color,
                back_button_text,
                back_button_bg_color,
                back_button_text_color,
                updated_at
            FROM themes
            ORDER BY id DESC
            LIMIT 1
        ");
        $result = $stmt->fetch();

        if ($result === false) {
            return [
                'header_html' => '',
                'footer_html' => '',
                'site_title' => 'イラストポートフォリオ',
                'site_subtitle' => 'Illustration Portfolio',
                'site_description' => 'イラストレーターのポートフォリオサイト',
                'primary_color' => '#8B5AFA',
                'secondary_color' => '#667eea',
                'accent_color' => '#FFD700',
                'background_color' => '#1a1a1a',
                'text_color' => '#ffffff',
                'heading_color' => '#ffffff',
                'header_image' => null,
                'logo_image' => null,
                'footer_bg_color' => '#2a2a2a',
                'footer_text_color' => '#cccccc',
                'card_border_color' => '#333333',
                'card_bg_color' => '#252525',
                'card_shadow_opacity' => '0.3',
                'link_color' => '#8B5AFA',
                'link_hover_color' => '#a177ff',
                'tag_bg_color' => '#8B5AFA',
                'tag_text_color' => '#ffffff',
                'filter_active_bg_color' => '#8B5AFA',
                'filter_active_text_color' => '#ffffff',
                'back_button_text' => '一覧に戻る',
                'back_button_bg_color' => '#8B5AFA',
                'back_button_text_color' => '#FFFFFF',
                'updated_at' => null
            ];
        }

        return $result;
    }

    /**
     * テーマ設定を更新
     *
     * @param array $data 更新するデータ
     * @return bool 成功した場合true
     */
    public function update(array $data): bool
    {
        $fields = [];
        $values = [];

        // 更新可能なフィールド
        $allowedFields = [
            'header_html', 'footer_html', 'site_title', 'site_subtitle',
            'site_description', 'primary_color', 'secondary_color',
            'accent_color', 'background_color', 'text_color', 'heading_color',
            'header_image', 'logo_image',
            'footer_bg_color', 'footer_text_color', 'card_border_color',
            'card_bg_color', 'card_shadow_opacity', 'link_color', 'link_hover_color',
            'tag_bg_color', 'tag_text_color', 'filter_active_bg_color', 'filter_active_text_color',
            'back_button_text', 'back_button_bg_color', 'back_button_text_color'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";

        $sql = "UPDATE themes SET " . implode(', ', $fields) .
               " WHERE id = (SELECT id FROM themes ORDER BY id ASC LIMIT 1)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * 画像を更新（ヘッダー画像またはロゴ）
     *
     * @param string $field フィールド名（header_image または logo_image）
     * @param string|null $path 画像パス
     * @return bool 成功した場合true
     */
    public function updateImage(string $field, ?string $path): bool
    {
        // フィールド名のホワイトリストマッピング（SQLインジェクション対策）
        $allowedFields = [
            'header_image' => 'header_image',
            'logo_image' => 'logo_image'
        ];

        if (!isset($allowedFields[$field])) {
            return false;
        }

        // マッピングされた安全なフィールド名を使用
        $safeField = $allowedFields[$field];

        $stmt = $this->db->prepare("
            UPDATE themes
            SET {$safeField} = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = (SELECT id FROM themes ORDER BY id ASC LIMIT 1)
        ");

        return $stmt->execute([$path]);
    }
}
