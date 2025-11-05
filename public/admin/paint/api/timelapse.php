<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Services\TimelapseService;

class TimelapseController extends AdminControllerBase
{
    private ?int $userId = null;

    protected function checkAuthentication(): void
    {
        // Support both session formats
        if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            $this->userId = $_SESSION['admin_user_id'] ?? null;
        } elseif (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) {
            $this->userId = $_SESSION['admin']['id'] ?? null;
        }

        if ($this->userId === null) {
            $this->sendError('Unauthorized', 403);
        }
    }

    protected function onProcess(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $publicRoot = realpath(__DIR__ . '/../../..');  // public ディレクトリ

        $result = TimelapseService::getTimelapseData($id, $publicRoot);

        if (!$result['success']) {
            $statusCode = 400;
            if (strpos($result['error'], 'not found') !== false) {
                $statusCode = 404;
            } else if (strpos($result['error'], 'Server error') !== false) {
                $statusCode = 500;
            }
            $this->sendError($result['error'], $statusCode);
        }

        // Send the result directly (already contains 'success' => true)
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// コントローラーを実行
$controller = new TimelapseController();
$controller->execute();
