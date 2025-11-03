<?php

declare(strict_types=1);

namespace Tests\Api;

class AdminPostsIntegrationTest extends IntegrationTestCase
{
    public function testPostLifecycleAndBulkVisibility(): void
    {
        $csrf = $this->loginAndGetCsrf();

        // create a post using upload endpoint
        $img = sys_get_temp_dir() . '/post_img.png';
        $im = imagecreatetruecolor(8,8);
        $white = imagecolorallocate($im, 255,255,255);
        imagefill($im,0,0,$white);
        imagepng($im, $img);
        imagedestroy($im);

        $upload = $this->curl('/admin/api/upload.php', ['upload' => ['image' => $img], 'form' => ['title' => 'PostLifecycle', 'csrf_token' => $csrf]]);
        $this->assertEquals(200, $upload['http_code']);
        $ud = json_decode($upload['output'], true);
        $this->assertTrue($ud['success']);
        $postId = (int)$ud['id'];

        // GET the post via posts.php
        $get = $this->curl('/admin/api/posts.php?id=' . $postId);
        $this->assertEquals(200, $get['http_code']);
        $gd = json_decode($get['output'], true);
        $this->assertTrue($gd['success']);
        $this->assertEquals($postId, $gd['post']['id']);

        // Update the post via POST _method=PUT
        $update = $this->curl('/admin/api/posts.php', ['form' => ['_method' => 'PUT', 'id' => $postId, 'title' => 'PostUpdated', 'tags' => 'a,b', 'detail' => 'd', 'csrf_token' => $csrf]]);
        $this->assertEquals(200, $update['http_code']);
        $ud2 = json_decode($update['output'], true);
        $this->assertTrue($ud2['success']);

        // Bulk visibility change via POST _method=PATCH (posts.php accepts _method override)
        $patch = $this->curl('/admin/api/posts.php', ['form' => ['_method' => 'PATCH', 'post_ids' => [$postId], 'is_visible' => 0]]);
        $this->assertEquals(200, $patch['http_code']);
        $pd = json_decode($patch['output'], true);
        $this->assertTrue($pd['success']);

        // Delete the post via POST _method=DELETE
        $del = $this->curl('/admin/api/posts.php', ['form' => ['_method' => 'DELETE', 'id' => $postId, 'csrf_token' => $csrf]]);
        $this->assertEquals(200, $del['http_code']);
        $dd = json_decode($del['output'], true);
        $this->assertTrue($dd['success']);
    }
}
