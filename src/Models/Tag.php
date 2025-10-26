<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

/**
 * タグモデルクラス
 *
 * タグデータのCRUD操作を管理
 */
class Tag
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * すべてのタグを取得（投稿数付き）
     * 表示中の投稿（is_visible=1）のみをカウント
     *
     * @return array タグデータの配列
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT t.id, t.name, COUNT(DISTINCT CASE WHEN p.is_visible = 1 THEN pt.post_id END) as post_count
            FROM tags t
            LEFT JOIN post_tags pt ON t.id = pt.tag_id
            LEFT JOIN posts p ON pt.post_id = p.id
            GROUP BY t.id
            ORDER BY post_count DESC, t.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 人気のタグを取得（投稿数が多い順）
     * 表示中の投稿（is_visible=1）のみをカウント
     *
     * @param int $limit 取得件数（デフォルト: 10）
     * @return array タグデータの配列
     */
    public function getPopular(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, COUNT(DISTINCT CASE WHEN p.is_visible = 1 THEN pt.post_id END) as post_count
            FROM tags t
            LEFT JOIN post_tags pt ON t.id = pt.tag_id
            LEFT JOIN posts p ON pt.post_id = p.id
            GROUP BY t.id
            HAVING post_count > 0
            ORDER BY post_count DESC, t.name ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * タグ名でタグを検索（部分一致）
     * 表示中の投稿（is_visible=1）のみをカウント
     *
     * @param string $name 検索する名前
     * @return array タグデータの配列
     */
    public function searchByName(string $name): array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, COUNT(DISTINCT CASE WHEN p.is_visible = 1 THEN pt.post_id END) as post_count
            FROM tags t
            LEFT JOIN post_tags pt ON t.id = pt.tag_id
            LEFT JOIN posts p ON pt.post_id = p.id
            WHERE t.name LIKE ?
            GROUP BY t.id
            ORDER BY post_count DESC, t.name ASC
        ");
        $stmt->execute(['%' . $name . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * タグIDでタグを取得
     * 表示中の投稿（is_visible=1）のみをカウント
     *
     * @param int $id タグID
     * @return array|null タグデータ、存在しない場合はnull
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, COUNT(DISTINCT CASE WHEN p.is_visible = 1 THEN pt.post_id END) as post_count
            FROM tags t
            LEFT JOIN post_tags pt ON t.id = pt.tag_id
            LEFT JOIN posts p ON pt.post_id = p.id
            WHERE t.id = ?
            GROUP BY t.id
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * タグ名でタグを取得（完全一致）
     * 表示中の投稿（is_visible=1）のみをカウント
     *
     * @param string $name タグ名
     * @return array|null タグデータ、存在しない場合はnull
     */
    public function getByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, COUNT(DISTINCT CASE WHEN p.is_visible = 1 THEN pt.post_id END) as post_count
            FROM tags t
            LEFT JOIN post_tags pt ON t.id = pt.tag_id
            LEFT JOIN posts p ON pt.post_id = p.id
            WHERE t.name = ?
            GROUP BY t.id
        ");
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * 新しいタグを作成
     *
     * @param string $name タグ名
     * @return int 作成されたタグのID
     * @throws \PDOException タグが既に存在する場合
     */
    public function create(string $name): int
    {
        $stmt = $this->db->prepare("INSERT INTO tags (name) VALUES (?)");
        $stmt->execute([trim($name)]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * タグを作成または取得（既に存在する場合は既存のIDを返す）
     *
     * @param string $name タグ名
     * @return int タグID
     */
    public function findOrCreate(string $name): int
    {
        $name = trim($name);

        // 既存のタグを検索
        $existing = $this->getByName($name);
        if ($existing) {
            return (int)$existing['id'];
        }

        // 存在しない場合は作成
        try {
            return $this->create($name);
        } catch (\PDOException $e) {
            // 競合が発生した場合（並行処理）、再度取得を試みる
            $existing = $this->getByName($name);
            if ($existing) {
                return (int)$existing['id'];
            }
            throw $e;
        }
    }

    /**
     * タグを削除
     *
     * @param int $id タグID
     * @return bool 成功した場合true
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM tags WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * 使用されていないタグを削除
     *
     * @return int 削除されたタグ数
     */
    public function deleteUnused(): int
    {
        $stmt = $this->db->exec("
            DELETE FROM tags
            WHERE id NOT IN (SELECT DISTINCT tag_id FROM post_tags)
        ");
        return $stmt;
    }

    /**
     * タグ総数を取得
     *
     * @return int タグ数
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM tags");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }
}
