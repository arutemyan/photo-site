<?php

declare(strict_types=1);

namespace Tests\Api;

class AdminSettingsIntegrationTest extends IntegrationTestCase
{
    public function testGetAndUpdateSettings(): void
    {
        $csrf = $this->loginAndGetCsrf();

        // GET settings
        $get = $this->curl('/admin/api/settings.php');
        $this->assertEquals(200, $get['http_code']);
        $gd = json_decode($get['output'], true);
        $this->assertTrue($gd['success']);

        // POST update settings
        $post = $this->curl('/admin/api/settings.php', ['form' => ['csrf_token' => $csrf, 'ogp_title' => 'Integration OGP Title']]);
        $this->assertEquals(200, $post['http_code']);
        $pd = json_decode($post['output'], true);
        $this->assertTrue($pd['success']);
    }
}
