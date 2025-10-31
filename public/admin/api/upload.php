<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Cache\CacheManager;
use App\Models\Post;
use App\Security\CsrfProtection;
use App\Utils\ImageUploader;

// セッション開始
initSecureSession();

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POSTメソッドのみ許可されています'], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRFトークン検証
if (!CsrfProtection::validatePost()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRFトークンが無効です'], JSON_UNESCAPED_UNICODE);
    logSecurityEvent('CSRF token validation failed on upload', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    // 入力検証
    if (empty($_POST['title'])) {
        echo json_encode(['success' => false, 'error' => 'タイトルは必須です'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'error' => '画像ファイルが必要です'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // NSFW設定を読み込み
    $config = require __DIR__ . '/../../../config/config.php';
    $nsfwConfig = $config['nsfw'];
    $filterSettings = $nsfwConfig['filter_settings'];

    // ImageUploaderを初期化
    $imageUploader = new ImageUploader(
        __DIR__ . '/../../uploads/images',
        __DIR__ . '/../../uploads/thumbs',
        10 * 1024 * 1024 // 10MB
    );

    // ファイルアップロード検証
    $validation = $imageUploader->validateFile($_FILES['image']);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'error' => $validation['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 投稿データを取得
    $title = trim($_POST['title']);
    $tags = isset($_POST['tags']) ? trim($_POST['tags']) : null;
    $detail = isset($_POST['detail']) ? trim($_POST['detail']) : null;
    $isSensitive = isset($_POST['is_sensitive']) ? (int)$_POST['is_sensitive'] : 0;
    $isVisible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 1;

    // ユニークなファイル名を生成
    $uniqueName = $imageUploader->generateUniqueFilename();

    // 画像を処理して保存
    $uploadResult = $imageUploader->processAndSave(
        $_FILES['image']['tmp_name'],
        $validation['mime_type'],
        $uniqueName,
        $isSensitive == 1, // センシティブ画像の場合はNSFWフィルター版も生成
        $filterSettings // フィルター設定
    );

    if (!$uploadResult['success']) {
        echo json_encode(['success' => false, 'error' => $uploadResult['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // データベースに保存
    $postModel = new Post();

    $postId = $postModel->create(
        $title,
        $tags,
        $detail,
        $uploadResult['image_path'],
        $uploadResult['thumb_path'],
        $isSensitive,
        $isVisible
    );

    // キャッシュを無効化
    $cache = new CacheManager();
    $cache->invalidateAllPosts();

    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'id' => $postId,
        'message' => '投稿が作成されました'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ], JSON_UNESCAPED_UNICODE);

    error_log('Upload Error: ' . $e->getMessage());
}
