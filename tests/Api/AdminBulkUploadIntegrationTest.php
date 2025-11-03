<?php

declare(strict_types=1);

namespace Tests\Api;

class AdminBulkUploadIntegrationTest extends IntegrationTestCase
{
    public function testBulkUploadCreatesMultiplePosts(): void
    {
        $csrf = $this->loginAndGetCsrf();

        // create two small images
        $img1 = sys_get_temp_dir() . '/bulk_img1.png';
        $im = imagecreatetruecolor(8,8);
        $white = imagecolorallocate($im, 255,255,255);
        imagefill($im,0,0,$white);
        imagepng($im, $img1);
        imagedestroy($im);

        $img2 = sys_get_temp_dir() . '/bulk_img2.png';
        $im2 = imagecreatetruecolor(8,8);
        $white2 = imagecolorallocate($im2, 255,255,255);
        imagefill($im2,0,0,$white2);
        imagepng($im2, $img2);
        imagedestroy($im2);

        // perform multipart POST with images[]
        $uploadResp = $this->curl('/admin/api/bulk_upload.php', [
            'upload' => [
                'images[0]' => $img1,
                'images[1]' => $img2
            ],
            'form' => ['csrf_token' => $csrf]
        ]);

        $this->assertEquals(200, $uploadResp['http_code'], 'Bulk upload did not return 200. Resp: ' . substr($uploadResp['output'],0,500));
        $data = json_decode($uploadResp['output'], true);
        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(2, $data['success_count']);
    }
}
