<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Models\GroupPost;
use App\Utils\ImageUploader;
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

// HTTPメソッドを確認
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'DELETE') {
    $method = 'DELETE';
}

// POSTまたはDELETEのみ許可
if ($method !== 'POST' && $method !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POSTまたはDELETEメソッドのみ許可されています'], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRFトークン検証
if (!CsrfProtection::validatePost()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRFトークンが無効です'], JSON_UNESCAPED_UNICODE);
    exit;
}

$groupPostModel = new GroupPost();

// DELETEリクエスト: 画像削除
if ($method === 'DELETE') {
    try {
        $imageId = (int)($_POST['image_id'] ?? 0);

        if ($imageId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '画像IDが無効です'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 画像情報を取得
        $image = $groupPostModel->getImageById($imageId);
        if (!$image) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => '画像が見つかりません'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // グループ内の画像数を確認（最低1枚は必要）
        $groupPost = $groupPostModel->getById($image['group_post_id'], true);
        if ($groupPost && count($groupPost['images']) <= 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'グループ内の最後の画像は削除できません'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ファイルを削除
        $imagePath = __DIR__ . '/../../' . $image['image_path'];
        $thumbPath = __DIR__ . '/../../' . $image['thumb_path'];

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        if ($image['thumb_path'] && file_exists($thumbPath)) {
            unlink($thumbPath);
        }

        // NSFWフィルター版の画像も削除
        $nsfwImages = [
            str_replace('.webp', '_blur.webp', $imagePath),
            str_replace('.webp', '_frosted.webp', $imagePath),
            str_replace('.webp', '_blur.webp', $thumbPath),
            str_replace('.webp', '_frosted.webp', $thumbPath)
        ];
        foreach ($nsfwImages as $nsfwPath) {
            if (file_exists($nsfwPath)) {
                unlink($nsfwPath);
            }
        }

        // DBから削除
        $success = $groupPostModel->deleteImage($imageId);

        if ($success) {
            $cache = new CacheManager();
            $cache->invalidateAllPosts();

            echo json_encode([
                'success' => true,
                'message' => '画像を削除しました'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => '削除に失敗しました'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        http_response_code(500);
        $errorDetails = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        echo json_encode([
            'success' => false,
            'error' => 'サーバーエラー: ' . $e->getMessage(),
            'debug' => $errorDetails
        ], JSON_UNESCAPED_UNICODE);
        error_log('Group Image Delete Error: ' . $errorDetails);
        error_log('Stack trace: ' . $e->getTraceAsString());
    }
    exit;
}

// POSTリクエスト: 画像差し替え
try {
    $imageId = (int)($_POST['image_id'] ?? 0);

    if ($imageId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '画像IDが無効です'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 画像情報を取得
    $image = $groupPostModel->getImageById($imageId);
    if (!$image) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => '画像が見つかりません'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 新しい画像ファイルをチェック
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '画像ファイルがアップロードされていません'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // グループ投稿情報を取得（is_sensitive設定のため）
    $groupPost = $groupPostModel->getById($image['group_post_id'], true);
    if (!$groupPost) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'グループ投稿が見つかりません'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ImageUploaderを初期化
    $imageUploader = new ImageUploader(
        __DIR__ . '/../../uploads/images',
        __DIR__ . '/../../uploads/thumbs',
        20 * 1024 * 1024 // 20MB
    );

    // ファイル検証
    $validation = $imageUploader->validateFile($_FILES['image']);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $validation['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // NSFW設定を読み込み
    $config = require __DIR__ . '/../../../config/config.php';
    $nsfwConfig = $config['nsfw'];
    $filterSettings = $nsfwConfig['filter_settings'];

    // ユニークなファイル名を生成
    $uniqueName = $imageUploader->generateUniqueFilename('group_');

    // 画像を処理して保存
    $uploadResult = $imageUploader->processAndSave(
        $_FILES['image']['tmp_name'],
        $validation['mime_type'],
        $uniqueName,
        $groupPost['is_sensitive'] == 1, // NSFWフィルター版を作成
        $filterSettings
    );

    if (!$uploadResult['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $uploadResult['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 古い画像ファイルを削除
    $oldImagePath = __DIR__ . '/../../' . $image['image_path'];
    $oldThumbPath = __DIR__ . '/../../' . $image['thumb_path'];

    if (file_exists($oldImagePath)) {
        unlink($oldImagePath);
    }
    if ($image['thumb_path'] && file_exists($oldThumbPath)) {
        unlink($oldThumbPath);
    }

    // 古いNSFWフィルター版の画像も削除
    $oldNsfwImages = [
        str_replace('.webp', '_blur.webp', $oldImagePath),
        str_replace('.webp', '_frosted.webp', $oldImagePath),
        str_replace('.webp', '_blur.webp', $oldThumbPath),
        str_replace('.webp', '_frosted.webp', $oldThumbPath)
    ];
    foreach ($oldNsfwImages as $nsfwPath) {
        if (file_exists($nsfwPath)) {
            unlink($nsfwPath);
        }
    }

    // DBを更新
    $success = $groupPostModel->updateImage(
        $imageId,
        $uploadResult['image_path'],
        $uploadResult['thumb_path']
    );

    if ($success) {
        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        echo json_encode([
            'success' => true,
            'message' => '画像を差し替えました',
            'image_path' => $uploadResult['image_path'],
            'thumb_path' => $uploadResult['thumb_path']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '更新に失敗しました'], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    $errorDetails = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラー: ' . $e->getMessage(),
        'debug' => $errorDetails
    ], JSON_UNESCAPED_UNICODE);
    error_log('Group Image Replace Error: ' . $errorDetails);
    error_log('Stack trace: ' . $e->getTraceAsString());
}
exit;
