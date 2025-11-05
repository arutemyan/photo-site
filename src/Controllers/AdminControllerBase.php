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
     * 固有処理（サブクラスで実装）
     */
    abstract protected function onProcess(string $method): void;

    /**
     * セッション初期化
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
            $this->sendError('認証が必要です', 401);
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
    protected function sendSuccess(array $data = []): void
    {
        http_response_code(200);
        echo json_encode(
            array_merge(['success' => true], $data),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    /**
     * エラーレスポンス送信
     */
    protected function sendError(string $error, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        echo json_encode(
            ['success' => false, 'error' => $error],
            JSON_UNESCAPED_UNICODE
        );
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
            'error' => 'サーバーエラーが発生しました'
        ], JSON_UNESCAPED_UNICODE);

        // 詳細なエラー情報はサーバーログのみに記録
        Logger::getInstance()->error(
            'Admin API Error: ' . $e->getMessage() . 
            ' in ' . $e->getFile() . ':' . $e->getLine()
        );
        exit;
    }

    /**
     * POSTパラメータをパース（FormまたはJSON）
     */
    protected function parseFormInput(): array
    {
        // JSONリクエストの場合
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        
        // 通常のPOSTフォーム
        return $_POST;
    }

    /**
     * JSONボディをパース
     */
    protected function parseJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
