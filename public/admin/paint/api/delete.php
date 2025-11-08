<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Database\Connection;
use App\Models\Paint;
use App\Services\Session;
use App\Utils\Logger;

class PaintDeleteController extends AdminControllerBase
{
    private Paint $paintModel;

    public function __construct()
    {
        $db = Connection::getInstance();
        $this->paintModel = new Paint($db);
    }

    protected function onProcess(string $method): void
    {
        if ($method !== 'DELETE') {
            $this->sendError('Method not allowed', 405);
        }

        // Read paint ID from request body (DELETE requests)
        $paintId = 0;

        // Try to get from php://input (FormData or JSON)
        $input = file_get_contents('php://input');

        // Try to parse as FormData (multipart/form-data)
        if (!empty($input) && strpos($input, 'Content-Disposition: form-data') !== false) {
            // Parse multipart form data
            if (preg_match('/name="id"\s+(\d+)/s', $input, $matches)) {
                $paintId = (int)$matches[1];
            }
        } else {
            // Try to parse as URL-encoded or plain text
            parse_str($input, $data);
            if (isset($data['id'])) {
                $paintId = (int)$data['id'];
            }
        }

        // Fallback to GET/POST if still not found
        if ($paintId <= 0) {
            $paintId = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        }

        if ($paintId <= 0) {
            $this->sendError('Invalid paint ID', 400);
        }

        // Get paint record
        $paint = $this->paintModel->findById($paintId);
        if ($paint === null) {
            $this->sendError('Paint not found', 404);
        }

        // Verify ownership
        if ((int)$paint['user_id'] !== $this->getUserId()) {
            $this->sendError('You do not have permission to delete this paint', 403);
        }

        // Delete associated files
        $uploadsDir = realpath(__DIR__ . '/../../../uploads/');
        $paintDir = realpath(__DIR__ . '/../../../paint/');

        // Helper function to safely delete files
        $safeDelete = function($filePath, $baseDir) {
            if (empty($filePath)) {
                return;
            }

            $fullPath = realpath(__DIR__ . '/../../../' . ltrim($filePath, '/'));

            // Verify path is within allowed directory and file exists
            if ($fullPath && $baseDir && strpos($fullPath, $baseDir) === 0 && file_exists($fullPath)) {
                unlink($fullPath);
            } elseif (!empty($filePath)) {
                Logger::getInstance()->warning('Invalid file path attempted for deletion: ' . $filePath);
            }
        };

        // Delete image files
        $safeDelete($paint['image_path'], $uploadsDir);
        $safeDelete($paint['thumbnail_path'], $uploadsDir);

        // Delete paint data files
        $safeDelete($paint['data_path'], $paintDir);
        $safeDelete($paint['timelapse_path'], $paintDir);

        // Delete from database
        $this->paintModel->delete($paintId);

        $this->sendSuccess(['message' => 'Paint deleted successfully']);
    }
}

// Execute controller
$controller = new PaintDeleteController();
$controller->execute();
