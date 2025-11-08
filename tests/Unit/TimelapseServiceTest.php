<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\TimelapseService;

final class TimelapseServiceTest extends TestCase
{
    public function testSaveAndLoad(): void
    {
        $tmp = sys_get_temp_dir() . '/timelapse_test_' . uniqid();
        @mkdir($tmp, 0755, true);
        $svc = new TimelapseService($tmp);

        $subdir = '001';
    $filename = 'timelapse_test.csv.gz';
        $data = gzencode('hello');

        $path = $svc->save($subdir, $filename, $data);
        $this->assertFileExists($path);
        $loaded = $svc->load($path);
        $this->assertEquals($data, $loaded);

        // cleanup
        @unlink($path);
        @rmdir($tmp . '/' . $subdir);
        @rmdir($tmp);
    }
}
