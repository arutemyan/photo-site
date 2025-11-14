<?php

declare(strict_types=1);

namespace App\Services;

require_once __DIR__. '/../Security/SecurityUtil.php';

use Exception;

/**
 * 簡易 Session 管理クラス
 * - PHP セッションの初期化ラッパー
 * - キーリングによる自動ローテーション（ファイルベース）
 * - AES-256-GCM による可逆 ID マスク（マスク/アンマスク）
 *
 * 注意: デフォルトのキー保存場所は project_root/config/session_keys/
 * 複数ノード運用の場合はキー同期（Vault 等）を別途用意してください。
 */
class Session
{
    private static ?Session $instance = null;

    private string $keyDir;
    /** @var string[] $keys raw binary keys, newest first */
    private array $keys = [];
    private int $rotationInterval; // seconds
    private int $retainKeys;
    private string $sessionNamespace = '_app_session';

    /**
     * start または getInstance を呼ぶこと
     *
     * @param array $opts [key_dir, rotation_interval]
     */
    public static function start(array $opts = []): Session
    {
        if (self::$instance === null) {
            self::$instance = new self($opts);
        } else {
            // If instance already exists ensure PHP session is active; this
            // handles tests that destroy the PHP session but keep the
            // Session singleton around.
            if (session_status() !== PHP_SESSION_ACTIVE) {
                // attempt to initialize PHP session settings and start it
                self::$instance->initPhpSession($opts);
            }
        }
        return self::$instance;
    }

    public static function getInstance(): Session
    {
        if (self::$instance === null) {
            throw new Exception('Session not started');
        }
        return self::$instance;
    }

    private function __construct(array $opts = [])
    {
        $projectRoot = dirname(__DIR__, 2);
        $this->keyDir = $opts['key_dir'] ?? $projectRoot . '/config/session_keys';
        $this->rotationInterval = $opts['rotation_interval'] ?? (60 * 60 * 24 * 30); // 30 days
        $this->retainKeys = $opts['retain_keys'] ?? 3; // keep newest N keys to allow decryption after rotation

        $this->initPhpSession($opts);
        $this->ensureKeyDir();
        $this->loadKeys();
        $this->rotateIfNeeded();
    }

    private function initPhpSession(array $config = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // 設定がない場合は読み込む
            if ($config === null) {
                $config = \App\Config\ConfigManager::getInstance()->getConfig();
            }

            // HTTPS強制
            if (!empty($config['https']['force'])) {
                forceHttps();
            }

            // セキュリティヘッダー送信
            sendSecurityHeaders($config);

            ini_set('session.cookie_httponly', '1');
            // HTTPS環境でのみsecure cookieを有効化
            // 本番環境では常にHTTPS前提でsecure=1を設定することを推奨
            $isHttps = isHttps();
            $forceSecure = $config['session']['force_secure_cookie'] ?? false;
            ini_set('session.cookie_secure', ($isHttps || $forceSecure) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            
            // セッションIDの再生成を定期的に行う設定
            ini_set('session.gc_maxlifetime', '3600'); // 1時間
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
            
            session_start();
        }
    }

    private function ensureKeyDir(): void
    {
        if (!is_dir($this->keyDir)) {
            if (!mkdir($this->keyDir, 0700, true) && !is_dir($this->keyDir)) {
                throw new Exception('Failed to create key directory: ' . $this->keyDir);
            }
        } else {
            // 既存ディレクトリのパーミッションを強制的に0700に設定
            // セキュリティ上重要なディレクトリのため、所有者のみアクセス可能にする
            chmod($this->keyDir, 0700);
        }
    }

    private function loadKeys(): void
    {
        // support both legacy .bin and new .php key files
        $files = array_merge(
            glob(rtrim($this->keyDir, '/') . '/key_*.php') ?: []
        );
        if (!$files) {
            // generate initial key
            $this->generateKey();
            $files = array_merge(
                glob(rtrim($this->keyDir, '/') . '/key_*.php') ?: []
            );
        }

        // sort newest first
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $this->keys = [];
        foreach ($files as $f) {
            try {
                if (substr($f, -4) === '.php') {
                    // require the php file which returns base64 encoded key
                    $val = @include $f;
                    if (!is_string($val) || $val === '') {
                        continue;
                    }
                    $k = base64_decode($val, true);
                    if ($k === false) {
                        continue;
                    }
                    $this->keys[] = $k;
                }
            } catch (\Throwable $ex) {
                // skip problematic files
                continue;
            }
        }

        if (empty($this->keys)) {
            throw new Exception('No valid keys available in ' . $this->keyDir);
        }
    }

