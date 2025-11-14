<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use App\Repositories\PostRepository;
use App\Utils\ViewCounter;
use App\Utils\AccessLogger;
use App\Services\PostTagService;
use App\Constants\PostConstants;
use PDO;

/**
 * 投稿モデルクラス
 *
 * 投稿データのCRUD操作を管理
 * データ取得はPostRepositoryに委譲
 */
class Post
{
    private PDO $db;
    private ViewCounter $viewCounter;
    private ?AccessLogger $accessLogger;
    private PostTagService $tagService;
    private PostRepository $repository;

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->viewCounter = new ViewCounter();
        $this->accessLogger = AccessLogger::isEnabled() ? new AccessLogger() : null;
        $this->tagService = new PostTagService($this->db);
        $this->repository = new PostRepository($this->db);
    }

    // ========================================
    // データ取得（PostRepositoryに委譲）
    // ========================================

    /**
     * すべての投稿を取得（表示可能なもののみ）
     */
    public function getAll(int $limit = PostConstants::DEFAULT_POSTS_PER_PAGE, string $nsfwFilter = PostConstants::NSFW_FILTER_ALL, ?int $tagId = null, int $offset = 0): array
    {
        return $this->repository->getAll($limit, $nsfwFilter, $tagId, $offset);
    }

    /**
     * すべての投稿を統合取得（single + group）
     */
    public function getAllUnified(int $limit = PostConstants::DEFAULT_POSTS_PER_PAGE, string $nsfwFilter = PostConstants::NSFW_FILTER_ALL, ?int $tagId = null, int $offset = 0): array
    {
        return $this->repository->getAllUnified($limit, $nsfwFilter, $tagId, $offset);
    }

    /**
     * IDで投稿を取得
     */
    public function getById(int $id): ?array
    {
        return $this->repository->getById($id);
    }

    /**
     * 管理画面用: すべての投稿を取得（非表示含む）
     */
    public function getAllForAdmin(int $limit = 100, int $offset = 0): array
    {
        return $this->repository->getAllForAdmin($limit, $offset);
    }

    /**
     * 管理画面用: IDで投稿を取得（非表示含む）
     */
    public function getByIdForAdmin(int $id): ?array
    {
        return $this->repository->getByIdForAdmin($id);
    }

    /**
     * タグで投稿を検索
     */
    public function getByTag(string $tagName, int $limit = PostConstants::DEFAULT_POSTS_PER_PAGE): array
    {
        // タグ名からタグIDを取得
        $stmt = $this->db->prepare("SELECT id FROM tags WHERE name = ?");
        $stmt->execute([$tagName]);
        $tag = $stmt->fetch();

        if (!$tag) {
            return [];
        }

        return $this->repository->getByTag((int)$tag['id'], $limit);
    }

    /**
     * 複数のタグで投稿を検索
     */
    public function getByTags(array $tagNames, int $limit = PostConstants::DEFAULT_POSTS_PER_PAGE): array
    {
        if (empty($tagNames)) {
            return [];
        }

        // タグ名からタグIDを取得
        $tagIds = $this->tagService->resolveTagNamesToIds($tagNames);

        if (empty($tagIds)) {
            return [];
        }

        return $this->repository->getByTags($tagIds, $limit);
    }

    // ========================================
    // CRUD操作（Post.phpで保持）
    // ========================================

    /**
     * 新しい投稿を作成
     */
    public function create(
        string $title,
        ?string $tags = null,
        ?string $detail = null,
        ?string $imagePath = null,
        ?string $thumbPath = null,
        int $isSensitive = 0,
        int $isVisible = 1
    ): int {
        // タグ文字列をタグID配列に変換
        $tagIds = $this->tagService->parseTagsToIds($tags);

        $sql = "INSERT INTO posts (title, detail, image_path, thumb_path, is_sensitive, is_visible, tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $title, $detail, $imagePath, $thumbPath, $isSensitive, $isVisible,
            $tagIds[0], $tagIds[1], $tagIds[2], $tagIds[3], $tagIds[4],
            $tagIds[5], $tagIds[6], $tagIds[7], $tagIds[8], $tagIds[9]
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * グループ投稿を作成
     */
    public function createGroupPost(
        string $title,
        array $imagePaths,
        ?string $tags = null,
        ?string $detail = null,
        int $isSensitive = 0,
        int $isVisible = 1
    ): int {
        // タグ文字列をタグID配列に変換
        $tagIds = $this->tagService->parseTagsToIds($tags);

        // post_type=1 でグループ投稿を作成
        $sql = "INSERT INTO posts (post_type, title, detail, is_sensitive, is_visible, tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $title, $detail, $isSensitive, $isVisible,
            $tagIds[0], $tagIds[1], $tagIds[2], $tagIds[3], $tagIds[4],
            $tagIds[5], $tagIds[6], $tagIds[7], $tagIds[8], $tagIds[9]
        ]);

        $postId = (int)$this->db->lastInsertId();

        // 画像を group_post_images に登録
        $groupPostImageModel = new GroupPostImage();
        $displayOrder = 0;
        foreach ($imagePaths as $imagePath) {
            $groupPostImageModel->addImage(
                $postId,
                $imagePath['image'],
                $imagePath['thumb'],
                $displayOrder++
            );
        }

        return $postId;
    }

    /**
     * 投稿を更新
     */
    public function update(
        int $id,
        string $title,
        ?string $tags = null,
        ?string $detail = null,
        ?string $imagePath = null,
        ?string $thumbPath = null
    ): bool {
        // タグ文字列をタグID配列に変換
        $tagIds = $this->tagService->parseTagsToIds($tags);

        $stmt = $this->db->prepare("
            UPDATE posts
            SET title = ?, detail = ?, image_path = ?, thumb_path = ?,
                tag1 = ?, tag2 = ?, tag3 = ?, tag4 = ?, tag5 = ?,
                tag6 = ?, tag7 = ?, tag8 = ?, tag9 = ?, tag10 = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $title, $detail, $imagePath, $thumbPath,
            $tagIds[0], $tagIds[1], $tagIds[2], $tagIds[3], $tagIds[4],
            $tagIds[5], $tagIds[6], $tagIds[7], $tagIds[8], $tagIds[9],
            $id
        ]);
    }

    /**
     * 投稿のテキストフィールドのみを更新（画像は変更しない）
     */
    public function updateTextOnly(
        int $id,
        string $title,
        ?string $tags = null,
        ?string $detail = null,
        int $isSensitive = 0,
        int $sortOrder = 0
    ): bool {
        // タグ文字列をタグID配列に変換
        $tagIds = $this->tagService->parseTagsToIds($tags);

        $stmt = $this->db->prepare("
            UPDATE posts
            SET title = ?, detail = ?, is_sensitive = ?, sort_order = ?,
                tag1 = ?, tag2 = ?, tag3 = ?, tag4 = ?, tag5 = ?,
                tag6 = ?, tag7 = ?, tag8 = ?, tag9 = ?, tag10 = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $title, $detail, $isSensitive, $sortOrder,
            $tagIds[0], $tagIds[1], $tagIds[2], $tagIds[3], $tagIds[4],
            $tagIds[5], $tagIds[6], $tagIds[7], $tagIds[8], $tagIds[9],
            $id
        ]);
    }

    /**
     * 投稿を削除
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM posts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * 投稿の表示/非表示を切り替え
     */
    public function setVisibility(int $id, int $isVisible): bool
    {
        $stmt = $this->db->prepare("
            UPDATE posts
            SET is_visible = ?
            WHERE id = ?
        ");
        return $stmt->execute([$isVisible, $id]);
    }

    /**
     * 一括アップロード用: 画像を非表示状態で登録
     */
    public function createBulk(string $imagePath, ?string $thumbPath = null): int
    {
        // ファイル名から簡易的なタイトルを生成
        $filename = basename($imagePath);
        $title = pathinfo($filename, PATHINFO_FILENAME);

        $stmt = $this->db->prepare("
            INSERT INTO posts (title, image_path, thumb_path, is_visible, is_sensitive)
            VALUES (?, ?, ?, 0, 0)
        ");
        $stmt->execute([$title, $imagePath, $thumbPath]);

        return (int)$this->db->lastInsertId();
    }

    // ========================================
    // その他のユーティリティ
    // ========================================

    /**
     * 投稿数を取得
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM posts");
        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    /**
     * 投稿の総件数を取得
     */
    public function getTotalCount(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM posts");
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    /**
     * 閲覧回数をインクリメント
     */
    public function incrementViewCount(int $id): bool
    {
        // カウンターDBで閲覧数をインクリメント
        $success = $this->viewCounter->increment($id, PostConstants::POST_TYPE_SINGLE);

        // アクセスログが有効な場合は記録
        if ($this->accessLogger !== null) {
            $this->accessLogger->log($id);
        }

        return $success;
    }

    /**
     * 閲覧回数を取得
     */
    public function getViewCount(int $id): int
    {
        return $this->viewCounter->get($id, PostConstants::POST_TYPE_SINGLE);
    }
}
