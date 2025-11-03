<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Database\Connection;

initSecureSession();

// Admin check - support both session formats
$userId = null;
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $userId = $_SESSION['admin_user_id'] ?? null;
} elseif (!empty($_SESSION['admin']) && is_array($_SESSION['admin'])) {
    $userId = $_SESSION['admin']['id'] ?? null;
}

if ($userId === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id']);
    exit;
}

$db = Connection::getInstance();
$stmt = $db->prepare('SELECT timelapse_path FROM illusts WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['timelapse_path'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Timelapse not found']);
    exit;
}

$path = $row['timelapse_path'];
// timelapse_path is relative to public (e.g., /uploads/paintfiles/timelapse/...)
$publicRoot = __DIR__ . '/../../..';
$abs = $publicRoot . $path;

if (!file_exists($abs)) {
    error_log("Timelapse file not found: $abs (from timelapse_path: $path)");
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Timelapse file not found']);
    exit;
}

// stream binary
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit;
