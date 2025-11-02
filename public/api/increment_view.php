<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Post;
use App\Models\GroupPost;
use App\Security\RateLimiter;

header('Content-Type: application/json; charset=utf-8');

// レート制限（1分間に100リクエストまで）
$rateLimiter = new RateLimiter(__DIR__ . '/../../data/rate-limits', 100, 60);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!$rateLimiter->check($clientIp, 'api_increment_view')) {
    http_response_code(429);
    $retryAfter = $rateLimiter->getRetryAfter($clientIp, 'api_increment_view');
    if ($retryAfter) {
        header('Retry-After: ' . ($retryAfter - time()));
    }
    echo json_encode(['success' => false, 'error' => 'Too many requests']);
    exit;
}

$rateLimiter->record($clientIp, 'api_increment_view');

try {
    $postId = $_POST['id'] ?? $_GET['id'] ?? null;
    $viewType = $_POST['viewtype'] ?? $_GET['viewtype'] ?? 0;

    if (empty($postId) || !is_numeric($postId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
        exit;
    }

    if (!is_numeric($viewType)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid view type']);
        exit;
    }

    $viewType = (int)$viewType;

    // viewTypeに応じて適切なモデルを使用（0=single, 1=group）
    if ($viewType === 1) {
        $model = new GroupPost();
    } else {
        $model = new Post();
    }

    $success = $model->incrementViewCount((int)$postId);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Post not found']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
    error_log('Increment View Error: ' . $e->getMessage());
}
