<?php

declare(strict_types=1);

namespace Tests\Api;

/**
 * Small integration test that uses the shared IntegrationTestCase utilities.
 */
class AdminIntegrationTest extends IntegrationTestCase
{
    public function testLoginAndUploadFlow(): void
    {
        // GET login page to fetch csrf_token
        $resp = $this->curl('/admin/login.php');
        $this->assertEquals(200, $resp['http_code'], 'Login page did not return 200. Body: ' . substr($resp['output'], 0, 1000));
        $body = $resp['output'];

        // extract csrf_token value
        $m = [];
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $body, $m);
        $this->assertArrayHasKey(1, $m, 'CSRF token not found on login page');
        $csrf = $m[1];

        // Post login (password set in setup is 'testpassword')
        $loginResp = $this->curl('/admin/login.php', ['form' => ['username' => 'admin', 'password' => 'testpassword', 'csrf_token' => $csrf]]);
        $this->assertTrue(in_array($loginResp['http_code'], [200,302]), 'Login POST did not return 200/302. Body: ' . substr($loginResp['output'], 0, 1000));

        // create a small test image
        $img = sys_get_temp_dir() . '/int_test_img.png';
        $im = imagecreatetruecolor(8,8);
        $white = imagecolorallocate($im, 255,255,255);
        imagefill($im,0,0,$white);
        imagepng($im, $img);
        imagedestroy($im);

        // After login, fetch the admin dashboard to confirm authentication and get a fresh CSRF token
        $dashResp = $this->curl('/admin/index.php');
        $this->assertEquals(200, $dashResp['http_code'], 'Dashboard did not return 200 after login. Body: ' . substr($dashResp['output'], 0, 1000));

        // extract csrf_token from dashboard forms (upload form includes it)
        $m2 = [];
        preg_match('/name="csrf_token" value="([a-f0-9]{32,128})"/i', $dashResp['output'], $m2);
        $this->assertArrayHasKey(1, $m2, 'CSRF token not found on dashboard page. Body: ' . substr($dashResp['output'], 0, 1000));
        $csrfDashboard = $m2[1];

        // perform upload via multipart using dashboard CSRF token
        $uploadResp = $this->curl('/admin/api/upload.php', ['upload' => ['image' => $img], 'form' => ['title' => 'Integration Test', 'csrf_token' => $csrfDashboard]]);
        $debugMsg = 'Upload did not return 200. http_code=' . ($uploadResp['http_code'] ?? 'NULL') . ' exec_code=' . ($uploadResp['code'] ?? 'NULL') . ' raw_exec_output=' . substr($uploadResp['raw_exec_output'] ?? '', 0, 4000);
        $this->assertEquals(200, $uploadResp['http_code'], $debugMsg);
        // write response for debugging
        @file_put_contents(self::$tmpDir . '/last_upload_resp.txt', $uploadResp['output']);

        // parse JSON from response body
        $json = trim($uploadResp['output']);
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue((bool)$data['success'], 'Upload API returned success=false. Response: ' . $uploadResp['output']);
        $this->assertArrayHasKey('id', $data);
    }
}
