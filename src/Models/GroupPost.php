<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use App\Services\PostTagService;
use App\Utils\ViewCounter;
use PDO;

/**
 * グループ投稿モデルクラス
 *
 * 複数の画像を1つの投稿として管理（漫画・連作向け）
 */
class GroupPost
{
    private PDO $db;
    private ViewCounter $viewCounter;
    private PostTagService $tagService;

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->viewCounter = new ViewCounter();
        $this->tagService = new PostTagService($this->db);
    }

    /**
     * 全てのグループ投稿を取得
     *
     * @param int $limit 取得件数
     * @param string $nsfwFilter NSFWフィルタ
     * @param int|null $tagId タグフィルタ
     * @param int $offset オフセット
     * @return array グループ投稿の配列
     */
    public function getAll(int $limit = 18, string $nsfwFilter = 'all', ?int $tagId = null, int $offset = 0): array
    {
        $limit = min($limit, 50);
        $offset = max($offset, 0);

        $sql = "
            SELECT gp.id, gp.title, gp.detail, gp.is_sensitive, gp.is_visible, gp.created_at, gp.updated_at,
                   gp.tag1, gp.tag2, gp.tag3, gp.tag4, gp.tag5, gp.tag6, gp.tag7, gp.tag8, gp.tag9, gp.tag10,
                   (SELECT COUNT(*) FROM group_post_images WHERE group_post_id = gp.id) as image_count
            FROM group_posts gp
            WHERE gp.is_visible = 1
        ";
        $params = [];

        // NSFWフィルタ
        if ($nsfwFilter === 'safe') {
            $sql .= " AND (gp.is_sensitive = 0 OR gp.is_sensitive IS NULL)";
        } elseif ($nsfwFilter === 'nsfw') {
            $sql .= " AND gp.is_sensitive = 1";
        }

        // タグフィルタ
        if ($tagId !== null && $tagId > 0) {
            $tagCondition = $this->tagService->buildTagSearchCondition($tagId);
            $sql .= " AND " . $tagCondition['sql'];
            $params = array_merge($params, $tagCondition['params']);
        }

        $sql .= " ORDER BY gp.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $groupPosts = $stmt->fetchAll();

        // 各グループの代表画像（最初の画像）を取得
        foreach ($groupPosts as &$groupPost) {
            $groupPost['tags'] = $this->tagService->getTagsFromRow($groupPost);
            $groupPost['view_count'] = $this->viewCounter->get($groupPost['id']);

            // 代表画像を取得
            $stmt = $this->db->prepare("
                SELECT image_path, thumb_path
                FROM group_post_images
                WHERE group_post_id = ?
                ORDER BY display_order ASC
                LIMIT 1
            ");
            $stmt->execute([$groupPost['id']]);
            $firstImage = $stmt->fetch();

            if ($firstImage) {
                $groupPost['image_path'] = $firstImage['image_path'];
                $groupPost['thumb_path'] = $firstImage['thumb_path'];
            } else {
                $groupPost['image_path'] = null;
                $groupPost['thumb_path'] = null;
            }
        }

        return $groupPosts;
    }

    /**
     * IDでグループ投稿を取得
     *
     * @param int $id グループ投稿ID
     * @param bool $includeHidden 非表示投稿も取得するか
     * @return array|null グループ投稿データ
     */
    public function getById(int $id, bool $includeHidden = false): ?array
    {
        $sql = "
            SELECT id, title, detail, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10
            FROM group_posts
            WHERE id = ?
        ";

        if (!$includeHidden) {
            $sql .= " AND is_visible = 1";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $groupPost = $stmt->fetch();

        if (!$groupPost) {
            return null;
        }

        // タグを取得
        $groupPost['tags'] = $this->tagService->getTagsFromRow($groupPost);
        $groupPost['view_count'] = $this->viewCounter->get($id);

        // グループ内の全画像を取得
        $groupPost['images'] = $this->getImages($id);
        $groupPost['image_count'] = count($groupPost['images']);

        return $groupPost;
    }

    /**
     * グループ内の全画像を取得
     *
     * @param int $groupPostId グループ投稿ID
     * @return array 画像配列
     */
    public function getImages(int $groupPostId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, image_path, thumb_path, display_order
            FROM group_post_images
            WHERE group_post_id = ?
            ORDER BY display_order ASC, id ASC
        ");
        $stmt->execute([$groupPostId]);
        return $stmt->fetchAll();
    }

    /**
     * グループ投稿を作成
     *
     * @param string $title タイトル
     * @param array $imagePaths 画像パスの配列（['image' => '', 'thumb' => '']）
     * @param string|null $tags タグ（カンマ区切り）
     * @param string|null $detail 詳細説明
     * @param int $isSensitive センシティブフラグ
     * @param int $isVisible 表示フラグ
     * @return int 作成されたグループ投稿のID
     */
    public function create(
        string $title,
        array $imagePaths,
        ?string $tags = null,
        ?string $detail = null,
        int $isSensitive = 0,
        int $isVisible = 1
    ): int {
        // タグIDを取得
        $tagIds = $this->tagService->parseTagsToIds($tags);

        // グループ投稿を作成
        $stmt = $this->db->prepare("
            INSERT INTO group_posts (
                title, detail, is_sensitive, is_visible,
                tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title, $detail, $isSensitive, $isVisible,
            $tagIds[0], $tagIds[1], $tagIds[2], $tagIds[3], $tagIds[4],
            $tagIds[5], $tagIds[6], $tagIds[7], $tagIds[8], $tagIds[9]
        ]);

        $groupPostId = (int)$this->db->lastInsertId();

        // 画像を追加
        foreach ($imagePaths as $index => $imagePath) {
            $this->addImage($groupPostId, $imagePath['image'], $imagePath['thumb'], $index);
        }

        return $groupPostId;
    }

    /**
     * グループに画像を追加
     *
     * @param int $groupPostId グループ投稿ID
     * @param string $imagePath 画像パス
     * @param string|null $thumbPath サムネイルパス
     * @param int $displayOrder 表示順序
     * @return int 画像ID
     */
    public function addImage(int $groupPostId, string $imagePath, ?string $thumbPath, int $displayOrder): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO group_post_images (group_post_id, image_path, thumb_path, display_order)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$groupPostId, $imagePath, $thumbPath, $displayOrder]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * グループ投稿を更新
     *
     * @param int $id グループ投稿ID
     * @param string $title タイトル
     * @param string|null $tags タグ
     * @param string|null $detail 詳細説明
     * @param int $isSensitive センシティブフラグ
     * @param int $isVisible 表示フラグ
     * @return bool 成功したらtrue
     */
    public function update(
        int $id,
        string $title,
        ?string $tags,
        ?string $detail,
        int $isSensitive,
        int $isVisible
    ): bool {
        $tagIds = $this->tagService->parseTagsToIds($tags);

        $stmt = $this->db->prepare("
            UPDATE group_posts
            SET title = ?, detail = ?, is_sensitive = ?, is_visible = ?,
                tag1 = ?, tag2 = ?, tag3 = ?, tag4 = ?, tag5 = ?,
                tag6 = ?, tag7 = ?, tag8 = ?, tag9 = ?, tag10 = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        return $stmt->execute([
            $title, $detail, $isSensitive, $isVisible,
            $tagIds[0], $tagIds[1], $tagIds[2], $tagIds[3], $tagIds[4],
            $tagIds[5], $tagIds[6], $tagIds[7], $tagIds[8], $tagIds[9],
            $id
        ]);
    }

    /**
     * グループ投稿を削除
     *
     * @param int $id グループ投稿ID
     * @return bool 成功したらtrue
     */
    public function delete(int $id): bool
    {
        // CASCADE削除で画像も自動削除される
        $stmt = $this->db->prepare("DELETE FROM group_posts WHERE id = ?");
        return $stmt->execute([$id]);
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
     * 画像情報を取得
     *
     * @param int $imageId 画像ID
     * @return array|null 画像データ
     */
    public function getImageById(int $imageId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, group_post_id, image_path, thumb_path, display_order
            FROM group_post_images
            WHERE id = ?
        ");
        $stmt->execute([$imageId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 閲覧回数をインクリメント
     *
     * @param int $id グループ投稿ID
     * @return bool 成功したらtrue
     */
    public function incrementViewCount(int $id): bool
    {
        return $this->viewCounter->increment($id);
    }
}
