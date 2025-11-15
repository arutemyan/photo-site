<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Utils\ViewCounter;
use App\Utils\AccessLogger;
use App\Utils\InputValidator;
use App\Services\PostTagService;
use App\Constants\PostConstants;
use PDO;

/**
 * 投稿データ取得リポジトリ
 *
 * 投稿データの検索・取得を担当
 */
class PostRepository
{
    private PDO $db;
    private ViewCounter $viewCounter;
    private ?AccessLogger $accessLogger;
    private PostTagService $tagService;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
        $this->viewCounter = new ViewCounter();
        $this->accessLogger = AccessLogger::isEnabled() ? new AccessLogger() : null;
        $this->tagService = new PostTagService($this->db);
    }

    /**
     * すべての投稿を取得（表示可能なもののみ）
     *
     * @param int $limit 取得件数
     * @param string $nsfwFilter NSFWフィルタ
     * @param int|null $tagId タグフィルタ
     * @param int $offset オフセット
     * @return array 投稿データの配列
     */
    public function getAll(int $limit = PostConstants::DEFAULT_POSTS_PER_PAGE, string $nsfwFilter = PostConstants::NSFW_FILTER_ALL, ?int $tagId = null, int $offset = 0): array
    {
        // セキュリティ: 上限値を強制
        $limit = min($limit, PostConstants::MAX_POSTS_PER_PAGE);
        $offset = max($offset, 0);

        $sql = "
            SELECT id, post_type, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE is_visible = 1
        ";
        $params = [];

        // NSFWフィルタ
        if ($nsfwFilter === PostConstants::NSFW_FILTER_SAFE) {
            $sql .= " AND (is_sensitive = 0 OR is_sensitive IS NULL)";
        } elseif ($nsfwFilter === PostConstants::NSFW_FILTER_NSFW) {
            $sql .= " AND is_sensitive = 1";
        }

        // 入力検証
        if (!InputValidator::validateTagId($tagId) || !InputValidator::validateNsfwFilter($nsfwFilter)) {
            return [];
        }

        // タグフィルタ
        if ($tagId !== null && $tagId > 0) {
            $sql .= " AND (tag1 = ? OR tag2 = ? OR tag3 = ? OR tag4 = ? OR tag5 = ? OR tag6 = ? OR tag7 = ? OR tag8 = ? OR tag9 = ? OR tag10 = ?)";
            for ($i = 0; $i < 10; $i++) {
                $params[] = $tagId;
            }
        }

        $sql .= " ORDER BY sort_order DESC, created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        // 閲覧数を一括取得して追加
        if (!empty($posts)) {
            $postIds = array_column($posts, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds, PostConstants::POST_TYPE_SINGLE);

            foreach ($posts as &$post) {
                $post['view_count'] = $viewCounts[$post['id']] ?? 0;
                $post['tags'] = $this->tagService->getTagsFromRow($post);
            }
        }

        return $posts;
    }

    /**
     * すべての投稿を統合取得（single + group）
     *
     * @param int $limit 取得件数
     * @param string $nsfwFilter NSFWフィルタ
     * @param int|null $tagId タグフィルタ
     * @param int $offset オフセット
     * @return array 投稿データの配列
     */
    public function getAllUnified(int $limit = PostConstants::DEFAULT_POSTS_PER_PAGE, string $nsfwFilter = PostConstants::NSFW_FILTER_ALL, ?int $tagId = null, int $offset = 0): array
    {
        $limit = min($limit, PostConstants::MAX_POSTS_PER_PAGE);
        $offset = max($offset, 0);

        $sql = "
            SELECT id, post_type, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE is_visible = 1
        ";
        $params = [];

        // NSFWフィルタ
        if ($nsfwFilter === PostConstants::NSFW_FILTER_SAFE) {
            $sql .= " AND (is_sensitive = 0 OR is_sensitive IS NULL)";
        } elseif ($nsfwFilter === PostConstants::NSFW_FILTER_NSFW) {
            $sql .= " AND is_sensitive = 1";
        }

        // 入力検証
        if (!InputValidator::validateTagId($tagId) || !InputValidator::validateNsfwFilter($nsfwFilter)) {
            return [];
        }

        // タグフィルタ
        if ($tagId !== null && $tagId > 0) {
            $sql .= " AND (tag1 = ? OR tag2 = ? OR tag3 = ? OR tag4 = ? OR tag5 = ? OR tag6 = ? OR tag7 = ? OR tag8 = ? OR tag9 = ? OR tag10 = ?)";
            for ($i = 0; $i < 10; $i++) {
                $params[] = $tagId;
            }
        }

        $sql .= " ORDER BY sort_order DESC, created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        // グループ投稿も取得
        require_once __DIR__ . '/../Models/GroupPost.php';
        $groupPostModel = new \App\Models\GroupPost();

        // グループ投稿（posts テーブル内の post_type=1）の image_count / 代表画像を補完
        require_once __DIR__ . '/../Models/GroupPostImage.php';
        $groupPostImageModel = new \App\Models\GroupPostImage();

        foreach ($posts as &$post) {
            // attach tags for all posts
            $post['tags'] = $this->tagService->getTagsFromRow($post);

            if (($post['post_type'] ?? 0) == 1) {
                // image count
                $post['image_count'] = $groupPostImageModel->getImageCountByPostId((int)$post['id']);

                // representative image (first)
                $firstImage = $groupPostImageModel->getFirstImageByPostId((int)$post['id']);
                if ($firstImage) {
                    $post['image_path'] = $firstImage['image_path'];
                    $post['thumb_path'] = $firstImage['thumb_path'];
                } else {
                    $post['image_path'] = $post['image_path'] ?? null;
                    $post['thumb_path'] = $post['thumb_path'] ?? null;
                }
            }
        }

        return $posts;
    }

    /**
     * IDで投稿を取得
     *
     * @param int $id 投稿ID
     * @return array|null 投稿データ、存在しない場合はnull
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, post_type, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE id = ? AND is_visible = 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result === false) {
            return null;
        }

        // 閲覧数を取得
        $postType = $result['post_type'] ?? PostConstants::POST_TYPE_SINGLE;
        $result['view_count'] = $this->viewCounter->get((int)$result['id'], $postType);
        $result['tags'] = $this->tagService->getTagsFromRow($result);

        // アクセスログを記録
        if ($this->accessLogger !== null) {
            $this->accessLogger->log((int)$result['id'], 'single');
        }

        return $result;
    }

    /**
     * 管理画面用: すべての投稿を取得（非表示含む）
     *
     * @param int $limit 取得件数
     * @param int $offset オフセット
     * @return array 投稿データの配列
     */
    public function getAllForAdmin(int $limit = 100, int $offset = 0): array
    {
        $limit = min($limit, 1000);
        $offset = max($offset, 0);

        $stmt = $this->db->prepare("
            SELECT id, post_type, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            ORDER BY sort_order DESC, created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $posts = $stmt->fetchAll();

        // 投稿タイプごとに処理
        if (!empty($posts)) {
            // GroupPostImageモデルのインスタンス化
            require_once __DIR__ . '/../Models/GroupPostImage.php';
            $groupPostImageModel = new \App\Models\GroupPostImage();

            // 閲覧数を一括取得（post_typeごと）
            $singlePostIds = [];
            $groupPostIds = [];

            foreach ($posts as $post) {
                if (($post['post_type'] ?? 0) == 0) {
                    $singlePostIds[] = $post['id'];
                } else {
                    $groupPostIds[] = $post['id'];
                }
            }

            $singleViewCounts = !empty($singlePostIds) ? $this->viewCounter->getBatch($singlePostIds, PostConstants::POST_TYPE_SINGLE) : [];
            $groupViewCounts = !empty($groupPostIds) ? $this->viewCounter->getBatch($groupPostIds, PostConstants::POST_TYPE_GROUP) : [];

            // 各投稿にデータを付加
            foreach ($posts as &$post) {
                $post['tags'] = $this->tagService->getTagsFromRow($post);

                if (($post['post_type'] ?? 0) == 0) {
                    // シングル投稿
                    $post['view_count'] = $singleViewCounts[$post['id']] ?? 0;
                } else {
                    // グループ投稿
                    $post['view_count'] = $groupViewCounts[$post['id']] ?? 0;

                    // 代表画像を取得
                    $firstImage = $groupPostImageModel->getFirstImageByPostId($post['id']);
                    if ($firstImage) {
                        $post['image_path'] = $firstImage['image_path'];
                        $post['thumb_path'] = $firstImage['thumb_path'];
                    } else {
                        $post['image_path'] = null;
                        $post['thumb_path'] = null;
                    }

                    // 画像数を取得
                    $post['image_count'] = $groupPostImageModel->getImageCountByPostId($post['id']);
                }
            }
        }

        return $posts;
    }

    /**
     * 管理画面用: IDで投稿を取得（非表示含む）
     *
     * @param int $id 投稿ID
     * @return array|null 投稿データ、存在しない場合はnull
     */
    public function getByIdForAdmin(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, post_type, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result === false) {
            return null;
        }

        $postType = $result['post_type'] ?? PostConstants::POST_TYPE_SINGLE;
        $result['view_count'] = $this->viewCounter->get((int)$result['id'], $postType);
        $result['tags'] = $this->tagService->getTagsFromRow($result);

        return $result;
    }

    /**
     * タグで投稿を検索
     *
     * @param int $tagId タグID
     * @param int $limit 取得件数
     * @return array 投稿データの配列
     */
    public function getByTag(int $tagId, int $limit = PostConstants::DEFAULT_POSTS_PER_PAGE): array
    {
        return $this->getAll($limit, PostConstants::NSFW_FILTER_ALL, $tagId);
    }

    /**
     * 複数のタグで投稿を検索（OR条件）
     *
     * @param array $tagIds タグIDの配列
     * @param int $limit 取得件数
     * @return array 投稿データの配列
     */
    public function getByTags(array $tagIds, int $limit = PostConstants::DEFAULT_POSTS_PER_PAGE): array
    {
        if (empty($tagIds)) {
            return [];
        }

        $limit = min($limit, PostConstants::MAX_POSTS_PER_PAGE);

        // タグIDの検証
        foreach ($tagIds as $tagId) {
            if (!InputValidator::validateTagId($tagId)) {
                return [];
            }
        }

        $conditions = [];
        $params = [];
        foreach ($tagIds as $tagId) {
            $conditions[] = "(tag1 = ? OR tag2 = ? OR tag3 = ? OR tag4 = ? OR tag5 = ? OR tag6 = ? OR tag7 = ? OR tag8 = ? OR tag9 = ? OR tag10 = ?)";
            for ($i = 0; $i < 10; $i++) {
                $params[] = $tagId;
            }
        }

        $sql = "
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE is_visible = 1 AND (" . implode(' OR ', $conditions) . ")
            ORDER BY sort_order DESC, created_at DESC
            LIMIT ?
        ";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        // 閲覧数とタグを追加
        if (!empty($posts)) {
            $postIds = array_column($posts, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds, PostConstants::POST_TYPE_SINGLE);

            foreach ($posts as &$post) {
                $post['view_count'] = $viewCounts[$post['id']] ?? 0;
                $post['tags'] = $this->tagService->getTagsFromRow($post);
            }
        }

        return $posts;
    }
}
