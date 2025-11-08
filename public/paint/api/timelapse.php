<?php
/**
 * タイムラプス取得API（公開用）
 * public/paint/ 専用
 */

require_once(__DIR__ . '/../../../vendor/autoload.php');
require_once(__DIR__ . '/../../../config/config.php');
// feature gate
require_once(__DIR__ . '/../_feature_check.php');

use App\Services\TimelapseService;

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$publicRoot = realpath(__DIR__ . '/../..');  // public ディレクトリ

$result = TimelapseService::getTimelapseData($id, $publicRoot);

if (!$result['success']) {
    $statusCode = 400;
    if (strpos($result['error'], 'not found') !== false) {
        $statusCode = 404;
    } else if (strpos($result['error'], 'Server error') !== false) {
        $statusCode = 500;
    }
    http_response_code($statusCode);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
