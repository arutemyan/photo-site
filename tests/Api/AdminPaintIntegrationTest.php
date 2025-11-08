<?php

declare(strict_types=1);

namespace Tests\Api;

/**
 * Integration test for admin paint API endpoints
 * Tests: list.php, save.php, load.php, data.php, palette.php, timelapse.php
 */
class AdminPaintIntegrationTest extends IntegrationTestCase
{
    public function testPaintApiWorkflow(): void
    {
        $csrf = $this->loginAndGetCsrf();

        // Test 1: List paint (should be empty initially)
        $listResp = $this->curl('/admin/paint/api/list.php');
        $this->assertEquals(200, $listResp['http_code']);
        $listData = json_decode($listResp['output'], true);
        $this->assertTrue($listData['success']);
        $this->assertIsArray($listData['data']);
        $this->assertEmpty($listData['data'], 'Initial list should be empty');

        // Test 2: Save a new illust
        $saveData = [
            'title' => 'Test Painting',
            'description' => 'Test description',
            'tags' => 'test,integration',
            'canvas_width' => 800,
            'canvas_height' => 600,
            'background_color' => '#FFFFFF',
            'illust_data' => json_encode([
                'metadata' => [
                    'canvas_width' => 800,
                    'canvas_height' => 600,
                    'background_color' => '#FFFFFF',
                    'version' => 1
                ],
                'layers' => [
                    ['name' => 'Layer 1', 'visible' => true, 'opacity' => 1.0, 'strokes' => []]
                ]
            ]),
            'image_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'csrf_token' => $csrf
        ];

        $saveResp = $this->curlJson('/admin/paint/api/save.php', $saveData, $csrf);

        // If 500 error, print the response for debugging
        if ($saveResp['http_code'] === 500) {
            $this->fail('Save API returned 500: ' . $saveResp['output']);
        }

        $this->assertEquals(200, $saveResp['http_code'], 'Save API failed: ' . $saveResp['output']);
        $saveResult = json_decode($saveResp['output'], true);
        $this->assertTrue($saveResult['success'], 'Save should return success');
        $this->assertArrayHasKey('data', $saveResult);
        $illustId = $saveResult['data']['id'] ?? null;
        $this->assertNotNull($illustId, 'Save should return illust id');

        // Test 3: List paint again (should have 1 item)
        $listResp2 = $this->curl('/admin/paint/api/list.php');
        $this->assertEquals(200, $listResp2['http_code']);
        $listData2 = json_decode($listResp2['output'], true);
        $this->assertTrue($listData2['success']);
        $this->assertCount(1, $listData2['data']);
        $this->assertEquals('Test Painting', $listData2['data'][0]['title']);

        // Test 4: Load illust
        $loadResp = $this->curl('/admin/paint/api/load.php?id=' . $illustId);
        $this->assertEquals(200, $loadResp['http_code']);
        $loadData = json_decode($loadResp['output'], true);
        $this->assertTrue($loadData['success']);
        $this->assertArrayHasKey('data', $loadData);
        $this->assertEquals($illustId, $loadData['data']['id']);
        $this->assertEquals('Test Painting', $loadData['data']['title']);

        // Test 5: Get illust data
        $dataResp = $this->curl('/admin/paint/api/data.php?id=' . $illustId);
        $this->assertEquals(200, $dataResp['http_code']);
        $dataResult = json_decode($dataResp['output'], true);
        $this->assertTrue($dataResult['success']);

        // Test 6: Update existing illust
        $updateData = [
            'id' => $illustId,
            'title' => 'Updated Painting',
            'description' => 'Updated description',
            'tags' => 'test,updated',
            'canvas_width' => 800,
            'canvas_height' => 600,
            'background_color' => '#FFFFFF',
            'illust_data' => json_encode([
                'metadata' => [
                    'canvas_width' => 800,
                    'canvas_height' => 600,
                    'background_color' => '#FFFFFF',
                    'version' => 2
                ],
                'layers' => [
                    ['name' => 'Updated Layer', 'visible' => true, 'opacity' => 1.0, 'strokes' => []]
                ]
            ]),
            'image_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'csrf_token' => $csrf
        ];

        $updateResp = $this->curlJson('/admin/paint/api/save.php', $updateData, $csrf);

        $this->assertEquals(200, $updateResp['http_code'], 'Update API failed: ' . $updateResp['output']);
        $updateResult = json_decode($updateResp['output'], true);
        $this->assertTrue($updateResult['success']);

        // Test 7: Verify update
        $loadResp2 = $this->curl('/admin/paint/api/load.php?id=' . $illustId);
        $this->assertEquals(200, $loadResp2['http_code']);
        $loadData2 = json_decode($loadResp2['output'], true);
        $this->assertTrue($loadData2['success']);
        $this->assertEquals('Updated Painting', $loadData2['data']['title']);

        // Test 8: Invalid ID should return 404
        $loadResp3 = $this->curl('/admin/paint/api/load.php?id=99999');
        $this->assertEquals(404, $loadResp3['http_code']);
        $loadData3 = json_decode($loadResp3['output'], true);
        $this->assertFalse($loadData3['success']);
    }

