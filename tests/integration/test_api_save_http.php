<?php
declare(strict_types=1);

// Self-contained HTTP integration test for CI: it will create a temporary config, run migrations,
// start the built-in PHP server on a free port, create an admin user in the test DB, run the test flow,
// then clean up. This allows CI to run this script directly (php tests/integration/test_api_save_http.php).

// Helpers
function find_free_port(): int
{
    $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($sock === false) {
        throw new RuntimeException('Unable to create temporary socket: ' . $errstr);
    }
    $name = stream_socket_get_name($sock, false);
    fclose($sock);
    $parts = explode(':', $name);
    return (int)array_pop($parts);
}

function run_cmd($cmd, &$output = null): int
{
    exec($cmd, $out, $rc);
    $output = implode("\n", $out);
    return $rc;
}

$projectRoot = realpath(__DIR__ . '/../../');
if ($projectRoot === false) throw new RuntimeException('Cannot determine project root');

$tmpDir = sys_get_temp_dir() . '/photo_site_ci_' . uniqid();
mkdir($tmpDir, 0777, true);

// Prepare test DB path and write a temporary config.local.php
$dbFilename = 'gallery_' . uniqid() . '.db';
$dbPath = $projectRoot . '/tests/tmp_data/' . $dbFilename;
@mkdir(dirname($dbPath), 0777, true);

$configPath = $projectRoot . '/config/config.local.php';
// backup existing config if present
if (file_exists($configPath)) copy($configPath, $configPath . '.bak');

$testConfig = "<?php\ndeclare(strict_types=1);\nreturn [\n    'database' => [\n        'driver' => 'sqlite',\n        'sqlite' => [\n            'gallery' => [\n                'path' => __DIR__ . '/../tests/tmp_data/" . basename($dbPath) . "'\n            ]\n        ]\n    ],\n    'cache' => ['cache_dir' => __DIR__ . '/../tests/tmp_data/cache']\n];\n";
file_put_contents($configPath, $testConfig);

// Run migrations
echo "Running migrations...\n";
$php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$migrateCmd = escapeshellarg($php) . ' ' . escapeshellarg($projectRoot . '/public/setup/run_migrations.php') . ' > ' . escapeshellarg($tmpDir . '/migrations.log') . ' 2>&1';
$rc = run_cmd($migrateCmd, $out);
if ($rc !== 0) {
    echo "Migration failed. Log:\n" . file_get_contents($tmpDir . '/migrations.log') . "\n";
    // restore config
    if (file_exists($configPath . '.bak')) rename($configPath . '.bak', $configPath);
    exit(1);
}

// Create admin user in DB
echo "Creating admin user in test DB...\n";
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password_hash TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
$passwordHash = password_hash('testpassword', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT OR IGNORE INTO users (username, password_hash) VALUES (?, ?)');
$stmt->execute(['admin', $passwordHash]);

// Start built-in server
$port = find_free_port();
$docroot = $projectRoot . '/public';
$logFile = $tmpDir . '/phpserver.log';
$cmd = sprintf('%s -S 127.0.0.1:%d -t %s > %s 2>&1 & echo $!', escapeshellarg($php), $port, escapeshellarg($docroot), escapeshellarg($logFile));
exec($cmd, $out, $rc);
$pid = (int)($out[0] ?? 0);
if ($pid <= 0) {
    echo "Failed to start built-in server. Log:\n" . @file_get_contents($logFile) . "\n";
    if (file_exists($configPath . '.bak')) rename($configPath . '.bak', $configPath);
    exit(1);
}
echo "Started PHP built-in server on port $port (pid=$pid)\n";

// wait for server
$start = time();
$up = false;
while (time() - $start < 10) {
    $headers = @get_headers('http://127.0.0.1:' . $port . '/');
    if ($headers !== false) { $up = true; break; }
    usleep(100000);
}
if (!$up) {
    echo "Server did not respond in time. Log:\n" . @file_get_contents($logFile) . "\n";
    posix_kill($pid, 9);
    if (file_exists($configPath . '.bak')) rename($configPath . '.bak', $configPath);
    exit(1);
}

$base = 'http://127.0.0.1:' . $port;
$cookieFile = $tmpDir . '/cookies.txt';

// HTTP helpers
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

// 1) GET login page to extract CSRF
$loginPage = curl_get($base . '/admin/login.php', $cookieFile);
echo "[DEBUG] login GET http_code=" . $loginPage['http_code'] . "\n";
echo "[DEBUG] login GET snippet=" . substr($loginPage['output'] ?? '', 0, 1000) . "\n";
if ($loginPage['http_code'] !== 200) {
    echo "Login page not available: HTTP " . $loginPage['http_code'] . "\n";
    goto cleanup;
}

$m = [];
preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $loginPage['output'] ?? '', $m);
if (!isset($m[1])) {
    echo "CSRF token not found on login page\n";
    goto cleanup;
}
$loginCsrf = $m[1];

