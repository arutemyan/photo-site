<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
// feature gate (returns 404 if admin disabled)
require_once(__DIR__ . '/_feature_check.php');
require_once __DIR__ . '/../../src/Security/SecurityUtil.php';

use App\Models\User;
use App\Security\CsrfProtection;
use App\Security\RateLimiter;
use App\Utils\PathHelper;
use App\Utils\Logger;

// セッション開始
initSecureSession();

// レート制限の初期化（15分間で5回まで）
$rateLimiter = new RateLimiter(__DIR__ . '/../../data/rate-limits', 5, 900);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// すでにログイン済みの場合はダッシュボードへリダイレクト
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ' . PathHelper::getAdminUrl('index.php'));
    exit;
}

$error = null;
$success = null;

// セットアップファイル削除成功メッセージ
if (isset($_GET['setup_deleted']) && $_GET['setup_deleted'] == '1') {
    $success = 'セットアップファイルが正常に削除されました。';
}

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // レート制限チェック
    if (!$rateLimiter->check($clientIp, 'login')) {
        $retryAfter = $rateLimiter->getRetryAfter($clientIp, 'login');
        $waitMinutes = $retryAfter ? ceil(($retryAfter - time()) / 60) : 15;
        $error = "ログイン試行回数が上限に達しました。{$waitMinutes}分後に再度お試しください。";
        logSecurityEvent('Login rate limit exceeded', ['ip' => $clientIp]);
        http_response_code(429);
    }
    // CSRFトークン検証
    elseif (!CsrfProtection::validatePost()) {
        $error = 'CSRFトークンが無効です';
        logSecurityEvent('CSRF token validation failed on login', ['ip' => $clientIp]);
        $rateLimiter->record($clientIp, 'login');
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'ユーザー名とパスワードを入力してください';
        } else {
            try {
                $userModel = new User();
                $user = $userModel->authenticate($username, $password);

                if ($user !== null) {
                    // 認証成功 - レート制限をリセット
                    $rateLimiter->reset($clientIp, 'login');

                    regenerateSessionId();
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];

                    logSecurityEvent('Admin login successful', ['username' => $username]);

                    header('Location: ' . PathHelper::getAdminUrl('index.php'));
                    exit;
                } else {
                    // 認証失敗 - 試行を記録
                    $rateLimiter->record($clientIp, 'login');
                    $remaining = $rateLimiter->getRemainingAttempts($clientIp, 'login');

                    $error = 'ユーザー名またはパスワードが正しくありません';
                    if ($remaining > 0 && $remaining <= 3) {
                        $error .= "（残り{$remaining}回の試行が可能です）";
                    }

                    logSecurityEvent('Admin login failed', ['username' => $username, 'remaining_attempts' => $remaining]);
                }
            } catch (Exception $e) {
                $error = 'ログインエラーが発生しました';
                Logger::getInstance()->error('Login Error: ' . $e->getMessage());
            }
        }
    }
}

// CSRFトークンを生成
$csrfToken = CsrfProtection::generateToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面ログイン - イラストポートフォリオ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/res/css/login.css" rel="stylesheet">
</head>
<body>
    <div class="login-card">
        <h1 class="login-title h3">管理画面ログイン</h1>

        <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?= escapeHtml($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            ✅ <?= escapeHtml($success) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

            <div class="mb-3">
                <label for="username" class="form-label">ユーザー名</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">パスワード</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-login w-100">ログイン</button>
        </form>
    </div>
</body>
</html>
