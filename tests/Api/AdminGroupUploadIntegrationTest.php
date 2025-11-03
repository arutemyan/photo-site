<?php

declare(strict_types=1);

namespace Tests\Api;

class AdminGroupUploadIntegrationTest extends IntegrationTestCase
{
    public function testCreateGroupWithImagesAndAddToExisting(): void
    {
        $csrf = $this->loginAndGetCsrf();

        // create two small images for initial group
        $img1 = sys_get_temp_dir() . '/group_img1.png';
        $im = imagecreatetruecolor(8,8);
        $white = imagecolorallocate($im, 255,255,255);
        imagefill($im,0,0,$white);
        imagepng($im, $img1);
        imagedestroy($im);

        $img2 = sys_get_temp_dir() . '/group_img2.png';
        $im2 = imagecreatetruecolor(8,8);
        $white2 = imagecolorallocate($im2, 255,255,255);
        imagefill($im2,0,0,$white2);
        imagepng($im2, $img2);
        imagedestroy($im2);

        // create a new group post
        $resp = $this->curl('/admin/api/group_upload.php', [
            'upload' => [
                'images[0]' => $img1,
                'images[1]' => $img2
            ],
            'form' => ['csrf_token' => $csrf, 'title' => 'Integration Group Test']
        ]);
        $this->assertEquals(200, $resp['http_code'], 'Group upload did not return 200');
        $data = json_decode($resp['output'], true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('group_post_id', $data);
        $groupId = (int)$data['group_post_id'];

        // now add another image to existing group
        $img3 = sys_get_temp_dir() . '/group_img3.png';
        $im3 = imagecreatetruecolor(8,8);
        $w3 = imagecolorallocate($im3, 255,255,255);
        imagefill($im3,0,0,$w3);
        imagepng($im3, $img3);
        imagedestroy($im3);

        $addResp = $this->curl('/admin/api/group_upload.php', [
            'upload' => ['images[0]' => $img3],
            'form' => ['csrf_token' => $csrf, 'group_post_id' => $groupId]
        ]);
        $this->assertEquals(200, $addResp['http_code'], 'Add to group did not return 200');
        $addData = json_decode($addResp['output'], true);
        $this->assertTrue($addData['success']);
    }
}
