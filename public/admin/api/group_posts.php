<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Models\Post;
use App\Models\GroupPostImage;
use App\Cache\CacheManager;

class GroupPostsController extends AdminControllerBase
{
    private Post $postModel;
    private GroupPostImage $groupPostImageModel;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->groupPostImageModel = new GroupPostImage();
    }

    protected function onProcess(string $method): void
    {
        switch ($method) {
            case 'GET':
                $this->handleGet();
                break;
            case 'PUT':
                $this->handlePut();
                break;
            case 'DELETE':
                $this->handleDelete();
                break;
            default:
                $this->sendError('サポートされていないメソッドです', 405);
        }
    }

    private function handleGet(): void
    {
        // 詳細取得
        if (isset($_GET['id'])) {
            $groupPost = $this->postModel->getByIdForAdmin((int)$_GET['id']);
            if ($groupPost && ($groupPost['post_type'] ?? 0) == 1) {
                // グループ投稿の画像を取得
                $groupPost['images'] = $this->groupPostImageModel->getImagesByPostId($groupPost['id']);
                $this->sendSuccess(['data' => $groupPost]);
                return;
            } else {
                $this->sendError('グループ投稿が見つかりません', 404);
                return;
            }
        }

        // 一覧取得 - post_type=1のみ
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $groupPosts = $this->postModel->getAllUnified($limit, 'all', null, $offset);

        // post_type=1のみフィルタ
        $groupPosts = array_filter($groupPosts, fn($post) => ($post['post_type'] ?? 0) == 1);
        $groupPosts = array_values($groupPosts); // 配列のインデックスを詰める

        $this->sendSuccess([
            'posts' => $groupPosts,
            'count' => count($groupPosts)
        ]);
    }

    private function handlePut(): void
    {
        $putData = $this->parseFormInput();
        $id = (int)($putData['id'] ?? 0);

        if ($id <= 0) {
            $this->sendError('IDが無効です');
        }

        $success = $this->postModel->updateTextOnly(
            $id,
            $putData['title'] ?? '',
            $putData['tags'] ?? null,
            $putData['detail'] ?? null,
            (int)($putData['is_sensitive'] ?? 0),
            (int)($putData['sort_order'] ?? 0)
        );

        if ($success) {
            $cache = new CacheManager();
            $cache->invalidateAllPosts();
            $this->sendSuccess(['message' => 'グループ投稿を更新しました']);
        } else {
            $this->sendError('更新に失敗しました', 500);
        }
    }

    private function handleDelete(): void
    {
        $deleteData = $this->parseFormInput();
        $id = (int)($deleteData['id'] ?? 0);

        if ($id <= 0) {
            $this->sendError('IDが無効です');
        }

        $success = $this->postModel->delete($id);

        if ($success) {
            $cache = new CacheManager();
            $cache->invalidateAllPosts();
            $this->sendSuccess(['message' => 'グループ投稿を削除しました']);
        } else {
            $this->sendError('削除に失敗しました', 500);
        }
    }
}

// コントローラーを実行
$controller = new GroupPostsController();
$controller->execute();
