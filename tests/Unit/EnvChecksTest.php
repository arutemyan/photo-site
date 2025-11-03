<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Utils\EnvChecks;

final class EnvChecksTest extends TestCase
{
    public function testCheckAllReturnsExpectedKeys(): void
    {
        $res = EnvChecks::checkAll();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('msgpack', $res);
        $this->assertArrayHasKey('zlib', $res);
        $this->assertArrayHasKey('fileinfo', $res);
        $this->assertArrayHasKey('webp', $res);
        $this->assertIsBool($res['msgpack']);
        $this->assertIsBool($res['zlib']);
        $this->assertIsBool($res['fileinfo']);
        $this->assertIsBool($res['webp']);
    }
}
