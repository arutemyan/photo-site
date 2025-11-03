<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected static string $tmpDir;
    protected static string $origConfigPath;
    protected static ?int $serverPid = null;
    protected static string $cookieJar;
    protected static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tmpDir = sys_get_temp_dir() . '/photo_site_integration_' . uniqid();
        mkdir(self::$tmpDir, 0777, true);

        $projectRoot = realpath(__DIR__ . '/../../');
        if ($projectRoot === false) {
            throw new \RuntimeException('Cannot determine project root');
        }

        $repoPublic = $projectRoot . '/public';
        @mkdir($repoPublic . '/uploads/images', 0777, true);
        @mkdir($repoPublic . '/uploads/thumbs', 0777, true);

        // backup existing config.local.php if present
        $configPath = $projectRoot . '/config/config.local.php';
        self::$origConfigPath = $configPath;
        if (file_exists($configPath)) {
            copy($configPath, $configPath . '.bak');
        }

        // prepare tests tmp_data under project root
        $testsTmp = $projectRoot . '/tests/tmp_data';
        @mkdir($testsTmp, 0777, true);

        // use a unique DB filename for this test run to avoid collisions
        $dbFilename = 'gallery_' . uniqid() . '.db';
        $dbPath = $projectRoot . '/tests/tmp_data/' . $dbFilename;

        // write test config.local to point DB to tmp_data (unique filename)
        $testConfig = "<?php\n" .
            "declare(strict_types=1);\n" .
            "return [\n" .
            "    'database' => [\n" .
            "        'driver' => 'sqlite',\n" .
            "        'sqlite' => [\n" .
            "            'gallery' => [\n" .
            "                'path' => __DIR__ . '/../tests/tmp_data/" . $dbFilename . "'\n" .
            "            ]\n" .
            "        ]\n" .
            "    ],\n" .
            "    'cache' => [\n" .
            "        'cache_dir' => __DIR__ . '/../tests/tmp_data/cache'\n" .
            "    ]\n" .
            "];\n";

        file_put_contents($configPath, $testConfig);

        // Note: migrations are run by CI during setup (see .github/workflows/phpunit.yml).
        // For local runs we avoid running the full migration runner here because
        // it can conflict with other test processes (SQLite locking). The
        // Connection::initializeSchema() will create the minimal schema needed
        // for tests and we create the admin user below.
        \App\Database\Connection::setDatabasePath($dbPath);

        // find a free TCP port
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            throw new \RuntimeException('Unable to create temporary socket to find free port: ' . $errstr);
        }
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        $parts = explode(':', $name);
        $port = (int)array_pop($parts);
        if ($port <= 0) {
            throw new \RuntimeException('Failed to determine free port');
        }
        self::$port = $port;

        // Start built-in PHP server
        $docroot = realpath($projectRoot . '/public');
        if ($docroot === false) {
            throw new \RuntimeException('Public docroot not found: ' . $projectRoot . '/public');
        }

        $logFile = self::$tmpDir . '/phpserver.log';
        @mkdir(dirname($logFile), 0777, true);

        $cmd = sprintf('php -S 127.0.0.1:%d -t %s > %s 2>&1 & echo $!', self::$port, escapeshellarg($docroot), escapeshellarg($logFile));
        $output = [];
        exec($cmd, $output);
        $pid = (int)($output[0] ?? 0);
        if ($pid <= 0) {
            $log = @file_get_contents($logFile);
            throw new \RuntimeException('Failed to start php built-in server (no pid). Log: ' . substr((string)$log, 0, 2000));
        }
        self::$serverPid = $pid;

        // wait for server to respond
        $start = time();
        $up = false;
        while (time() - $start < 10) {
            $headers = @get_headers('http://127.0.0.1:' . self::$port . '/');
            if ($headers !== false) { $up = true; break; }
            usleep(100000);
        }
        if (!$up) {
            $log = @file_get_contents($logFile);
            self::tearDownAfterClass();
            throw new \RuntimeException('Built-in server did not respond in time. Log: ' . substr((string)$log, 0, 2000));
        }

        // cookie jar
        self::$cookieJar = self::$tmpDir . '/cookies.txt';
        @touch(self::$cookieJar);
        @chmod(self::$cookieJar, 0666);

        // ensure DB and admin user
        $dbDir = dirname($dbPath);
        @mkdir($dbDir, 0777, true);

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password_hash TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
        $passwordHash = password_hash('testpassword', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $stmt->execute(['admin', $passwordHash]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverPid) {
            exec('kill ' . (int)self::$serverPid);
        }

        $configPath = self::$origConfigPath;
        if (file_exists($configPath . '.bak')) {
            rename($configPath . '.bak', $configPath);
        } else {
            @unlink($configPath);
        }

        if (file_exists(self::$tmpDir)) {
            $it = new \RecursiveDirectoryIterator(self::$tmpDir, \FilesystemIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) rmdir($file->getRealPath()); else unlink($file->getRealPath());
            }
            @rmdir(self::$tmpDir);
        }

        parent::tearDownAfterClass();
    }

    protected function curl(string $url, array $options = []): array
    {
        $ch = curl_init();
        $urlArg = 'http://127.0.0.1:' . self::$port . $url;
        curl_setopt($ch, CURLOPT_URL, $urlArg);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        curl_setopt($ch, CURLOPT_COOKIEJAR, self::$cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, self::$cookieJar);

        if (!empty($options['form']) || !empty($options['upload'])) {
            $postFields = [];
            if (!empty($options['form'])) {
                foreach ($options['form'] as $k => $v) {
                    // keep arrays as-is for multipart, but detect below
                    $postFields[$k] = $v;
                }
            }
            if (!empty($options['upload'])) {
                foreach ($options['upload'] as $field => $path) {
                    if (file_exists($path)) {
                        $postFields[$field] = new \CURLFile($path);
                    }
                }
            }

            curl_setopt($ch, CURLOPT_POST, true);

            // If there are no files and the form contains an _method override,
            // send as application/x-www-form-urlencoded raw body so server-side
            // parse_str(file_get_contents('php://input')) can decode it (used by some endpoints).
            $hasFiles = false;
            foreach ($postFields as $v) {
                if ($v instanceof \CURLFile) { $hasFiles = true; break; }
            }

            $forceUrlEncoded = !$hasFiles && isset($options['form']) && array_key_exists('_method', $options['form']);
            if ($forceUrlEncoded) {
                $body = http_build_query($postFields);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } else {
                // let curl handle array -> multipart or urlencoded as appropriate
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            }
        }

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['output' => $response === false ? '' : $response, 'http_code' => (int)$httpCode, 'code' => $errno, 'raw_exec_output' => $err];
    }

    /**
     * Log in as the admin user created in setUpBeforeClass and return dashboard CSRF token.
     */
    protected function loginAndGetCsrf(): string
    {
        // GET login page
        $resp = $this->curl('/admin/login.php');
        if ($resp['http_code'] !== 200) {
            throw new \RuntimeException('Login page not available: HTTP ' . $resp['http_code']);
        }
        $m = [];
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $resp['output'], $m);
        if (!isset($m[1])) {
            throw new \RuntimeException('CSRF token not found on login page');
        }
        $loginToken = $m[1];

        // POST login
        $loginResp = $this->curl('/admin/login.php', ['form' => ['username' => 'admin', 'password' => 'testpassword', 'csrf_token' => $loginToken]]);
        if (!in_array($loginResp['http_code'], [200, 302])) {
            throw new \RuntimeException('Login failed: HTTP ' . $loginResp['http_code']);
        }

        // GET dashboard to obtain session-bound CSRF
        $dash = $this->curl('/admin/index.php');
        if ($dash['http_code'] !== 200) {
            throw new \RuntimeException('Dashboard not available after login: HTTP ' . $dash['http_code']);
        }
        $m2 = [];
        preg_match('/name="csrf_token" value="([a-f0-9]{32,128})"/i', $dash['output'], $m2);
        if (!isset($m2[1])) {
            throw new \RuntimeException('CSRF token not found on dashboard');
        }
        return $m2[1];
    }
}