    private function generateKey(): void
    {
        $key = random_bytes(32); // 256-bit key
        $b64 = base64_encode($key);
        $path = rtrim($this->keyDir, '/') . '/key_' . time() . '.php';
        $tmp = $path . '.tmp';
        $content = "<?php\ndeclare(strict_types=1);\nreturn '" . $b64 . "';\n";
        if (file_put_contents($tmp, $content) === false) {
            throw new Exception('Failed to write temporary key file: ' . $tmp);
        }
        // atomic move
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new Exception('Failed to move key file into place: ' . $path);
        }
        chmod($path, 0600);
        // prune old keys beyond retention
        $this->pruneOldKeys();
    }

    /**
     * Keep newest $retainKeys files and remove older ones.
     */
    private function pruneOldKeys(): void
    {
        $files = array_merge(
            glob(rtrim($this->keyDir, '/') . '/key_*.php') ?: [],
            glob(rtrim($this->keyDir, '/') . '/key_*.bin') ?: []
        );
        if (count($files) <= $this->retainKeys) {
            return;
        }
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        $toDelete = array_slice($files, $this->retainKeys);
        foreach ($toDelete as $f) {
            // only delete if file exists and is writable
            if (is_file($f) && is_writable($f)) {
                @unlink($f);
            }
        }
    }

    private function rotateIfNeeded(): void
    {
        $files = glob(rtrim($this->keyDir, '/') . '/key_*.bin');
        if (!$files) {
            return;
        }
        $newest = array_reduce($files, function ($carry, $item) {
            return $carry === null || filemtime($item) > filemtime($carry) ? $item : $carry;
        }, null);

        if ($newest === null) {
            return;
        }

        $age = time() - filemtime($newest);
        if ($age > $this->rotationInterval) {
            // rotate: generate new key (old keys remain for decryption)
            $this->generateKey();
            // reload
            $this->loadKeys();
        }
    }

    /**
     * get value from session namespace
     */
    public function get(string $key, $default = null)
    {
        return $_SESSION[$this->sessionNamespace][$key] ?? $default;
    }

    // --- Static compatibility proxies ---
    /**
     * Static proxy for get()
     */
    public static function getValue(string $key, $default = null)
    {
        return self::getInstance()->get($key, $default);
    }

    /**
     * Static proxy for set()
     */
    public static function setValue(string $key, $value): void
    {
        self::getInstance()->set($key, $value);
    }

    /**
     * Static proxy for has()
     */
    public static function hasValue(string $key): bool
    {
        return self::getInstance()->has($key);
    }

    /**
     * Static proxy for delete()
     */
    public static function deleteValue(string $key): void
    {
        self::getInstance()->delete($key);
    }

    public function set(string $key, $value): void
    {
        if (!isset($_SESSION[$this->sessionNamespace])) {
            $_SESSION[$this->sessionNamespace] = [];
        }
        $_SESSION[$this->sessionNamespace][$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$this->sessionNamespace]) && array_key_exists($key, $_SESSION[$this->sessionNamespace]);
    }

    public function delete(string $key): void
    {
        if (isset($_SESSION[$this->sessionNamespace][$key])) {
            unset($_SESSION[$this->sessionNamespace][$key]);
        }
    }

    public function destroy(): void
    {
        // clear session array first
        $_SESSION = [];
        // only attempt to clear cookie / destroy if a session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? false);
            }
            @session_destroy();
        }
    }

    public function regenerate(bool $deleteOld = true): void
    {
        // Only regenerate if a session is active. If none is active, try to start one silently.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (session_status() === PHP_SESSION_NONE) {
                // suppress warnings if session cannot be started in this context
                @session_start();
            } else {
                // sessions are disabled or in an unexpected state; skip regenerate
                return;
            }
        }

        // now safe to regenerate id
        @session_regenerate_id($deleteOld);
    }

    /**
     * user id helpers
     */
    public function setUserId(int $id): void
    {
        $this->set('user_id', $id);
    }

    public function getUserId(): ?int
    {
        $v = $this->get('user_id', null);
        return is_int($v) ? $v : (is_numeric($v) ? (int)$v : null);
    }

    /**
     * CSRF token (stored in session namespace)
     */
    public function getCsrfToken(): string
    {
        $token = $this->get('csrf_token');
        if (empty($token)) {
            $token = bin2hex(random_bytes(24));
            $this->set('csrf_token', $token);
        }
        return $token;
    }

    public function validateCsrf(string $token): bool
    {
        $stored = $this->get('csrf_token');
        if (empty($stored)) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    /**
     * マスク（可逆暗号）: AES-256-GCM を使う
     * 出力は URL 安全な base64（+/- を -_ に）
     */
    public function maskId(int $id): string
    {
        $key = $this->keys[0];
        $iv = random_bytes(12);
        $plaintext = (string)$id;
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new Exception('Encryption failed');
        }
        $raw = $iv . $tag . $cipher;
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * アンマスク（複数キーを試す）
     */
    public function unmaskId(string $masked): ?int
    {
        $data = base64_decode(strtr($masked, '-_', '+/'));
        if ($data === false || strlen($data) < 12 + 16) {
            return null;
        }
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $cipher = substr($data, 28);

        foreach ($this->keys as $k) {
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain !== false) {
                if (is_numeric($plain)) {
                    return (int)$plain;
                }
                return null;
            }
        }
        return null;
    }
}
