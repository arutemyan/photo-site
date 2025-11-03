<?php

declare(strict_types=1);

namespace Tests\Api;

class AdminGroupPostsIntegrationTest extends IntegrationTestCase
{
    public function testGroupPostsListAndCrud(): void
    {
        $csrf = $this->loginAndGetCsrf();

        // Create a group via group_upload
        $img = sys_get_temp_dir() . '/gplist_img.png';
        $im = imagecreatetruecolor(8,8);
        $white = imagecolorallocate($im, 255,255,255);
        imagefill($im,0,0,$white);
        imagepng($im, $img);
        imagedestroy($im);

        $create = $this->curl('/admin/api/group_upload.php', [
            'upload' => ['images[0]' => $img],
            'form' => ['csrf_token' => $csrf, 'title' => 'ListGroup']
        ]);
        $this->assertEquals(200, $create['http_code']);
        $cd = json_decode($create['output'], true);
        $this->assertTrue($cd['success']);
        $groupId = (int)$cd['group_post_id'];

        // GET list
        $list = $this->curl('/admin/api/group_posts.php');
        $this->assertEquals(200, $list['http_code']);
        $ld = json_decode($list['output'], true);
        $this->assertTrue($ld['success']);
        $this->assertIsArray($ld['posts']);

        // GET detail
        $detail = $this->curl('/admin/api/group_posts.php?id=' . $groupId);
        $this->assertEquals(200, $detail['http_code']);
        $dd = json_decode($detail['output'], true);
        $this->assertTrue($dd['success']);
        $this->assertEquals($groupId, $dd['data']['id']);

        // Update group title via POST _method=PUT
        $update = $this->curl('/admin/api/group_posts.php', [
            'form' => ['_method' => 'PUT', 'id' => $groupId, 'title' => 'UpdatedGroup', 'csrf_token' => $csrf]
        ]);
        $this->assertEquals(200, $update['http_code']);
        $ud = json_decode($update['output'], true);
        $this->assertTrue($ud['success']);

        // Delete group via POST _method=DELETE
        $del = $this->curl('/admin/api/group_posts.php', [
            'form' => ['_method' => 'DELETE', 'id' => $groupId, 'csrf_token' => $csrf]
        ]);
        $this->assertEquals(200, $del['http_code']);
        $dv = json_decode($del['output'], true);
        $this->assertTrue($dv['success']);
    }
}
