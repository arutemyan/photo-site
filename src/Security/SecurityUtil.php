<?php
declare(strict_types=1);
use App\Utils\Logger;

/**
 * セキュリティ関数ライブラリ
 *
 * CSRF/XSS/SQLインジェクション/パスワードハッシュ/ファイルアップロード対策
 *
 * @version 1.0.0
 * @author Claude Code
 */

/**
 * HTTPS接続かどうかを判定
 *
 * @return bool HTTPS接続の場合true
 */
function isHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
           || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * HTTPSを強制（リダイレクト）
 *
 * @return void
 */
function forceHttps(): void
{
    if (!isHttps()) {
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        // If headers already sent, log and avoid calling header() which would raise a warning
        if (headers_sent($file, $line)) {
            Logger::getInstance()->warning(sprintf('Cannot redirect to HTTPS because headers already sent at %s:%d', $file ?? 'unknown', $line ?? 0));
            return;
        }

        header('Location: ' . $redirect, true, 301);
        exit;
    }
}

/**
 * セキュリティヘッダーを送信
 *
 * @param array $config セキュリティ設定
 * @return void
 */
function sendSecurityHeaders(array $config = []): void
{
    // If output has already started, headers cannot be modified. Log and return early.
    if (headers_sent($file, $line)) {
        try {
            Logger::getInstance()->warning(sprintf('sendSecurityHeaders skipped: headers already sent at %s:%d', $file ?? 'unknown', $line ?? 0));
        } catch (Throwable $e) {
            // Logging failure should not break execution
        }
        return;
    }
    // X-Content-Type-Options
    header('X-Content-Type-Options: nosniff');

    // X-Frame-Options
    header('X-Frame-Options: SAMEORIGIN');

    // X-XSS-Protection（古いブラウザ用）
    header('X-XSS-Protection: 1; mode=block');

    // Referrer-Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // HSTS（HTTPS有効時のみ）
    if (isHttps() && !empty($config['https']['hsts_enabled'])) {
        $maxAge = $config['https']['hsts_max_age'] ?? 31536000;
        header('Strict-Transport-Security: max-age=' . $maxAge . '; includeSubDomains');
    }

    // Content-Security-Policy（オプション）
    if (!empty($config['csp']['enabled'])) {
        // 管理画面かどうかを判定
        $isAdmin = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false);

        if ($isAdmin) {
            // 管理画面では画像プレビュー等のためにより緩和されたCSP
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net code.jquery.com; " .
                   "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; " .
                   "img-src 'self' data: blob: https:; " .
                   "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net; " .
                   "connect-src 'self'";
        } else {
            // 公開ページでは厳格なCSP
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net code.jquery.com; " .
                   "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; " .
                   "img-src 'self' data: blob:; " .
                   "font-src 'self' fonts.gstatic.com; " .
                   "connect-src 'self'";
        }

        // レポートのみモードか通常モードか
        $headerName = !empty($config['csp']['report_only'])
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        header($headerName . ': ' . $csp);
    }

    // Permissions-Policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/**
 * XSS対策: HTMLエスケープ
 *
 * @param string $text エスケープする文字列
 * @return string エスケープされた文字列
 */
function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * SQLインジェクション対策: Prepared Statement用のパラメータバインディング
 *
 * 注意: この関数は使用例を示すもので、実際はPDOのprepare/executeを直接使用すること
 *
 * @param PDO $pdo PDOインスタンス
 * @param string $query SQLクエリ
 * @param array $params バインドするパラメータ
 * @return PDOStatement|false
 */
function executePreparedStatement(PDO $pdo, string $query, array $params = []): PDOStatement|false
{
    $stmt = $pdo->prepare($query);
    if ($stmt === false) {
        return false;
    }

    $stmt->execute($params);
    return $stmt;
}

