<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
$config = \App\Config\ConfigManager::getInstance()->getConfig();
require_once __DIR__ . '/../../src/Security/SecurityUtil.php';

use App\Security\CsrfProtection;
use App\Utils\PathHelper;

// セッション開始
initSecureSession();

// POSTリクエストのみ許可（CSRF対策）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ' . PathHelper::getAdminUrl('index.php'));
    exit;
}

// CSRFトークン検証
if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
    http_response_code(403);
    logSecurityEvent('CSRF token validation failed on logout', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    header('Location: ' . PathHelper::getAdminUrl('index.php'));
    exit;
}

// セッションデータを全てクリア
$_SESSION = array();

// セッションクッキーも削除
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破棄
session_destroy();

// 新しいセッションを開始してセッションIDを再生成（セッション固定攻撃対策）
session_start();
session_regenerate_id(true);

// セキュリティログを記録
logSecurityEvent('Admin logout', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

// ログインページへリダイレクト
header('Location: ' . PathHelper::getAdminUrl('login.php'));
exit;
