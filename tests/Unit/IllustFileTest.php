<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Models\IllustFile;

final class IllustFileTest extends TestCase
{
    public function testValidateAcceptsMinimalValidStructure(): void
    {
        $data = [
            'metadata' => ['canvas_width' => 100, 'canvas_height' => 100],
            'layers' => [
                ['id' => 'layer_0', 'name' => 'bg', 'order' => 0, 'visible' => true, 'opacity' => 1.0, 'type' => 'raster', 'data' => '', 'width' => 100, 'height' => 100]
            ],
        ];
        $json = json_encode($data);
        $res = IllustFile::validate($json);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('metadata', $res);
        $this->assertArrayHasKey('layers', $res);
    }

    public function testValidateRejectsInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        IllustFile::validate('{invalid json');
    }

    public function testValidateRejectsMissingFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $json = json_encode(['foo' => 'bar']);
        IllustFile::validate($json);
    }
}
