<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use App\Utils\ViewCounter;
use App\Utils\AccessLogger;
use App\Services\PostTagService;
use PDO;

/**
 * 投稿モデルクラス
 *
 * 投稿データのCRUD操作を管理
 */
class Post
{
    private PDO $db;
    private ViewCounter $viewCounter;
    private ?AccessLogger $accessLogger;
    private PostTagService $tagService;

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->viewCounter = new ViewCounter();
        $this->accessLogger = AccessLogger::isEnabled() ? new AccessLogger() : null;
        $this->tagService = new PostTagService($this->db);
    }

    /**
     * すべての投稿を取得（最大50件、新しい順）
     *
     * @param int $limit 取得件数（デフォルト: 50）
     * @param string $nsfwFilter NSFWフィルタ（all: すべて, safe: 一般のみ, nsfw: NSFWのみ）
     * @param int|null $tagId タグフィルタ（タグID）
     * @return array 投稿データの配列
     */
    public function getAll(int $limit = 18, string $nsfwFilter = 'all', ?int $tagId = null, int $offset = 0): array
    {
        // セキュリティ: 上限値を強制（DoS攻撃対策）
        $limit = min($limit, 50); // 絶対に50件以上は返さない
        $offset = max($offset, 0); // 負のオフセットは無効

        $sql = "
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE is_visible = 1
        ";
        $params = [];

        // NSFWフィルタ
        if ($nsfwFilter === 'safe') {
            $sql .= " AND (is_sensitive = 0 OR is_sensitive IS NULL)";
        } elseif ($nsfwFilter === 'nsfw') {
            $sql .= " AND is_sensitive = 1";
        }

        // 以下の文字は解析しないで結果なしにする
        function checkNGTag($t) {
            if (empty($t)) return false;
            return false
                || strpos($t, ";") !== false
                || strpos($t, '"') !== false
                || strpos($t, "'") !== false;
        }
        if ((!empty($tagId) && !is_numeric($tagId)) || checkNGTag($nsfwFilter)) {
            return [];
        }
        // タグフィルタ（tag1～tag10のいずれかに一致）
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

        // 閲覧数を一括取得して追加 & tag1～tag10をtagsフィールドに変換
        if (!empty($posts)) {
            $postIds = array_column($posts, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds, 0); // 0=single

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
     * post_type=0（single）とpost_type=1（group）の両方を取得
     * 正しいページネーションと絞り込みが可能
     *
     * @param int $limit 取得件数
     * @param string $nsfwFilter NSFWフィルタ（all/safe/nsfw）
     * @param int|null $tagId タグフィルタ（タグID）
     * @param int $offset オフセット
     * @return array 投稿データの配列
     */
    public function getAllUnified(int $limit = 18, string $nsfwFilter = 'all', ?int $tagId = null, int $offset = 0): array
    {
        // セキュリティ: 上限値を強制
        $limit = min($limit, 50);
        $offset = max($offset, 0);

        $sql = "
            SELECT id, post_type, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE is_visible = 1
        ";
        $params = [];

        // NSFWフィルタ
        if ($nsfwFilter === 'safe') {
            $sql .= " AND (is_sensitive = 0 OR is_sensitive IS NULL)";
        } elseif ($nsfwFilter === 'nsfw') {
            $sql .= " AND is_sensitive = 1";
        }

        // セキュリティチェック
        function checkNGTag($t) {
            if (empty($t)) return false;
            return false
                || strpos($t, ";") !== false
                || strpos($t, '"') !== false
                || strpos($t, "'") !== false;
        }
        if ((!empty($tagId) && !is_numeric($tagId)) || checkNGTag($nsfwFilter)) {
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

        // 投稿タイプごとに処理
        if (!empty($posts)) {
            // GroupPostImageモデルのインスタンス化
            $groupPostImageModel = new GroupPostImage();

            // 閲覧数を一括取得（post_typeごと）
            $singlePostIds = [];
            $groupPostIds = [];

            foreach ($posts as $post) {
                if ($post['post_type'] == 0) {
                    $singlePostIds[] = $post['id'];
                } else {
                    $groupPostIds[] = $post['id'];
                }
            }

            $singleViewCounts = !empty($singlePostIds) ? $this->viewCounter->getBatch($singlePostIds, 0) : [];
            $groupViewCounts = !empty($groupPostIds) ? $this->viewCounter->getBatch($groupPostIds, 1) : [];

            // 各投稿にデータを付加
            foreach ($posts as &$post) {
                $post['tags'] = $this->tagService->getTagsFromRow($post);

                if ($post['post_type'] == 0) {
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
     * 投稿IDで投稿を取得
     *
     * @param int $id 投稿ID
     * @return array|null 投稿データ、存在しない場合はnull
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE id = ? AND is_visible = 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result !== false) {
            // 閲覧数を追加
            $result['view_count'] = $this->viewCounter->get((int)$result['id'], 0); // 0=single
            // tag1～tag10をtagsフィールドに変換
            $result['tags'] = $this->tagService->getTagsFromRow($result);
            return $result;
        }

        return null;
    }

    /**
     * 新しい投稿を作成
     *
     * @param string $title タイトル
     * @param string|null $tags タグ（カンマ区切り）
     * @param string|null $detail 詳細説明
     * @param string|null $imagePath 画像パス
     * @param string|null $thumbPath サムネイルパス
     * @param int $isSensitive センシティブ画像フラグ（0: 通常, 1: NSFW）
     * @param int $isVisible 表示フラグ（0: 非表示, 1: 表示）デフォルト1
     * @return int 作成された投稿のID
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

        // tag1～tag10を含むINSERT文を構築
        $sql = "INSERT INTO posts (title, detail, image_path, thumb_path, is_sensitive, is_visible, tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $title, $detail, $imagePath, $thumbPath, $isSensitive, $isVisible,
            $tagIds[0], $tagIds[1], $tagIds[2], $tagIds[3], $tagIds[4],
            $tagIds[5], $tagIds[6], $tagIds[7], $tagIds[8], $tagIds[9]
        ]);

        $postId = (int)$this->db->lastInsertId();

        return $postId;
    }

    /**
     * グループ投稿を作成
     *
     * @param string $title タイトル
     * @param array $imagePaths 画像パス配列 [['image' => '...', 'thumb' => '...'], ...]
     * @param string|null $tags タグ
     * @param string|null $detail 詳細説明
     * @param int $isSensitive センシティブ画像フラグ
     * @param int $isVisible 表示フラグ
     * @return int 作成された投稿ID
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
     *
     * @param int $id 投稿ID
     * @param string $title タイトル
     * @param string|null $tags タグ
     * @param string|null $detail 詳細説明
     * @param string|null $imagePath 画像パス
     * @param string|null $thumbPath サムネイルパス
     * @return bool 成功した場合true
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
     *
     * @param int $id 投稿ID
     * @param string $title タイトル
     * @param string|null $tags タグ
     * @param string|null $detail 詳細説明
     * @param int $isSensitive センシティブ画像フラグ（0: 通常, 1: NSFW）
     * @param int $sortOrder 表示順序（デフォルト: 0）
     * @return bool 成功した場合true
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
     *
     * @param int $id 投稿ID
     * @return bool 成功した場合true
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM posts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * 投稿数を取得
     *
     * @return int 投稿数
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM posts");
        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    /**
     * 閲覧回数をインクリメント
     *
     * @param int $id 投稿ID
     * @return bool 成功した場合true
     */
    public function incrementViewCount(int $id): bool
    {
        // カウンターDBで閲覧数をインクリメント (0=single)
        $success = $this->viewCounter->increment($id, 0);

        // アクセスログが有効な場合は記録
        if ($this->accessLogger !== null) {
            $this->accessLogger->log($id);
        }

        return $success;
    }

    /**
     * 管理画面用: 全投稿を取得（非表示含む）
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
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            ORDER BY sort_order DESC, created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $posts = $stmt->fetchAll();

        // 閲覧数を一括取得して追加 & tag1～tag10をtagsフィールドに変換
        if (!empty($posts)) {
            $postIds = array_column($posts, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds, 0); // 0=single

            foreach ($posts as &$post) {
                $post['view_count'] = $viewCounts[$post['id']] ?? 0;
                $post['tags'] = $this->tagService->getTagsFromRow($post);
            }
        }

        return $posts;
    }

    /**
     * 投稿の総件数を取得
     *
     * @return int 投稿の総件数
     */
    public function getTotalCount(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM posts");
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
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
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result !== false) {
            $result['view_count'] = $this->viewCounter->get((int)$result['id']);
            $result['tags'] = $this->tagService->getTagsFromRow($result);
            return $result;
        }

        return null;
    }

    /**
     * 投稿の表示/非表示を切り替え
     *
     * @param int $id 投稿ID
     * @param int $isVisible 表示状態（1: 表示, 0: 非表示）
     * @return bool 成功した場合true
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
     *
     * @param string $imagePath 画像パス
     * @param string|null $thumbPath サムネイルパス
     * @return int 作成された投稿のID
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

    /**
     * 閲覧回数を取得
     *
     * @param int $id 投稿ID
     * @return int 閲覧回数
     */
    public function getViewCount(int $id): int
    {
        return $this->viewCounter->get($id, 0); // 0=single
    }

    /**
     * タグで投稿を検索
     *
     * @param string $tagName タグ名
     * @param int $limit 取得件数（デフォルト: 50）
     * @return array 投稿データの配列
     */
    public function getByTag(string $tagName, int $limit = 50): array
    {
        $limit = min($limit, 50);

        // タグ名からタグIDを取得
        $stmt = $this->db->prepare("SELECT id FROM tags WHERE name = ?");
        $stmt->execute([$tagName]);
        $tag = $stmt->fetch();

        if (!$tag) {
            return [];
        }

        $tagId = (int)$tag['id'];

        // タグ検索条件を生成
        $condition = $this->tagService->buildTagSearchCondition($tagId);

        $stmt = $this->db->prepare("
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE is_visible = 1 AND {$condition['sql']}
            ORDER BY sort_order DESC, created_at DESC
            LIMIT ?
        ");

        $params = array_merge($condition['params'], [$limit]);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // 閲覧数を一括取得して追加 & tag1～tag10をtagsフィールドに変換
        if (!empty($results)) {
            $postIds = array_column($results, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds, 0); // 0=single

            foreach ($results as &$result) {
                $result['view_count'] = $viewCounts[$result['id']] ?? 0;
                $result['tags'] = $this->tagService->getTagsFromRow($result);
            }
        }

        return $results;
    }

    /**
     * 複数のタグで投稿を検索（AND検索）
     *
     * @param array $tagNames タグ名の配列
     * @param int $limit 取得件数（デフォルト: 50）
     * @return array 投稿データの配列
     */
    public function getByTags(array $tagNames, int $limit = 50): array
    {
        if (empty($tagNames)) {
            return [];
        }

        $limit = min($limit, 50);
        $tagNames = array_slice($tagNames, 0, 10); // 最大10個まで

        // タグ名からタグIDを取得
        $tagIds = $this->tagService->resolveTagNamesToIds($tagNames);

        if (empty($tagIds)) {
            return [];
        }

        // タグ検索条件を生成
        $condition = $this->tagService->buildMultiTagSearchCondition($tagIds);

        $stmt = $this->db->prepare("
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at, updated_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, sort_order
            FROM posts
            WHERE is_visible = 1 AND {$condition['sql']}
            ORDER BY sort_order DESC, created_at DESC
            LIMIT ?
        ");

        $params = array_merge($condition['params'], [$limit]);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // 閲覧数を一括取得して追加 & tag1～tag10をtagsフィールドに変換
        if (!empty($results)) {
            $postIds = array_column($results, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds, 0); // 0=single

            foreach ($results as &$result) {
                $result['view_count'] = $viewCounts[$result['id']] ?? 0;
                $result['tags'] = $this->tagService->getTagsFromRow($result);
            }
        }

        return $results;
    }
}
