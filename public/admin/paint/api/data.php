<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Database\Connection;

class IllustDataController extends AdminControllerBase
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

        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if (!$id) {
            $this->sendError('Missing id', 400);
        }

        $db = Connection::getInstance();
        $stmt = $db->prepare('SELECT * FROM illusts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->sendError('Not found', 404);
        }

        $dataPath = $row['data_path'] ?? null;
        if (!$dataPath) {
            $this->sendSuccess(['data' => null]);
        }

        // data_path is relative to public (e.g., /uploads/paintfiles/data/...)
        $publicRoot = __DIR__ . '/../../..';
        $abs = $publicRoot . $dataPath;

        if (!file_exists($abs)) {
            error_log("Illust data file not found: $abs (from data_path: $dataPath)");
            $this->sendError('Data file not found', 404);
        }

        $content = @file_get_contents($abs);
        if ($content === false) {
            $this->sendError('Failed to read file', 500);
        }

        $this->sendSuccess(['data' => json_decode($content, true)]);
    }
}

// コントローラーを実行
$controller = new IllustDataController();
$controller->execute();
