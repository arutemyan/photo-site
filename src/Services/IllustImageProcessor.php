<?php
declare(strict_types=1);

namespace App\Services;

use App\Utils\Logger;

/**
 * 画像処理専門クラス
 * - JPEG/WebP変換
 * - サムネイル生成
 * - 署名フッター追加
 */
class IllustImageProcessor
{
    /**
     * 画像データをJPEGに変換して保存
     *
     * @param string $imageData バイナリ画像データ
     * @param string $outputPath 出力先パス
     * @param string|null $artistName アーティスト名（署名追加用）
     * @return bool 成功時true
     */
    public function convertToJpeg(string $imageData, string $outputPath, ?string $artistName = null): bool
    {
        $tmpFile = $outputPath . '.tmp';

        // Try Imagick first for better quality
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick();
                $im->readImageBlob($imageData);

                // Add signature if artist name provided
                if ($artistName !== null && $artistName !== '') {
                    $this->addSignatureImagick($im, $artistName);
                }

                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(95);
                $im->writeImage($tmpFile);
                $im->clear();
                $im->destroy();

                if (@rename($tmpFile, $outputPath)) {
                    return true;
                }
            } catch (\Throwable $e) {
                Logger::getInstance()->error('IllustImageProcessor: Imagick conversion failed: ' . $e->getMessage());
                if (file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
            }
        }

