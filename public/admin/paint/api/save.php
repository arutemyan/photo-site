<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Database\Connection;
use App\Services\IllustService;
use App\Security\CsrfProtection;
use App\Services\Session;

class IllustSaveController extends AdminControllerBase
{
    private IllustService $illustService;

    public function __construct()
    {
        $db = Connection::getInstance();
        $this->illustService = new IllustService($db, __DIR__ . '/../../../uploads');
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

        // Wrap processing in try/catch so unexpected errors always return JSON
        try {

            // Log whether timelapse_data was provided (helpful for debugging missing timelapse saves)
            try {
                if (isset($raw['timelapse_data']) && $raw['timelapse_data']) {
                    $len = is_string($raw['timelapse_data']) ? strlen($raw['timelapse_data']) : 0;
                    \App\Utils\Logger::getInstance()->warning('IllustSaveController: timelapse_data present in payload, encoded length=' . $len);
                } else {
                    \App\Utils\Logger::getInstance()->warning('IllustSaveController: no timelapse_data present in save payload');
                }
            } catch (\Throwable $e) {
                // ignore logging issues
            }

            $forceNew = false;
            if (!empty($raw['forceNew']) || !empty($raw['force_new'])) {
                $forceNew = true;
            }

            $id = isset($raw['id']) ? (int)$raw['id'] : null;
            if ($forceNew) {
                // client asked to force creation of a new record
                $id = null;
            }

            $result = $this->illustService->save([
                'user_id' => $this->getUserId(),
                // optional id for updates (null means create)
                'id' => $id,
                'title' => $raw['title'] ?? '',
                'description' => $raw['description'] ?? '',
                'tags' => $raw['tags'] ?? '',
                'canvas_width' => $raw['canvas_width'] ?? 800,
                'canvas_height' => $raw['canvas_height'] ?? 600,
                'background_color' => $raw['background_color'] ?? '#FFFFFF',
                'illust_json' => $raw['illust_data'] ?? '',
                'image_data' => $raw['image_data'] ?? '',
                'timelapse_data' => isset($raw['timelapse_data']) ? base64_decode(preg_replace('#^data:.*;base64,#', '', $raw['timelapse_data'])) : null,
                // new flags (defaults applied server-side if omitted)
                'nsfw' => isset($raw['nsfw']) ? (int)$raw['nsfw'] : null,
                'is_visible' => isset($raw['is_visible']) ? (int)$raw['is_visible'] : null,
            ]);

            $this->sendSuccess(['data' => $result]);
        } catch (\Throwable $e) {
            // Ensure we always return JSON to the client; log the error for debugging.
            try {
                \App\Utils\Logger::getInstance()->error('IllustSaveController exception: ' . $e->getMessage(), ['exception' => $e]);
            } catch (\Throwable $_) {
                // ignore logging errors
            }

            $this->sendError('Internal server error', 500);
        }
    }
}

// コントローラーを実行
$controller = new IllustSaveController();
$controller->execute();
