<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\CsrfProtection;
use App\Security\FeatureDisabledException;
use App\Services\Session;
use Exception;

/**
 * 管理画面API共通基底クラス
 * 
 * すべての管理画面APIの共通処理をまとめる
 * 継承クラスはonProcessメソッドで固有処理を実装する
 */
abstract class AdminControllerBase extends ControllerBase
{
    private ?int $userId = null;

    public function getUserId(): ?int {
        return $this->userId;
    }

    /**
     * 共通認証チェックユーティリティ
     * ページ用にリダイレクトするか、API用にリダイレクトしないかを切り替え可能
     *
     * @param bool $redirect If true and not authenticated, redirect to login page and exit.
     * @return int|null returns user id when authenticated, or null when not authenticated
     */
    public static function ensureAuthenticated(bool $redirect = true): ?int
    {
        \App\Services\Session::start();

        $userId = null;
        $sess = \App\Services\Session::getInstance();
        if ($sess->get('admin_logged_in', null) === true) {
            $userId = $sess->get('admin_user_id', null);
        }
        if ($userId === null && $redirect) {
            // redirect to login page
            if (!headers_sent()) {
                $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
                header($protocol . ' 302 Found');
            }
            $login = '/admin/login.php';
            // PathHelper may not be available here in some includes; keep simple
            header('Location: ' . $login);
            exit;
        }

        return $userId;
    }

    /**
     * エントリーポイント
     * 共通処理を実行してからonProcessを呼び出す
     */
    public function execute(): void
    {
        try {
            try {
                \App\Utils\FeatureGate::ensureEnabled('admin');
            } catch (FeatureDisabledException $e) {
                if (!headers_sent()) {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
                }
                echo '404 Not Found';
                exit;
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
        // Use parent implementation from ControllerBase
        parent::initSession();
    }

    /**
     * 認証チェック
     */
    protected function checkAuthentication(): void
    {
        $sess = Session::getInstance();
        $loggedIn = $sess->get('admin_logged_in', null);

        // Fallback to raw $_SESSION
        if ($loggedIn === null) {
            $loggedIn = $_SESSION['admin_logged_in'] ?? null;
        }

        if ($loggedIn !== true) {
            // For many admin APIs we want to return 403 for unauthenticated
            // requests; controllers that require a 401 can override this
            // behavior by implementing their own checkAuthentication().
            $this->sendError('Unauthorized', 403);
        }

        $this->userId = $sess->get('admin_user_id', null);
        if ($this->userId === null) {
            $this->sendError('Unauthorized', 403);
        }
    }

    /**
     * CSRFトークン検証
     */
    protected function validateCsrf(): void
    {
        $session = Session::getInstance();
        // check POST param first, then common header names
        $token = $_POST['csrf_token'] ?? null;
        if (empty($token)) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_XSRF_TOKEN'] ?? null);
        }
        if (!empty($token) && $session->validateCsrf($token)) {
            return;
        }
        if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
            $this->logSecurityEvent('CSRF token validation failed');
            $this->sendError('CSRFトークンが無効です', 403);
        }
    }

    // The common helpers for JSON responses, input parsing, session bootstrap
    // and error handling are provided by ControllerBase.
}
