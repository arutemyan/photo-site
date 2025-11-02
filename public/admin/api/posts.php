<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Models\Post;
use App\Security\CsrfProtection;
use App\Cache\CacheManager;
use App\Utils\ImageUploader;

initSecureSession();

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $postModel = new Post();

    // GETリクエスト: 投稿を取得
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 個別投稿の取得
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $postId = (int)$_GET['id'];
            $post = $postModel->getByIdForAdmin($postId);

            if ($post === null) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => '投稿が見つかりません'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode([
                'success' => true,
                'post' => $post
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 一覧取得（管理画面用: 非表示含む）
        // ページネーション対応
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 30;
        $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

        $posts = $postModel->getAllForAdmin($limit, $offset);

        // 総件数を取得
        $totalCount = $postModel->getTotalCount();
        $hasMore = ($offset + count($posts)) < $totalCount;

        echo json_encode([
            'success' => true,
            'count' => count($posts),
            'total' => $totalCount,
            'offset' => $offset,
            'limit' => $limit,
            'hasMore' => $hasMore,
            'posts' => $posts
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // POST/PUT/DELETEリクエストの処理
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }

    // CSRFトークン検証（POST/PUT/DELETEの場合）
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'CSRFトークンが無効です'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // PATCH: 一括更新（公開/非公開）
    if ($method === 'PATCH') {
        $postIds = $_POST['post_ids'] ?? [];
        $isVisible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : null;

        // バリデーション
        if (!is_array($postIds) || empty($postIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '投稿IDが指定されていません'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($isVisible === null || !in_array($isVisible, [0, 1])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '公開/非公開の指定が不正です'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 各投稿IDを整数化してバリデーション
        $postIds = array_map('intval', $postIds);
        $postIds = array_filter($postIds, function($id) {
            return $id > 0;
        });

        if (empty($postIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '有効な投稿IDがありません'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 一括更新処理
        $updatedCount = 0;
        foreach ($postIds as $postId) {
            $existingPost = $postModel->getByIdForAdmin($postId);
            if ($existingPost !== null) {
                $postModel->setVisibility($postId, $isVisible);
                $updatedCount++;
            }
        }

        // キャッシュを無効化
        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        $action = $isVisible === 1 ? '公開' : '非公開';
        echo json_encode([
            'success' => true,
            'message' => "{$updatedCount}件の投稿を{$action}にしました",
            'updated_count' => $updatedCount
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // PUT: 投稿更新
    if ($method === 'PUT') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        $title = $_POST['title'] ?? '';
        $tags = $_POST['tags'] ?? '';
        $detail = $_POST['detail'] ?? '';
        $isSensitive = isset($_POST['is_sensitive']) ? (int)$_POST['is_sensitive'] : 0;
        $isVisible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 0;
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

        // バリデーション
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '投稿IDが不正です'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'タイトルは必須です'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (mb_strlen($title) > 200) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'タイトルは200文字以内で入力してください'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 投稿の存在確認
        $existingPost = $postModel->getByIdForAdmin($id);
        if ($existingPost === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => '投稿が見つかりません'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // NSFWフィルター設定を読み込み
        $config = require __DIR__ . '/../../../config/config.php';
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
                $result = $postModel->update($id, $title, $tags, $detail, $newImagePath, $newThumbPath);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $uploadResult['error']], JSON_UNESCAPED_UNICODE);
                exit;
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
            $result = $postModel->updateTextOnly($id, $title, $tags, $detail, $isSensitive, $sortOrder);
        }

        // 表示/非表示を更新
        if ($result) {
            $postModel->setVisibility($id, $isVisible);
        }

        // キャッシュを無効化
        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        echo json_encode([
            'success' => true,
            'message' => '投稿が更新されました'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // DELETE: 投稿削除
    if ($method === 'DELETE') {
        $postId = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

        if ($postId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '投稿IDが無効です'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 投稿を取得
        $post = $postModel->getByIdForAdmin($postId);
        if ($post === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => '投稿が見つかりません'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 画像ファイルを削除（パストラバーサル対策）
        $uploadsDir = realpath(__DIR__ . '/../../uploads/');

        if (!empty($post['image_path'])) {
            $imagePath = realpath(__DIR__ . '/../../' . $post['image_path']);

            // パスがuploadsディレクトリ内にあることを検証
            if ($imagePath && $uploadsDir && strpos($imagePath, $uploadsDir) === 0 && file_exists($imagePath)) {
                unlink($imagePath);
            } elseif (!empty($post['image_path'])) {
                error_log('Invalid image path attempted for deletion: ' . $post['image_path']);
            }
        }

        if (!empty($post['thumb_path'])) {
            $thumbPath = realpath(__DIR__ . '/../../' . $post['thumb_path']);

            // パスがuploadsディレクトリ内にあることを検証
            if ($thumbPath && $uploadsDir && strpos($thumbPath, $uploadsDir) === 0 && file_exists($thumbPath)) {
                unlink($thumbPath);
            } elseif (!empty($post['thumb_path'])) {
                error_log('Invalid thumb path attempted for deletion: ' . $post['thumb_path']);
            }
        }

        // データベースから削除
        $postModel->delete($postId);

        // キャッシュを無効化
        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        echo json_encode([
            'success' => true,
            'message' => '投稿が削除されました'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // その他のメソッドは許可しない
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => '許可されていないメソッドです'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    // 本番環境では詳細なエラー情報をユーザーに表示しない（セキュリティ対策）
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ], JSON_UNESCAPED_UNICODE);

    // 詳細なエラー情報はサーバーログのみに記録
    error_log('Admin Posts API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}