/**
 * パスワードハッシュ化
 *
 * PASSWORD_DEFAULT（bcrypt以上）を使用
 *
 * @param string $password 平文パスワード
 * @return string ハッシュ化されたパスワード
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * パスワード検証
 *
 * @param string $password 平文パスワード
 * @param string $hash ハッシュ化されたパスワード
 * @return bool パスワードが一致する場合はtrue
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * ファイルアップロード検証
 *
 * @param array $file $_FILES配列の要素
 * @param int $maxSizeMb 最大ファイルサイズ（MB）
 * @param array $allowedMimeTypes 許可するMIMEタイプ
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateFileUpload(array $file, int $maxSizeMb = 10, array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp']): array
{
    // ファイルが存在しない場合
    if (!isset($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'ファイルがアップロードされていません'];
    }

    // 本番環境ではis_uploaded_fileでチェック、テスト環境ではファイルの存在確認
    if (!is_uploaded_file($file['tmp_name']) && !file_exists($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'ファイルがアップロードされていません'];
    }

    // ファイルサイズチェック
    $maxSizeBytes = $maxSizeMb * 1024 * 1024;
    if ($file['size'] > $maxSizeBytes) {
        return ['valid' => false, 'error' => "ファイルサイズが{$maxSizeMb}MBを超えています"];
    }

    // MIMEタイプチェック（finfo使用）
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return ['valid' => false, 'error' => '許可されていないファイル形式です'];
    }

    // 拡張子とMIMEタイプの整合性チェック
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $validExtensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp']
    ];

    if (isset($validExtensions[$mimeType])) {
        if (!in_array($extension, $validExtensions[$mimeType], true)) {
            return ['valid' => false, 'error' => '拡張子とファイル形式が一致しません'];
        }
    }

    // PHPファイルアップロード防止（念のため二重チェック）
    if (in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5', 'phps'], true)) {
        return ['valid' => false, 'error' => 'PHPファイルはアップロードできません'];
    }

    return ['valid' => true, 'error' => null];
}

/**
 * コンテキストから機密情報を除去
 *
 * @param array $context コンテキスト情報
 * @return array サニタイズされたコンテキスト
 */
function sanitizeLogContext(array $context): array
{
    $sensitiveKeys = [
        'password', 'passwd', 'pwd',
        'token', 'csrf_token', 'api_key', 'secret',
        'authorization', 'auth', 'cookie',
        'session', 'sess'
    ];

    $sanitized = [];
    foreach ($context as $key => $value) {
        $keyLower = strtolower($key);
        $isSensitive = false;

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (strpos($keyLower, $sensitiveKey) !== false) {
                $isSensitive = true;
                break;
            }
        }

        if ($isSensitive) {
            $sanitized[$key] = '[REDACTED]';
        } elseif (is_array($value)) {
            $sanitized[$key] = sanitizeLogContext($value);
        } else {
            $sanitized[$key] = $value;
        }
    }

    return $sanitized;
}

/**
 * セキュアなディレクトリを作成
 *
 * ディレクトリを作成し、自動的に.htaccessで外部アクセスを拒否する
 *
 * @param string $dirPath ディレクトリパス
 * @param int $permissions パーミッション（デフォルト: 0755）
 * @param bool $recursive 再帰的に作成するか（デフォルト: true）
 * @return bool 成功時true
 */
function ensureSecureDirectory(string $dirPath, int $permissions = 0755, bool $recursive = true): bool
{
    // ディレクトリが存在しない場合は作成
    if (!is_dir($dirPath)) {
        if (!mkdir($dirPath, $permissions, $recursive)) {
            Logger::getInstance()->error("Failed to create directory: {$dirPath}");
            return false;
        }
    }

    // .htaccessのパス
    $htaccessPath = rtrim($dirPath, '/') . '/.htaccess';

    // .htaccessが存在しない場合は作成
    if (!file_exists($htaccessPath)) {
        $htaccessContent = <<<'HTACCESS'
# Deny all access to this directory
# Generated automatically by ensureSecureDirectory()

<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
HTACCESS;

        if (file_put_contents($htaccessPath, $htaccessContent) === false) {
            Logger::getInstance()->error("Failed to create .htaccess in: {$dirPath}");
            return false;
        }

        // .htaccessのパーミッションを設定
        chmod($htaccessPath, 0644);
    }

    return true;
}

/**
 * セキュリティログ記録
 *
 * @param string $message ログメッセージ
 * @param array $context 追加コンテキスト情報
 * @return void
 */
function logSecurityEvent(string $message, array $context = []): void
{
    // 設定を読み込み
    static $config = null;
    if ($config === null) {
        $config = \App\Config\ConfigManager::getInstance()->getConfig();
    }

    // ログが無効の場合は何もしない
    if (empty($config['security']['logging']['enabled'])) {
        return;
    }

    // ログディレクトリを取得して保護
    $logDir = dirname($config['security']['logging']['log_file'] ?? __DIR__ . '/../../logs/security.log');
    ensureSecureDirectory($logDir);

    // ログファイルパスを取得
    $logFile = $config['security']['logging']['log_file'] ?? __DIR__ . '/../../logs/security.log';

    // 機密情報のサニタイズ
    if (!empty($config['security']['logging']['sanitize'])) {
        $context = sanitizeLogContext($context);
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';

    $logEntry = sprintf(
        "[%s] [IP: %s] %s %s\n",
        $timestamp,
        $ip,
        $message,
        $contextJson
    );

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
