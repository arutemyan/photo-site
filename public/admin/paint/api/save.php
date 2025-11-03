<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Database\Connection;
use App\Services\IllustService;
use App\Security\CsrfProtection;

initSecureSession();
// Support existing admin session keys used elsewhere in the app
// - Normal app login sets $_SESSION['admin_logged_in']=true with admin_user_id
// - Test helper uses $_SESSION['admin'] for convenience
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

// CSRF validation: accept header X-CSRF-Token or JSON field csrf_token
$rawBody = file_get_contents('php://input');
$raw = json_decode($rawBody, true);
$csrfOk = false;
if (CsrfProtection::validateHeader('X-CSRF-Token')) {
    $csrfOk = true;
} elseif (is_array($raw) && isset($raw['csrf_token']) && CsrfProtection::validateToken($raw['csrf_token'])) {
    $csrfOk = true;
}

if (!$csrfOk) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'CSRF token missing or invalid']);
    exit;
}

$db = Connection::getInstance();
$service = new IllustService($db, __DIR__ . '/../../../uploads');

if (!is_array($raw)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request body']);
    exit;
}

try {
    $result = $service->save([
        'user_id' => $userId,
        // optional id for updates
        'id' => isset($raw['id']) ? (int)$raw['id'] : null,
        'title' => $raw['title'] ?? '',
        'canvas_width' => $raw['canvas_width'] ?? 800,
        'canvas_height' => $raw['canvas_height'] ?? 600,
        'background_color' => $raw['background_color'] ?? '#FFFFFF',
        'illust_json' => $raw['illust_data'] ?? '',
        'image_data' => $raw['image_data'] ?? '',
        'timelapse_data' => isset($raw['timelapse_data']) ? base64_decode(preg_replace('#^data:.*;base64,#', '', $raw['timelapse_data'])) : null,
    ]);

    echo json_encode(['success' => true, 'data' => $result]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
