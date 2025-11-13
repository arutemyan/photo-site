<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

/**
 * 設定モデルクラス
 *
 * サイト設定のCRUD操作を管理
 */
class Setting
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * 設定値を取得
     *
     * @param string $key 設定キー
     * @param string $default デフォルト値
     * @return string 設定値、存在しない場合はデフォルト値
     */
    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    }

    /**
     * 設定値を保存（既存の場合は更新）
     *
     * @param string $key 設定キー
     * @param string $value 設定値
     * @return bool 成功した場合true
     */
    public function set(string $key, string $value): bool
    {
        // DatabaseHelperを使用してUPSERT SQLを生成
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($this->db);

        if ($driver === 'mysql') {
            // MySQLの場合
            $stmt = $this->db->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$key, $value]);
        } else {
            // SQLite/PostgreSQLの場合
            $stmt = $this->db->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$key, $value]);
        }
    }

    /**
     * すべての設定を取得
     *
     * @return array 設定データの配列
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
        return $stmt->fetchAll();
    }
}
