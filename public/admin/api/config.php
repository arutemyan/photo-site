<?php
/**
 * Admin Configuration API
 * 
 * Provides configuration values to admin interface via API
 * This eliminates the need for inline scripts with configuration data
 * Part of CSP unsafe-inline removal strategy
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Security\CsrfProtection;
use App\Utils\PathHelper;

class ConfigController extends AdminControllerBase
{
    protected function onProcess(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendError('許可されていないメソッドです', 405);
            return;
        }

        $this->handleGet();
    }

    private function handleGet(): void
    {
        // CSRF トークンを生成
        $csrfToken = CsrfProtection::generateToken();

        // 管理画面パスを取得
        $adminPath = PathHelper::getAdminPath();

        // ユーザー名を取得
        $username = 'Admin';
        try {
            if (class_exists('\App\\Services\\Session')) {
                $username = \App\Services\Session::getInstance()->get('admin_username', $username);
            } else {
                $username = $_SESSION['admin_username'] ?? $username;
            }
        } catch (Throwable $e) {
            $username = $_SESSION['admin_username'] ?? $username;
        }

        // 設定データを返す
        $this->sendSuccess([
            'csrfToken' => $csrfToken,
            'adminPath' => $adminPath,
            'username' => $username,
        ]);
    }
}

// コントローラーを実行
$controller = new ConfigController();
$controller->execute();
