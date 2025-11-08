<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Models\Post;
use App\Models\GroupPostImage;
use App\Cache\CacheManager;
use App\Utils\ImageUploader;
use App\Utils\Logger;

class PostsController extends AdminControllerBase
{
    private Post $postModel;

    public function __construct()
    {
        $this->postModel = new Post();
    }

    protected function onProcess(string $method): void
    {
        switch ($method) {
            case 'GET':
                $this->handleGet();
                break;
            case 'PATCH':
                $this->handleBulkUpdate();
                break;
            case 'PUT':
                $this->handleUpdate();
                break;
            case 'DELETE':
                $this->handleDelete();
                break;
            default:
                $this->sendError('許可されていないメソッドです', 405);
        }
    }

    private function handleGet(): void
    {
        // 個別投稿の取得
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $postId = (int)$_GET['id'];
            $post = $this->postModel->getByIdForAdmin($postId);

            if ($post === null) {
                $this->sendError('投稿が見つかりません', 404);
            }

            // グループ投稿の場合は画像一覧を追加
            if ($post['post_type'] == 1) {
                $groupPostImageModel = new GroupPostImage();
                $post['images'] = $groupPostImageModel->getImagesByPostId($postId);
            }

            $this->sendSuccess(['post' => $post]);
        }

        // 一覧取得（管理画面用: 非表示含む）
        // ページネーション対応
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 30;
        $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

        $posts = $this->postModel->getAllForAdmin($limit, $offset);

        // 総件数を取得
        $totalCount = $this->postModel->getTotalCount();
        $hasMore = ($offset + count($posts)) < $totalCount;

