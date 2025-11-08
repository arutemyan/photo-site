<?php

declare(strict_types=1);

/**
 * 管理画面の認証チェック
 *
 * すべての管理画面APIで使用
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
// feature gate (returns 404 if admin disabled)
require_once(__DIR__ . '/_feature_check.php');
require_once __DIR__ . '/../../src/Security/SecurityUtil.php';

use App\Security\CsrfProtection;

// セッション開始
initSecureSession();

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => '認証が必要です。ログインしてください。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
