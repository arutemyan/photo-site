<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../auth_check.php';

use App\Models\Post;
use App\Models\GroupPostImage;
use App\Security\CsrfProtection;
use App\Cache\CacheManager;

initSecureSession();

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// POSTで_methodが指定されている場合はそれを使う
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

$postModel = new Post();
$groupPostImageModel = new GroupPostImage();

try {
    switch ($method) {
        case 'GET':
            // グループ投稿一覧または詳細取得
            if (isset($_GET['id'])) {
                $groupPost = $postModel->getById((int)$_GET['id']);
                if ($groupPost && $groupPost['post_type'] == 1) {
                    // グループ投稿の画像を取得
                    $groupPost['images'] = $groupPostImageModel->getImagesByPostId($groupPost['id']);
                    echo json_encode(['success' => true, 'data' => $groupPost], JSON_UNESCAPED_UNICODE);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'グループ投稿が見つかりません'], JSON_UNESCAPED_UNICODE);
                }
            } else {
                // 一覧取得 - post_type=1のみ
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                $groupPosts = $postModel->getAllUnified($limit, 'all', null, $offset);

                // post_type=1のみフィルタ
                $groupPosts = array_filter($groupPosts, function($post) {
                    return $post['post_type'] == 1;
                });
                $groupPosts = array_values($groupPosts); // 配列のインデックスを詰める

                $count = count($groupPosts);

                echo json_encode([
                    'success' => true,
                    'posts' => $groupPosts,
                    'count' => $count
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'PUT':
            // 更新
            if (!CsrfProtection::validatePost()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'CSRFトークンが無効です'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            parse_str(file_get_contents('php://input'), $putData);
            $id = (int)($putData['id'] ?? 0);

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'IDが無効です'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $success = $postModel->updateTextOnly(
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
                echo json_encode(['success' => true, 'message' => 'グループ投稿を更新しました'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => '更新に失敗しました'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'DELETE':
            // 削除
            if (!CsrfProtection::validatePost()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'CSRFトークンが無効です'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            parse_str(file_get_contents('php://input'), $deleteData);
            $id = (int)($deleteData['id'] ?? 0);

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'IDが無効です'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $success = $postModel->delete($id);

            if ($success) {
                $cache = new CacheManager();
                $cache->invalidateAllPosts();
                echo json_encode(['success' => true, 'message' => 'グループ投稿を削除しました'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => '削除に失敗しました'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'サポートされていないメソッドです'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'サーバーエラーが発生しました'], JSON_UNESCAPED_UNICODE);
    error_log('Group Posts API Error: ' . $e->getMessage());
}
