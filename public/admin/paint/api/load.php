<?php
declare(strict_types=1);


require_once __DIR__ . '/../../../../vendor/autoload.php';

use App\Database\Connection;
use App\Models\Illust;

session_start();
if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

$db = Connection::getInstance();
$illust = new Illust($db);
$row = $illust->findById($id);
if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}

echo json_encode(['success' => true, 'data' => $row]);
