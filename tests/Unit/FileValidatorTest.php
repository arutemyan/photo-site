<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Utils\FileValidator;

final class FileValidatorTest extends TestCase
{
    public function testValidateDataUriImageAcceptsValidPng(): void
    {
        $dataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==';
        [$mime, $bin] = FileValidator::validateDataUriImage($dataUri);
        $this->assertContains($mime, ['image/png', 'image/jpeg', 'image/webp']);
        $this->assertIsString($bin);
        $this->assertNotEmpty($bin);
    }

    public function testValidateDataUriImageRejectsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FileValidator::validateDataUriImage('not-a-data-uri');
    }

    public function testValidateTimelapseBinaryAcceptsGz(): void
    {
        $gz = gzencode('test');
        $this->assertTrue(FileValidator::validateTimelapseBinary($gz));
    }

    public function testValidateDataUriImageRejectsOversize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // generate a base64 payload exceeding IMAGE_MAX_BYTES
        $big = str_repeat('A', FileValidator::IMAGE_MAX_BYTES + 100);
        $dataUri = 'data:image/png;base64,' . base64_encode($big);
        FileValidator::validateDataUriImage($dataUri);
    }

    public function testValidateTimelapseBinaryRejectsNonGz(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FileValidator::validateTimelapseBinary('notgzdata');
    }

    public function testIsSafeFilename(): void
    {
        $this->assertTrue(FileValidator::isSafeFilename('file.png'));
        $this->assertFalse(FileValidator::isSafeFilename('../evil.php'));
        $this->assertFalse(FileValidator::isSafeFilename('double.ext.png.php'));
    }
}
