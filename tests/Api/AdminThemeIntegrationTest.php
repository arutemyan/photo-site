<?php

declare(strict_types=1);

namespace Tests\Api;

class AdminThemeIntegrationTest extends IntegrationTestCase
{
    public function testGetAndUpdateTheme(): void
    {
        $csrf = $this->loginAndGetCsrf();

        // GET theme
        $get = $this->curl('/admin/api/theme.php');
        $this->assertEquals(200, $get['http_code']);
        $gd = json_decode($get['output'], true);
        $this->assertTrue($gd['success']);

        // Update theme via POST _method=PUT
        $post = $this->curl('/admin/api/theme.php', ['form' => ['_method' => 'PUT', 'csrf_token' => $csrf, 'site_title' => 'Integration Site Title']]);
        $this->assertEquals(200, $post['http_code']);
        $pd = json_decode($post['output'], true);
        $this->assertTrue($pd['success']);
    }
}
