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
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

$db = Connection::getInstance();
$illust = new Illust($db);
$row = $illust->findById($id);
if (!$row) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}

// Load illust_data from file if data_path exists
if (!empty($row['data_path'])) {
    // Construct absolute path (data_path is like /uploads/paintfiles/data/...)
    $dataPath = __DIR__ . '/../../..' . $row['data_path'];
    if (file_exists($dataPath)) {
        $illustData = @file_get_contents($dataPath);
        if ($illustData !== false) {
            $row['illust_data'] = $illustData;
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $row]);
