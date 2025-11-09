<?php
/**
 * タイムラプス取得API（公開用）
 * public/paint/ 専用
 */

require_once(__DIR__ . '/../../../vendor/autoload.php');
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Controllers\PublicControllerBase;
use App\Services\TimelapseService;

class TimelapsePublicController extends PublicControllerBase
{
    protected bool $startSession = false;
    protected bool $allowCors = false;

    protected function onProcess(string $method): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $publicRoot = realpath(__DIR__ . '/../..');  // public ディレクトリ

        $result = TimelapseService::getTimelapseData($id, $publicRoot);

        if (!$result['success']) {
            $statusCode = 400;
            if (strpos($result['error'], 'not found') !== false) {
                $statusCode = 404;
            } elseif (strpos($result['error'], 'Server error') !== false) {
                $statusCode = 500;
            }
            $this->sendError($result['error'], $statusCode, ['details' => $result]);
            return;
        }

        $this->sendSuccess($result);
    }
}

try {
    $controller = new TimelapsePublicController();
    $controller->execute();
} catch (Exception $e) {
    PublicControllerBase::handleException($e);
}
