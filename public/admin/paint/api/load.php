<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Database\Connection;
use App\Models\Illust;

class IllustLoadController extends AdminControllerBase
{
    private Illust $illustModel;
    private ?int $userId = null;

    public function __construct()
    {
        $db = Connection::getInstance();
        $this->illustModel = new Illust($db);
    }

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

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $this->sendError('Invalid id', 400);
        }

        $row = $this->illustModel->findById($id);
        if (!$row) {
            $this->sendError('Not found', 404);
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

        $this->sendSuccess(['data' => $row]);
    }
}

// コントローラーを実行
$controller = new IllustLoadController();
$controller->execute();