// 2) POST login
$loginResp = curl_post($base . '/admin/login.php', ['username' => 'admin', 'password' => 'testpassword', 'csrf_token' => $loginCsrf], $cookieFile);
echo "[DEBUG] login POST http_code=" . $loginResp['http_code'] . "\n";
echo "[DEBUG] login POST output snippet=" . substr($loginResp['output'] ?? '', 0, 1000) . "\n";
// show cookie contents for debugging
if (file_exists($cookieFile)) {
    echo "[DEBUG] cookieFile path=\"$cookieFile\" size=" . filesize($cookieFile) . "\n";
    echo "[DEBUG] cookieFile contents:\n" . substr(file_get_contents($cookieFile), 0, 2000) . "\n";
} else {
    echo "[DEBUG] cookieFile not found after login\n";
}

if (!in_array($loginResp['http_code'], [200, 302])) {
    echo "Login failed: HTTP " . $loginResp['http_code'] . " error=" . $loginResp['error'] . "\n";
    goto cleanup;
}

// 3) GET dashboard to get session-bound CSRF
$dash = curl_get($base . '/admin/index.php', $cookieFile);
echo "[DEBUG] dashboard GET http_code=" . $dash['http_code'] . "\n";
echo "[DEBUG] dashboard snippet=" . substr($dash['output'] ?? '', 0, 1000) . "\n";
if ($dash['http_code'] !== 200) {
    echo "Dashboard not available after login: HTTP " . $dash['http_code'] . "\n";
    goto cleanup;
}
$m2 = [];
preg_match('/name="csrf_token" value="([a-f0-9]{32,128})"/i', $dash['output'] ?? '', $m2);
if (!isset($m2[1])) {
    echo "CSRF token not found on dashboard\n";
    goto cleanup;
}
$csrf = $m2[1];

// 4) Prepare payload and POST
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

echo "[DEBUG] server log: $logFile\n";
if (file_exists($logFile)) {
    $tail = shell_exec('tail -n 200 ' . escapeshellarg($logFile) . ' 2>/dev/null');
    echo "[DEBUG] server log tail:\n" . ($tail ?: "(empty)\n");
}

$resp = curl_post($base . '/admin/paint/api/save.php', $body, $cookieFile, ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]);
echo "HTTP " . $resp['http_code'] . "\n";
echo "Response: \n" . ($resp['output'] ?? '') . "\n";
$resp = curl_post($base . '/admin/paint/api/save.php', $body, $cookieFile, ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]);
echo "HTTP " . $resp['http_code'] . "\n";
echo "Response: \n" . ($resp['output'] ?? '') . "\n";

cleanup:
// shutdown server and restore config
if (!empty($pid)) {
    posix_kill($pid, 9);
    echo "Killed server pid=$pid\n";
}
if (file_exists($configPath . '.bak')) {
    rename($configPath . '.bak', $configPath);
} else {
    @unlink($configPath);
}
// remove tmp dir only if not asked to keep
if (!getenv('PHOTO_SITE_KEEP_TMP')) {
    $it = new RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) rmdir($file->getRealPath()); else unlink($file->getRealPath());
    }
    @rmdir($tmpDir);
}

echo "Done.\n";
