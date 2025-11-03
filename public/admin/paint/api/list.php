<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Database\Connection;
use App\Models\Illust;

initSecureSession();

// Admin check - support both session formats
$userId = null;
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $userId = $_SESSION['admin_user_id'] ?? null;
} elseif (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) {
    $userId = $_SESSION['admin']['id'] ?? null;
}

if ($userId === null) {
    error_log('list.php: Unauthorized - Session data: ' . print_r($_SESSION, true));
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$db = Connection::getInstance();
$illust = new Illust($db);
$rows = $illust->listByUser((int)$userId, $limit, $offset);

// Format response with proper image paths
$formatted = array_map(function($row) {
    return [
        'id' => $row['id'],
        'title' => $row['title'] ?? 'Untitled',
        'created_at' => $row['created_at'] ?? '',
        'updated_at' => $row['updated_at'] ?? '',
        'image_path' => isset($row['image_path']) ? '/' . ltrim($row['image_path'], '/') : null,
        'thumbnail_path' => isset($row['thumbnail_path']) ? '/' . ltrim($row['thumbnail_path'], '/') : null,
        'canvas_width' => $row['canvas_width'] ?? 512,
        'canvas_height' => $row['canvas_height'] ?? 512
    ];
}, $rows);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $formatted]);
