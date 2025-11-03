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
$stmt = $db->prepare('SELECT * FROM illusts WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}

$dataPath = $row['data_path'] ?? null;
if (!$dataPath) {
    echo json_encode(['success' => true, 'data' => null]);
    exit;
}

// data_path is relative to public (e.g., /uploads/paintfiles/data/...)
$publicRoot = __DIR__ . '/../../..';
$abs = $publicRoot . $dataPath;

if (!file_exists($abs)) {
    error_log("Illust data file not found: $abs (from data_path: $dataPath)");
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Data file not found']);
    exit;
}

$content = @file_get_contents($abs);
if ($content === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to read file']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'data' => json_decode($content, true)]);