    public function testPaletteApi(): void
    {
        $csrf = $this->loginAndGetCsrf();

        // Test 1: Get default palette
        $getResp = $this->curl('/admin/paint/api/palette.php');
        $this->assertEquals(200, $getResp['http_code']);
        $getData = json_decode($getResp['output'], true);
        $this->assertTrue($getData['success']);
        $this->assertArrayHasKey('colors', $getData);
        $this->assertIsArray($getData['colors']);
        $this->assertCount(16, $getData['colors']);

        // Test 2: Update palette color
        $updateData = [
            'slot_index' => 0,
            'color' => '#FF0000',
            'csrf_token' => $csrf
        ];

        $updateResp = $this->curlJson('/admin/paint/api/palette.php', $updateData, $csrf);

        $this->assertEquals(200, $updateResp['http_code'], 'Palette update failed: ' . $updateResp['output']);
        $updateResult = json_decode($updateResp['output'], true);
        $this->assertTrue($updateResult['success']);

        // Test 3: Verify color was updated
        $getResp2 = $this->curl('/admin/paint/api/palette.php');
        $this->assertEquals(200, $getResp2['http_code']);
        $getData2 = json_decode($getResp2['output'], true);
        $this->assertTrue($getData2['success']);
        $this->assertEquals('#FF0000', $getData2['colors'][0]);

        // Test 4: Invalid slot index
        $invalidData = [
            'slot_index' => 99,
            'color' => '#00FF00',
            'csrf_token' => $csrf
        ];

        $invalidResp = $this->curlJson('/admin/paint/api/palette.php', $invalidData, $csrf);

        $this->assertEquals(400, $invalidResp['http_code']);
        $invalidResult = json_decode($invalidResp['output'], true);
        $this->assertFalse($invalidResult['success']);

        // Test 5: Invalid color format
        $invalidColorData = [
            'slot_index' => 1,
            'color' => 'red',
            'csrf_token' => $csrf
        ];

        $invalidColorResp = $this->curlJson('/admin/paint/api/palette.php', $invalidColorData, $csrf);

        $this->assertEquals(400, $invalidColorResp['http_code']);
        $invalidColorResult = json_decode($invalidColorResp['output'], true);
        $this->assertFalse($invalidColorResult['success']);
    }

    public function testUnauthorizedAccess(): void
    {
        // Clear cookies to simulate unauthenticated user
        @unlink(self::$cookieJar);

        // All paint API endpoints return 403 for unauthenticated users
        // Test list.php without auth
        $listResp = $this->curl('/admin/paint/api/list.php');
        $this->assertEquals(403, $listResp['http_code']);
        $listData = json_decode($listResp['output'], true);
        $this->assertFalse($listData['success']);

        // Test save.php without auth
        $saveResp = $this->curlJson('/admin/paint/api/save.php', ['title' => 'Test']);
        $this->assertEquals(403, $saveResp['http_code']);

        // Test load.php without auth
        $loadResp = $this->curl('/admin/paint/api/load.php?id=1');
        $this->assertEquals(403, $loadResp['http_code']);

        // Test palette.php without auth (uses default AdminControllerBase auth = 401)
        $paletteResp = $this->curl('/admin/paint/api/palette.php');
        $this->assertEquals(401, $paletteResp['http_code']);
    }

    public function testCsrfProtection(): void
    {
        $this->loginAndGetCsrf();

        // Test save.php without CSRF token
        $saveData = [
            'title' => 'Test',
            'illust_data' => '{}',
            'image_data' => 'data:image/png;base64,test'
        ];

        $saveResp = $this->curlJson('/admin/paint/api/save.php', $saveData);

        $this->assertContains($saveResp['http_code'], [400, 403], 'CSRF validation should fail with 400 or 403');
        $saveResult = json_decode($saveResp['output'], true);
        $this->assertFalse($saveResult['success']);
        $this->assertStringContainsString('CSRF', $saveResult['error']);

        // Test palette.php without CSRF token
        $paletteData = [
            'slot_index' => 0,
            'color' => '#FF0000'
        ];

        $paletteResp = $this->curlJson('/admin/paint/api/palette.php', $paletteData);

        $this->assertContains($paletteResp['http_code'], [400, 403], 'CSRF validation should fail with 400 or 403');
        $paletteResult = json_decode($paletteResp['output'], true);
        $this->assertFalse($paletteResult['success']);
    }

    /**
     * Helper method for JSON POST requests
     */
    private function curlJson(string $url, array $data, ?string $csrfToken = null): array
    {
        $ch = curl_init();
        $urlArg = 'http://127.0.0.1:' . self::$port . $url;
        curl_setopt($ch, CURLOPT_URL, $urlArg);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_COOKIEJAR, self::$cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, self::$cookieJar);

        $headers = ['Content-Type: application/json'];
        if ($csrfToken !== null) {
            $headers[] = 'X-CSRF-Token: ' . $csrfToken;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['output' => $response === false ? '' : $response, 'http_code' => (int)$httpCode];
    }
}
