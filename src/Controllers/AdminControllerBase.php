<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\CsrfProtection;
use App\Utils\Logger;
use Exception;

/**
 * 管理画面API共通基底クラス
 * 
 * すべての管理画面APIの共通処理をまとめる
 * 継承クラスはonProcessメソッドで固有処理を実装する
 */
abstract class AdminControllerBase
{
    /**
     * エントリーポイント
     * 共通処理を実行してからonProcessを呼び出す
     */
    public function execute(): void
    {
        try {
            // feature gate: ensure admin feature enabled
            if (class_exists('\App\\Utils\\FeatureGate')) {
                \App\Utils\FeatureGate::ensureEnabled('admin');
            } else {
                // fallback: check config directly
                $configPath = __DIR__ . '/../../config/config.php';
                if (file_exists($configPath)) {
                    $cfg = require $configPath;
                    if (isset($cfg['admin']) && array_key_exists('enabled', $cfg['admin']) && !$cfg['admin']['enabled']) {
                        if (!headers_sent()) {
                            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
                        }
                        echo '404 Not Found';
                        exit;
                    }
                }
            }
            // セッション開始
            $this->initSession();

            // 認証チェック
            $this->checkAuthentication();

            // JSONレスポンスヘッダー設定
            $this->setJsonHeader();

            // HTTPメソッド取得
            $method = $this->getHttpMethod();

            // CSRFトークン検証（GET以外）
            if ($method !== 'GET') {
                $this->validateCsrf();
            }

            // 固有処理を実行
            $this->onProcess($method);

        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * 固有処理（継承先で実装）
     * 
     * @param string $method HTTPメソッド（GET, POST, PUT, DELETE, PATCH等）
     */
    abstract protected function onProcess(string $method): void;

    /**
     * セッション開始
     */
    protected function initSession(): void
    {
        if (function_exists('initSecureSession')) {
            initSecureSession();
        } elseif (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * 認証チェック
     */
    protected function checkAuthentication(): void
    {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            $this->sendError('Unauthorized', 401);
        }
    }

    /**
     * JSONレスポンスヘッダー設定
     */
    protected function setJsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * HTTPメソッド取得（_methodパラメータ対応）
     */
    protected function getHttpMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // POSTで_methodが指定されている場合はそれを使う
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        
        return $method;
    }

    /**
     * CSRFトークン検証
     */
    protected function validateCsrf(): void
    {
        if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
            $this->logSecurityEvent('CSRF token validation failed');
            $this->sendError('CSRFトークンが無効です', 403);
        }
    }

    /**
     * セキュリティイベントログ記録
     */
    protected function logSecurityEvent(string $message, array $context = []): void
    {
        if (function_exists('logSecurityEvent')) {
            $defaultContext = ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'];
            logSecurityEvent($message, array_merge($defaultContext, $context));
        }
    }

    /**
     * 成功レスポンス送信
     */
    protected function sendSuccess(array $data = [], int $statusCode = 200): void
    {
        if ($statusCode !== 200) {
            http_response_code($statusCode);
        }
        
        $response = array_merge(['success' => true], $data);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * エラーレスポンス送信
     */
    protected function sendError(string $error, int $statusCode = 400, array $additionalData = []): void
    {
        http_response_code($statusCode);
        
        $response = array_merge(['success' => false, 'error' => $error], $additionalData);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * エラーハンドリング
     */
    protected function handleError(Exception $e): void
    {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error'
        ], JSON_UNESCAPED_UNICODE);
        
        Logger::getInstance()->error(get_class($this) . ' Error: ' . $e->getMessage());
        exit;
    }

    /**
     * JSONデコード（PUT/PATCHリクエスト用）
     */
    protected function parseJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return $data ?? [];
    }

    /**
     * parse_strでデコード（PUT/PATCHリクエスト用）
     */
    protected function parseFormInput(): array
    {
        $input = file_get_contents('php://input');
        parse_str($input, $data);
        return $data;
    }
}