        $this->sendSuccess([
            'count' => count($posts),
            'total' => $totalCount,
            'offset' => $offset,
            'limit' => $limit,
            'hasMore' => $hasMore,
            'posts' => $posts
        ]);
    }

    private function handleBulkUpdate(): void
    {
        $postIds = $_POST['post_ids'] ?? [];
        $isVisible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : null;

        // バリデーション
        if (!is_array($postIds) || empty($postIds)) {
            $this->sendError('投稿IDが指定されていません', 400);
        }

        if ($isVisible === null || !in_array($isVisible, [0, 1])) {
            $this->sendError('公開/非公開の指定が不正です', 400);
        }

        // 各投稿IDを整数化してバリデーション
        $postIds = array_map('intval', $postIds);
        $postIds = array_filter($postIds, function($id) {
            return $id > 0;
        });

        if (empty($postIds)) {
            $this->sendError('有効な投稿IDがありません', 400);
        }

        // 一括更新処理
        $updatedCount = 0;
        foreach ($postIds as $postId) {
            $existingPost = $this->postModel->getByIdForAdmin($postId);
            if ($existingPost !== null) {
                $this->postModel->setVisibility($postId, $isVisible);
                $updatedCount++;
            }
        }

        // キャッシュを無効化
        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        $action = $isVisible === 1 ? '公開' : '非公開';
        $this->sendSuccess([
            'message' => "{$updatedCount}件の投稿を{$action}にしました",
            'updated_count' => $updatedCount
        ]);
    }

    private function handleUpdate(): void
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        $title = $_POST['title'] ?? '';
        $tags = $_POST['tags'] ?? '';
        $detail = $_POST['detail'] ?? '';
        $isSensitive = isset($_POST['is_sensitive']) ? (int)$_POST['is_sensitive'] : 0;
        $isVisible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 0;
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

        // バリデーション
        if ($id <= 0) {
            $this->sendError('投稿IDが不正です', 400);
        }

        if (empty($title)) {
            $this->sendError('タイトルは必須です', 400);
        }

        if (mb_strlen($title) > 200) {
            $this->sendError('タイトルは200文字以内で入力してください', 400);
        }

        // 投稿の存在確認
        $existingPost = $this->postModel->getByIdForAdmin($id);
        if ($existingPost === null) {
            $this->sendError('投稿が見つかりません', 404);
        }

        // NSFWフィルター設定を読み込み
        $config = \App\Config\ConfigManager::getInstance()->getConfig();
        $filterSettings = $config['nsfw']['filter_settings'];

        // 画像が差し替えられた場合の処理
        $newImagePath = $existingPost['image_path'];
        $newThumbPath = $existingPost['thumb_path'];

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // 新しい画像をアップロード
            $imageUploader = new ImageUploader(
                __DIR__ . '/../../../public/uploads/images',
                __DIR__ . '/../../../public/uploads/thumbs'
            );

            $uploadResult = $imageUploader->upload($_FILES['image'], $isSensitive, $filterSettings);

            if ($uploadResult['success']) {
                // 古い画像ファイルを削除
                $uploadsDir = realpath(__DIR__ . '/../../uploads/');

                if (!empty($existingPost['image_path'])) {
                    $oldImagePath = realpath(__DIR__ . '/../../' . $existingPost['image_path']);
                    if ($oldImagePath && $uploadsDir && strpos($oldImagePath, $uploadsDir) === 0 && file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                if (!empty($existingPost['thumb_path'])) {
                    $oldThumbPath = realpath(__DIR__ . '/../../' . $existingPost['thumb_path']);
                    if ($oldThumbPath && $uploadsDir && strpos($oldThumbPath, $uploadsDir) === 0 && file_exists($oldThumbPath)) {
                        unlink($oldThumbPath);

                        // NSFWフィルター画像も削除
                        $pathInfo = pathinfo($oldThumbPath);
                        $nsfwPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp');
                        if (file_exists($nsfwPath)) {
                            unlink($nsfwPath);
                        }
                    }
                }

                // 新しい画像パスを設定
                $newImagePath = $uploadResult['image_path'];
                $newThumbPath = $uploadResult['thumb_path'];

                // 画像を含めて更新
                $result = $this->postModel->update($id, $title, $tags, $detail, $newImagePath, $newThumbPath);
            } else {
                $this->sendError($uploadResult['error'], 400);
            }
        } else {
            // is_sensitiveが変更された場合、NSFWフィルター画像を処理
            $oldIsSensitive = (int)$existingPost['is_sensitive'];
            $newIsSensitive = $isSensitive;

            if ($oldIsSensitive !== $newIsSensitive) {
                $thumbPath = $existingPost['thumb_path'];
                if (!empty($thumbPath)) {
                    $thumbFullPath = __DIR__ . '/../../../public/' . $thumbPath;
                    $pathInfo = pathinfo($thumbFullPath);
                    $nsfwPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp');

                    if ($newIsSensitive === 1) {
                        // 0→1: NSFWフィルター画像を生成
                        if (file_exists($thumbFullPath)) {
                            $imageUploader = new ImageUploader(
                                __DIR__ . '/../../../public/uploads/images',
                                __DIR__ . '/../../../public/uploads/thumbs'
                            );
                            $imageUploader->createNsfwThumbnail($thumbFullPath, $nsfwPath, $filterSettings);
                        }
                    } else {
                        // 1→0: NSFWフィルター画像を削除
                        if (file_exists($nsfwPath)) {
                            unlink($nsfwPath);
                        }
                    }
                }
            }

            // 投稿を更新（画像パスは変更なし）
            $result = $this->postModel->updateTextOnly($id, $title, $tags, $detail, $isSensitive, $sortOrder);
        }

        // 表示/非表示を更新
        if ($result) {
            $this->postModel->setVisibility($id, $isVisible);
        }

        // キャッシュを無効化
        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        $this->sendSuccess(['message' => '投稿が更新されました']);
    }

    private function handleDelete(): void
    {
        $postId = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if ($postId <= 0) {
            $this->sendError('投稿IDが無効です', 400);
        }

        // 投稿を取得
        $post = $this->postModel->getByIdForAdmin($postId);
        if ($post === null) {
            $this->sendError('投稿が見つかりません', 404);
        }

        // 画像ファイルを削除（パストラバーサル対策）
        $uploadsDir = realpath(__DIR__ . '/../../uploads/');

        if (!empty($post['image_path'])) {
            $imagePath = realpath(__DIR__ . '/../../' . $post['image_path']);

            // パスがuploadsディレクトリ内にあることを検証
            if ($imagePath && $uploadsDir && strpos($imagePath, $uploadsDir) === 0 && file_exists($imagePath)) {
                unlink($imagePath);
            } elseif (!empty($post['image_path'])) {
                Logger::getInstance()->error('Invalid image path attempted for deletion: ' . $post['image_path']);
            }
        }

        if (!empty($post['thumb_path'])) {
            $thumbPath = realpath(__DIR__ . '/../../' . $post['thumb_path']);

            // パスがuploadsディレクトリ内にあることを検証
            if ($thumbPath && $uploadsDir && strpos($thumbPath, $uploadsDir) === 0 && file_exists($thumbPath)) {
                unlink($thumbPath);
            } elseif (!empty($post['thumb_path'])) {
                Logger::getInstance()->error('Invalid thumb path attempted for deletion: ' . $post['thumb_path']);
            }
        }

        // データベースから削除
        $this->postModel->delete($postId);

        // キャッシュを無効化
        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        $this->sendSuccess(['message' => '投稿が削除されました']);
    }
}

// コントローラーを実行
$controller = new PostsController();
$controller->execute();
