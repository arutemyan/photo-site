<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Security/SecurityUtil.php';
require_once __DIR__ . '/../../src/Security/CsrfProtection.php';

use App\Security\CsrfProtection;

// Start secure session and set a test admin user in session for integration tests
initSecureSession();

// Set minimal admin session data for integration testing
$_SESSION['admin'] = ['id' => 1, 'username' => 'testadmin'];

$token = CsrfProtection::getToken();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['csrf_token' => $token], JSON_UNESCAPED_UNICODE);
