<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Database\Connection;
use App\Services\IllustService;
use App\Security\CsrfProtection;

class IllustSaveController extends AdminControllerBase
{
    private ?int $userId = null;
    private IllustService $illustService;

    public function __construct()
    {
        $db = Connection::getInstance();
        $this->illustService = new IllustService($db, __DIR__ . '/../../../uploads');
    }

    /**
     * 認証チェック（paint API用にuser_idを取得）
     */
    protected function checkAuthentication(): void
    {
        // Support existing admin session keys used elsewhere in the app
        // - Normal app login sets $_SESSION['admin_logged_in']=true with admin_user_id
        // - Test helper uses $_SESSION['admin'] for convenience
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
        if ($method !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }

        $rawBody = file_get_contents('php://input');
        $raw = json_decode($rawBody, true);

        if (!is_array($raw)) {
            $this->sendError('Invalid request body', 400);
        }

        $result = $this->illustService->save([
            'user_id' => $this->userId,
            // optional id for updates
            'id' => isset($raw['id']) ? (int)$raw['id'] : null,
            'title' => $raw['title'] ?? '',
            'description' => $raw['description'] ?? '',
            'tags' => $raw['tags'] ?? '',
            'canvas_width' => $raw['canvas_width'] ?? 800,
            'canvas_height' => $raw['canvas_height'] ?? 600,
            'background_color' => $raw['background_color'] ?? '#FFFFFF',
            'illust_json' => $raw['illust_data'] ?? '',
            'image_data' => $raw['image_data'] ?? '',
            'timelapse_data' => isset($raw['timelapse_data']) ? base64_decode(preg_replace('#^data:.*;base64,#', '', $raw['timelapse_data'])) : null,
        ]);

        $this->sendSuccess(['data' => $result]);
    }
}

// コントローラーを実行
$controller = new IllustSaveController();
$controller->execute();
