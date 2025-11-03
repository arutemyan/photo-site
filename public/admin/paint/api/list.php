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

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$db = Connection::getInstance();
$illust = new Illust($db);
$rows = $illust->listByUser((int)$_SESSION['admin']['id'], $limit, $offset);

echo json_encode(['success' => true, 'data' => $rows]);
