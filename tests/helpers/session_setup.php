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
// NOTE: This helper is intended to be used from the test suite only, not served by the web server.
$_SESSION['admin'] = ['id' => 1, 'username' => 'testadmin'];

$token = CsrfProtection::getToken();

// Print token for developer consumption
fwrite(STDOUT, json_encode(['csrf_token' => $token], JSON_UNESCAPED_UNICODE));

/**
 * Usage (CLI):
 *   php tests/helpers/session_setup.php
 * This prints a JSON object with a csrf_token. The helper DOES NOT set cookies for the web server; it's
 * intended for use by CLI integration helpers, unit tests, or to show how to generate a token locally.
 */
