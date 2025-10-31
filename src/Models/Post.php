<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use App\Utils\ViewCounter;
use App\Utils\AccessLogger;
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

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->viewCounter = new ViewCounter();
        $this->accessLogger = AccessLogger::isEnabled() ? new AccessLogger() : null;
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
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10
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

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        // 閲覧数を一括取得して追加 & tag1～tag10をtagsフィールドに変換
        if (!empty($posts)) {
            $postIds = array_column($posts, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds);

            foreach ($posts as &$post) {
                $post['view_count'] = $viewCounts[$post['id']] ?? 0;
                $post['tags'] = $this->getTagsFromRow($post);
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
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10
            FROM posts
            WHERE id = ? AND is_visible = 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result !== false) {
            // 閲覧数を追加
            $result['view_count'] = $this->viewCounter->get((int)$result['id']);
            // tag1～tag10をtagsフィールドに変換
            $result['tags'] = $this->getTagsFromRow($result);
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
        // タグをカンマ区切りから配列に変換し、前後のスペース/タブを除去
        $tagArray = $this->tagsToArray($tags);
        $tagIds = $this->getOrCreateTagIds($tagArray);

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
        // タグをカンマ区切りから配列に変換
        $tagArray = $this->tagsToArray($tags);
        $tagIds = $this->getOrCreateTagIds($tagArray);

        $stmt = $this->db->prepare("
            UPDATE posts
            SET title = ?, detail = ?, image_path = ?, thumb_path = ?,
                tag1 = ?, tag2 = ?, tag3 = ?, tag4 = ?, tag5 = ?,
                tag6 = ?, tag7 = ?, tag8 = ?, tag9 = ?, tag10 = ?
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
     * @return bool 成功した場合true
     */
    public function updateTextOnly(
        int $id,
        string $title,
        ?string $tags = null,
        ?string $detail = null,
        int $isSensitive = 0
    ): bool {
        // タグをカンマ区切りから配列に変換
        $tagArray = $this->tagsToArray($tags);
        $tagIds = $this->getOrCreateTagIds($tagArray);

        $stmt = $this->db->prepare("
            UPDATE posts
            SET title = ?, detail = ?, is_sensitive = ?,
                tag1 = ?, tag2 = ?, tag3 = ?, tag4 = ?, tag5 = ?,
                tag6 = ?, tag7 = ?, tag8 = ?, tag9 = ?, tag10 = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $title, $detail, $isSensitive,
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
        // カウンターDBで閲覧数をインクリメント
        $success = $this->viewCounter->increment($id);

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
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10
            FROM posts
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $posts = $stmt->fetchAll();

        // 閲覧数を一括取得して追加 & tag1～tag10をtagsフィールドに変換
        if (!empty($posts)) {
            $postIds = array_column($posts, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds);

            foreach ($posts as &$post) {
                $post['view_count'] = $viewCounts[$post['id']] ?? 0;
                $post['tags'] = $this->getTagsFromRow($post);
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
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10
            FROM posts
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result !== false) {
            $result['view_count'] = $this->viewCounter->get((int)$result['id']);
            $result['tags'] = $this->getTagsFromRow($result);
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
        return $this->viewCounter->get($id);
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

        // tag1～tag10のいずれかがタグIDと一致する投稿を検索
        $stmt = $this->db->prepare("
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10
            FROM posts
            WHERE is_visible = 1
              AND (tag1 = ? OR tag2 = ? OR tag3 = ? OR tag4 = ? OR tag5 = ?
                   OR tag6 = ? OR tag7 = ? OR tag8 = ? OR tag9 = ? OR tag10 = ?)
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([
            $tagId, $tagId, $tagId, $tagId, $tagId,
            $tagId, $tagId, $tagId, $tagId, $tagId,
            $limit
        ]);
        $results = $stmt->fetchAll();

        // 閲覧数を一括取得して追加 & tag1～tag10をtagsフィールドに変換
        if (!empty($results)) {
            $postIds = array_column($results, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds);

            foreach ($results as &$result) {
                $result['view_count'] = $viewCounts[$result['id']] ?? 0;
                $result['tags'] = $this->getTagsFromRow($result);
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
        $tagIds = [];
        foreach ($tagNames as $tagName) {
            $stmt = $this->db->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([$tagName]);
            $tag = $stmt->fetch();
            if ($tag) {
                $tagIds[] = (int)$tag['id'];
            }
        }

        if (empty($tagIds)) {
            return [];
        }

        // tag1～tag10のいずれかに各タグIDが含まれているかをチェック
        $conditions = [];
        $params = [];

        foreach ($tagIds as $tagId) {
            $conditions[] = "(tag1 = ? OR tag2 = ? OR tag3 = ? OR tag4 = ? OR tag5 = ? OR tag6 = ? OR tag7 = ? OR tag8 = ? OR tag9 = ? OR tag10 = ?)";
            for ($i = 0; $i < 10; $i++) {
                $params[] = $tagId;
            }
        }

        $whereClause = implode(' AND ', $conditions);

        $stmt = $this->db->prepare("
            SELECT id, title, detail, image_path, thumb_path, is_sensitive, is_visible, created_at,
                   tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10
            FROM posts
            WHERE is_visible = 1 AND ({$whereClause})
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $params[] = $limit;
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // 閲覧数を一括取得して追加 & tag1～tag10をtagsフィールドに変換
        if (!empty($results)) {
            $postIds = array_column($results, 'id');
            $viewCounts = $this->viewCounter->getBatch($postIds);

            foreach ($results as &$result) {
                $result['view_count'] = $viewCounts[$result['id']] ?? 0;
                $result['tags'] = $this->getTagsFromRow($result);
            }
        }

        return $results;
    }

    /**
     * タグ文字列（カンマ区切り）を配列に変換
     * 前後のスペース/タブを除去し、空要素を削除、最大10個に制限
     *
     * @param string|null $tags タグ文字列（カンマ区切り）
     * @return array タグ配列（最大10個）
     */
    private function tagsToArray(?string $tags): array
    {
        if (empty($tags)) {
            return [];
        }

        // カンマで分割し、前後のスペース/タブを除去
        $tagArray = array_map('trim', explode(',', $tags));

        // 空要素を削除
        $tagArray = array_filter($tagArray, function($tag) {
            return !empty($tag);
        });

        // 最大10個に制限
        return array_slice($tagArray, 0, 10);
    }

    /**
     * タグ名配列からタグIDを取得または作成
     * 10個未満の場合はnullで埋める
     *
     * @param array $tagNames タグ名配列
     * @return array 10要素の配列（tag1～tag10のタグID、または null）
     */
    private function getOrCreateTagIds(array $tagNames): array
    {
        $tagIds = array_fill(0, 10, null);

        for ($i = 0; $i < min(count($tagNames), 10); $i++) {
            $tagName = $tagNames[$i];
            if (empty($tagName)) {
                continue;
            }

            // タグを取得または作成
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO tags (name) VALUES (?)");
            $stmt->execute([$tagName]);

            $stmt = $this->db->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([$tagName]);
            $tag = $stmt->fetch();

            if ($tag) {
                $tagIds[$i] = (int)$tag['id'];
            }
        }

        return $tagIds;
    }

    /**
     * 投稿行データからtag1～tag10（タグID）を読み取り、タグ名のカンマ区切り文字列に変換
     *
     * @param array $row 投稿行データ
     * @return string カンマ区切りのタグ名文字列
     */
    private function getTagsFromRow(array $row): string
    {
        $tagIds = [];

        // tag1～tag10からタグIDを取得
        for ($i = 1; $i <= 10; $i++) {
            $tagKey = "tag{$i}";
            if (isset($row[$tagKey]) && !empty($row[$tagKey])) {
                $tagIds[] = (int)$row[$tagKey];
            }
        }

        if (empty($tagIds)) {
            return '';
        }

        // タグIDからタグ名を一括取得
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $stmt = $this->db->prepare("SELECT name FROM tags WHERE id IN ({$placeholders}) ORDER BY id");
        $stmt->execute($tagIds);
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return implode(',', $tags);
    }
}
