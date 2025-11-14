<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use App\Utils\Logger;
use PDO;

/**
 * ユーザーモデルクラス
 *
 * 管理者認証用のユーザー管理
 */
class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * ユーザー名とパスワードで認証
     *
     * @param string $username ユーザー名
     * @param string $password パスワード（平文）
     * @return array|null ユーザー情報、認証失敗時はnull
     */
    public function authenticate(string $username, string $password): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, username, password_hash
            FROM users
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        // タイミング攻撃対策: 常にpassword_verify()を実行
        // 空のパスワードハッシュの場合は無効なハッシュを使用
        $hash = !empty($user['password_hash']) ? $user['password_hash'] : '$2y$10$invalidhashxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
        $isValid = password_verify($password, $hash);

        // 空のパスワードハッシュの場合は警告ログを記録
        if (empty($user['password_hash'])) {
            Logger::getInstance()->warning('Login attempt with empty password hash for user: ' . $username);
            return null;
        }

        // パスワード検証失敗
        if (!$isValid) {
            return null;
        }

        // password_hashを除外して返す
        unset($user['password_hash']);
        return $user;
    }

    /**
     * パスワードを更新
     *
     * @param int $userId ユーザーID
     * @param string $newPassword 新しいパスワード（平文）
     * @return bool 成功した場合true
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            UPDATE users
            SET password_hash = ?
            WHERE id = ?
        ");

        return $stmt->execute([$passwordHash, $userId]);
    }

    /**
     * ユーザーIDでユーザーを取得
     *
     * @param int $id ユーザーID
     * @return array|null ユーザー情報、存在しない場合はnull
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, username, created_at
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }
}
