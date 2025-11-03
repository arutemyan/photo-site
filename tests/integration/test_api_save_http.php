<?php
declare(strict_types=1);

// Integration test that follows the same admin login -> dashboard CSRF flow as the PHPUnit IntegrationTestCase.
// Usage: ensure a local built-in server is running at the $base URL (default http://127.0.0.1:8001)

$base = getenv('BASE_URL') ?: 'http://127.0.0.1:8001';
$cookieFile = sys_get_temp_dir() . '/paint_test_cookie_' . uniqid() . '.txt';
@unlink($cookieFile);

function curl_get(string $url, string $cookieFile): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['output' => $res === false ? '' : $res, 'http_code' => (int)$code, 'error' => $err];
}

function curl_post(string $url, $postFields, string $cookieFile, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['output' => $res === false ? '' : $res, 'http_code' => (int)$code, 'error' => $err];
}

echo "Using base URL: $base\n";

// 1) GET login page to extract initial CSRF
$loginPage = curl_get($base . '/admin/login.php', $cookieFile);
if ($loginPage['http_code'] !== 200) {
    echo "Login page not available: HTTP " . $loginPage['http_code'] . "\n";
    exit(1);
}

$m = [];
preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $loginPage['output'] ?? '', $m);
if (!isset($m[1])) {
    echo "CSRF token not found on login page\n";
    exit(1);
}
$loginCsrf = $m[1];

// 2) POST login (test user created by IntegrationTestCase uses password 'testpassword')
$loginResp = curl_post($base . '/admin/login.php', ['username' => 'admin', 'password' => 'testpassword', 'csrf_token' => $loginCsrf], $cookieFile);
if (!in_array($loginResp['http_code'], [200, 302])) {
    echo "Login failed: HTTP " . $loginResp['http_code'] . " error=" . $loginResp['error'] . "\n";
    exit(1);
}

// 3) GET dashboard to get session-bound CSRF
$dash = curl_get($base . '/admin/index.php', $cookieFile);
if ($dash['http_code'] !== 200) {
    echo "Dashboard not available after login: HTTP " . $dash['http_code'] . "\n";
    exit(1);
}
$m2 = [];
preg_match('/name="csrf_token" value="([a-f0-9]{32,128})"/i', $dash['output'] ?? '', $m2);
if (!isset($m2[1])) {
    echo "CSRF token not found on dashboard\n";
    exit(1);
}
$csrf = $m2[1];

// 4) Prepare payload and POST to save API with header X-CSRF-Token and JSON body
$illust = [
    'version' => '1.0',
    'metadata' => ['canvas_width' => 64, 'canvas_height' => 64, 'background_color' => '#FFFFFF'],
    'layers' => [[ 'id' => 'layer_0', 'name' => 'bg', 'order' => 0, 'visible' => true, 'opacity' => 1.0, 'type' => 'raster', 'data' => '', 'width' => 64, 'height' => 64 ]],
    'timelapse' => ['enabled' => false]
];

$payload = [
    'title' => 'HTTP integration test',
    'canvas_width' => 64,
    'canvas_height' => 64,
    'background_color' => '#FFFFFF',
    'illust_data' => json_encode($illust),
    'image_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==',
    'timelapse_data' => 'data:application/octet-stream;base64,' . base64_encode(gzencode(''))
];

$body = json_encode($payload);

echo "Posting to save API...\n";
$resp = curl_post($base . '/admin/paint/api/save.php', $body, $cookieFile, ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]);
echo "HTTP " . $resp['http_code'] . "\n";
echo "Response: \n" . ($resp['output'] ?? '') . "\n";

// cleanup
@unlink($cookieFile);

echo "Integration test finished.\n";