        // GD fallback
        if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            $gd = @imagecreatefromstring($imageData);
            if ($gd !== false) {
                // Add signature if artist name provided
                if ($artistName !== null && $artistName !== '') {
                    $gd = $this->addSignatureGD($gd, $artistName);
                }

                if (function_exists('imagejpeg')) {
                    @imagejpeg($gd, $tmpFile, 95);
                } else {
                    @imagepng($gd, $tmpFile);
                }

                imagedestroy($gd);

                if (file_exists($tmpFile) && @rename($tmpFile, $outputPath)) {
                    return true;
                }
            } else {
                Logger::getInstance()->error('IllustImageProcessor: GD failed to read image blob');
            }
        }

        return false;
    }

    /**
     * 画像ファイルからサムネイル(WebP)を生成
     *
     * @param string $sourcePath 元画像パス
     * @param string $outputPath 出力先パス
     * @param int $width サムネイル幅（デフォルト: 320）
     * @param int $quality WebP品質（デフォルト: 80）
     * @return bool 成功時true
     */
    public function generateThumbnail(string $sourcePath, string $outputPath, int $width = 320, int $quality = 80): bool
    {
        $tmpFile = $outputPath . '.tmp';
        $ext = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));

        // Try Imagick first
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick($sourcePath);
                $im->thumbnailImage($width, 0);

                $format = ($ext === 'webp') ? 'webp' : 'png';
                $im->setImageFormat($format);
                $im->setImageCompressionQuality($quality);
                $im->writeImage($tmpFile);
                $im->clear();
                $im->destroy();

                if (@rename($tmpFile, $outputPath)) {
                    return true;
                }
            } catch (\Throwable $e) {
                Logger::getInstance()->error('IllustImageProcessor: Imagick thumbnail failed: ' . $e->getMessage());
                if (file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
            }
        }

        // GD fallback
        if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
            $data = @file_get_contents($sourcePath);
            if ($data === false) {
                return false;
            }

            $im = @imagecreatefromstring($data);
            if ($im !== false) {
                $thumb = imagescale($im, $width, -1);
                if ($thumb !== false) {
                    if ($ext === 'webp' && function_exists('imagewebp')) {
                        @imagewebp($thumb, $tmpFile, $quality);
                    } elseif (function_exists('imagepng')) {
                        @imagepng($thumb, $tmpFile);
                    }

                    imagedestroy($thumb);
                }
                imagedestroy($im);

                if (file_exists($tmpFile) && @rename($tmpFile, $outputPath)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Imagickで署名フッターを追加
     */
    private function addSignatureImagick(\Imagick $image, string $artistName): void
    {
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $footerHeight = 40;

        // Create new canvas with footer space
        $newHeight = $height + $footerHeight;
        $newImage = new \Imagick();
        $newImage->newImage($width, $newHeight, new \ImagickPixel('black'));
        $newImage->setImageFormat($image->getImageFormat());

        // Composite original image
        $newImage->compositeImage($image, \Imagick::COMPOSITE_OVER, 0, 0);

        // Add text
        $draw = new \ImagickDraw();
        $draw->setFillColor(new \ImagickPixel('white'));
        $draw->setFontSize(14);

        // Try to find suitable font
        $fontCandidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            'DejaVu-Sans',
            'Arial'
        ];

        $fontSet = false;
        foreach ($fontCandidates as $font) {
            try {
                if (file_exists($font) || strpos($font, '/') === false) {
                    $draw->setFont($font);
                    $fontSet = true;
                    break;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        if (!$fontSet) {
            try {
                $draw->setFont('Courier');
            } catch (\Throwable $e) {
                // Use default font
            }
        }

        // Artist name on left
        $newImage->annotateImage($draw, 10, $height + 25, 0, $artistName);

        // Timestamp on right
        $timestamp = date('Y-m-d H:i');
        try {
            $metrics = $newImage->queryFontMetrics($draw, $timestamp);
            $textWidth = $metrics['textWidth'];
        } catch (\Throwable $e) {
            $textWidth = strlen($timestamp) * 8;
        }
        $newImage->annotateImage($draw, $width - $textWidth - 10, $height + 25, 0, $timestamp);

        // Replace original
        $image->clear();
        $image->addImage($newImage);
        $newImage->clear();
        $newImage->destroy();
    }

    /**
     * GDで署名フッターを追加
     *
     * @return resource 新しい画像リソース
     */
    private function addSignatureGD($gdImage, string $artistName)
    {
        $width = imagesx($gdImage);
        $height = imagesy($gdImage);
        $footerHeight = 40;
        $newHeight = $height + $footerHeight;

        // Create new image with footer
        $newImage = imagecreatetruecolor($width, $newHeight);
        if ($newImage === false) {
            return $gdImage;
        }

        $black = imagecolorallocate($newImage, 0, 0, 0);
        $white = imagecolorallocate($newImage, 255, 255, 255);

        // Copy original image
        imagecopy($newImage, $gdImage, 0, 0, 0, 0, $width, $height);

        // Fill footer with black
        imagefilledrectangle($newImage, 0, $height, $width, $newHeight, $black);

        // Find TrueType font
        $fontCandidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            'C:\\Windows\\Fonts\\arial.ttf'
        ];

        $fontPath = null;
        foreach ($fontCandidates as $candidate) {
            if (file_exists($candidate)) {
                $fontPath = $candidate;
                break;
            }
        }

        if (!$fontPath) {
            $commonPaths = [
                '/usr/share/fonts/truetype/ttf-dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/TTF/DejaVuSans.ttf'
            ];
            foreach ($commonPaths as $path) {
                if (file_exists($path)) {
                    $fontPath = $path;
                    break;
                }
            }
        }

        $fontSize = 14;
        $textY = $height + 25;

        if ($fontPath && function_exists('imagettftext')) {
            // Artist name on left
            imagettftext($newImage, $fontSize, 0, 10, $textY, $white, $fontPath, $artistName);

            // Timestamp on right
            $timestamp = date('Y-m-d H:i');
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $timestamp);
            $textWidth = $bbox[2] - $bbox[0];
            imagettftext($newImage, $fontSize, 0, $width - $textWidth - 10, $textY, $white, $fontPath, $timestamp);
        } else {
            // Fallback to bitmap font
            imagestring($newImage, 3, 10, $height + 15, $artistName, $white);
            $timestamp = date('Y-m-d H:i');
            $textWidth = strlen($timestamp) * 7;
            imagestring($newImage, 3, $width - $textWidth - 10, $height + 15, $timestamp, $white);
        }

        // Destroy original
        imagedestroy($gdImage);
        return $newImage;
    }
}
