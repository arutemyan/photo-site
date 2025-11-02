<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

/**
 * グループ投稿画像モデルクラス
 *
 * group_post_imagesテーブルへのアクセスを管理
 * 複数画像投稿（post_type=1）の画像管理に使用
 */
class GroupPostImage
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * 指定した投稿の全画像を取得
     *
     * @param int $postId 投稿ID（posts.id）
     * @return array 画像配列
     */
    public function getImagesByPostId(int $postId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, group_post_id as post_id, image_path, thumb_path, display_order
            FROM group_post_images
            WHERE group_post_id = ?
            ORDER BY display_order ASC, id ASC
        ");
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    }

    /**
     * 投稿の画像数を取得
     *
     * @param int $postId 投稿ID
     * @return int 画像数
     */
    public function getImageCountByPostId(int $postId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM group_post_images
            WHERE group_post_id = ?
        ");
        $stmt->execute([$postId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 投稿の代表画像（最初の画像）を取得
     *
     * @param int $postId 投稿ID
     * @return array|null 画像データ
     */
    public function getFirstImageByPostId(int $postId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, group_post_id as post_id, image_path, thumb_path, display_order
            FROM group_post_images
            WHERE group_post_id = ?
            ORDER BY display_order ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$postId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * IDで画像を取得
     *
     * @param int $imageId 画像ID
     * @return array|null 画像データ
     */
    public function getImageById(int $imageId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, group_post_id as post_id, image_path, thumb_path, display_order
            FROM group_post_images
            WHERE id = ?
        ");
        $stmt->execute([$imageId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 投稿に画像を追加
     *
     * @param int $postId 投稿ID
     * @param string $imagePath 画像パス
     * @param string|null $thumbPath サムネイルパス
     * @param int $displayOrder 表示順序
     * @return int 追加された画像ID
     */
    public function addImage(int $postId, string $imagePath, ?string $thumbPath, int $displayOrder): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO group_post_images (group_post_id, image_path, thumb_path, display_order)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$postId, $imagePath, $thumbPath, $displayOrder]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * 画像を更新（差し替え）
     *
     * @param int $imageId 画像ID
     * @param string $imagePath 新しい画像パス
     * @param string|null $thumbPath 新しいサムネイルパス
     * @return bool 成功したらtrue
     */
    public function updateImage(int $imageId, string $imagePath, ?string $thumbPath): bool
    {
        $stmt = $this->db->prepare("
            UPDATE group_post_images
            SET image_path = ?, thumb_path = ?
            WHERE id = ?
        ");
        return $stmt->execute([$imagePath, $thumbPath, $imageId]);
    }

    /**
     * 画像の表示順序を更新
     *
     * @param int $imageId 画像ID
     * @param int $displayOrder 新しい表示順序
     * @return bool 成功したらtrue
     */
    public function updateDisplayOrder(int $imageId, int $displayOrder): bool
    {
        $stmt = $this->db->prepare("
            UPDATE group_post_images
            SET display_order = ?
            WHERE id = ?
        ");
        return $stmt->execute([$displayOrder, $imageId]);
    }

    /**
     * 画像を削除
     *
     * @param int $imageId 画像ID
     * @return bool 成功したらtrue
     */
    public function deleteImage(int $imageId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM group_post_images WHERE id = ?");
        return $stmt->execute([$imageId]);
    }

    /**
     * 投稿のすべての画像を削除
     *
     * @param int $postId 投稿ID
     * @return bool 成功したらtrue
     */
    public function deleteAllByPostId(int $postId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM group_post_images WHERE group_post_id = ?");
        return $stmt->execute([$postId]);
    }
}
