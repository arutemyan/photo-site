<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Database\Connection;
use App\Models\Paint;

class PaintListController extends AdminControllerBase
{
    private Paint $paintModel;
    private ?int $userId = null;

    public function __construct()
    {
        $db = Connection::getInstance();
        $this->paintModel = new Paint($db);
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

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $rows = $this->paintModel->listByUser((int)$this->userId, $limit, $offset);

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

        $this->sendSuccess(['data' => $formatted]);
    }
}

// コントローラーを実行
$controller = new PaintListController();
$controller->execute();
