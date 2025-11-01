<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Models\Setting;
use App\Security\CsrfProtection;

// セッション開始
initSecureSession();

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POSTメソッドのみ許可されています'], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRFトークン検証
if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRFトークンが無効です'], JSON_UNESCAPED_UNICODE);
    logSecurityEvent('CSRF token validation failed on OGP image upload', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    exit;
}

try {
    // 画像ファイルチェック
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '画像ファイルをアップロードしてください'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // MIME typeチェック
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '画像ファイル（JPEG, PNG, WebP, GIF）のみアップロード可能です'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ファイルサイズチェック (5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ファイルサイズは5MB以下にしてください'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // アップロードディレクトリ
    $uploadDir = __DIR__ . '/../../uploads/ogp';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ファイル名生成（既存のOGP画像を上書き）
    $extension = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'jpg'
    };
    $filename = 'ogp-image.' . $extension;
    $filepath = $uploadDir . '/' . $filename;

    // 既存のOGP画像を削除
    foreach (glob($uploadDir . '/ogp-image.*') as $oldFile) {
        @unlink($oldFile);
    }

    // ファイルを移動
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'ファイルの保存に失敗しました'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // パーミッション設定
    chmod($filepath, 0644);

    // 相対パスを生成
    $relativePath = 'uploads/ogp/' . $filename;

    // データベースに保存
    $settingModel = new Setting();
    $settingModel->set('ogp_image', $relativePath);

    echo json_encode([
        'success' => true,
        'message' => 'OGP画像がアップロードされました',
        'image_path' => $relativePath
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ], JSON_UNESCAPED_UNICODE);

    error_log('OGP Image Upload Error: ' . $e->getMessage());
}
