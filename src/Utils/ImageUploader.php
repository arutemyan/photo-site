<?php

declare(strict_types=1);

namespace App\Utils;

use Exception;

/**
 * 画像アップロードユーティリティクラス
 *
 * アップロードとバルクアップロードで共通のロジックを提供
 */
class ImageUploader
{
    private string $uploadDir;
    private string $thumbDir;
    private int $maxFileSize;
    private array $allowedMimeTypes;

    /**
     * コンストラクタ
     *
     * @param string $uploadDir アップロードディレクトリのパス
     * @param string $thumbDir サムネイルディレクトリのパス
     * @param int $maxFileSize 最大ファイルサイズ（バイト）
     * @param array $allowedMimeTypes 許可するMIMEタイプ
     */
    public function __construct(
        string $uploadDir,
        string $thumbDir,
        int $maxFileSize = 20 * 1024 * 1024,
        array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
    ) {
        $this->uploadDir = $uploadDir;
        $this->thumbDir = $thumbDir;
        $this->maxFileSize = $maxFileSize;
        $this->allowedMimeTypes = $allowedMimeTypes;

        // ディレクトリが存在しない場合は作成
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbDir)) {
            mkdir($this->thumbDir, 0755, true);
        }
    }

    /**
     * アップロードされたファイルを検証
     *
     * @param array $file $_FILES配列の要素
     * @return array ['valid' => bool, 'error' => string|null, 'mime_type' => string|null]
     */
    public function validateFile(array $file): array
    {
        // アップロードエラーチェック
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => 'アップロードエラー (code: ' . ($file['error'] ?? 'unknown') . ')',
                'mime_type' => null
            ];
        }

        // ファイルサイズチェック
        if ($file['size'] > $this->maxFileSize) {
            $maxMB = $this->maxFileSize / (1024 * 1024);
            return [
                'valid' => false,
                'error' => "ファイルサイズが大きすぎます（最大{$maxMB}MB）",
                'mime_type' => null
            ];
        }

        // 画像形式チェック
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'error' => '画像ファイルではありません',
                'mime_type' => null
            ];
        }

        $mimeType = $imageInfo['mime'];
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return [
                'valid' => false,
                'error' => 'サポートされていない画像形式です',
                'mime_type' => null
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'mime_type' => $mimeType
        ];
    }

    /**
     * 画像を処理して保存
     *
     * @param string $tmpPath 一時ファイルのパス
     * @param string $mimeType MIMEタイプ
     * @param string $filename 保存するファイル名（拡張子なし）
     * @param bool $createNsfwFilter NSFWフィルター版サムネイルを作成するか
     * @param array $filterSettings フィルター設定（オプション）
     * @return array ['success' => bool, 'image_path' => string, 'thumb_path' => string, 'error' => string|null]
     */
    public function processAndSave(string $tmpPath, string $mimeType, string $filename, bool $createNsfwFilter = false, array $filterSettings = []): array
    {
        try {
            $webpFilename = $filename . '.webp';
            $imagePath = $this->uploadDir . '/' . $webpFilename;
            $thumbPath = $this->thumbDir . '/' . $webpFilename;

            // 画像を読み込み
            $sourceImage = match($mimeType) {
                'image/jpeg' => imagecreatefromjpeg($tmpPath),
                'image/png' => imagecreatefrompng($tmpPath),
                'image/gif' => imagecreatefromgif($tmpPath),
                'image/webp' => imagecreatefromwebp($tmpPath),
                default => throw new Exception('サポートされていない画像形式です')
            };

            if ($sourceImage === false) {
                throw new Exception('画像の読み込みに失敗しました');
            }

            // WebP形式で保存（元画像）
            if (!imagewebp($sourceImage, $imagePath, 90)) {
                imagedestroy($sourceImage);
                throw new Exception('画像の保存に失敗しました');
            }

            // サムネイルを生成
            $this->createThumbnail($sourceImage, $thumbPath, 600, 600);

            // NSFWフィルター版サムネイルを生成
            if ($createNsfwFilter) {
                $nsfwFilename = $filename . '_nsfw.webp';
                $nsfwPath = $this->thumbDir . '/' . $nsfwFilename;
                $this->createNsfwThumbnail($thumbPath, $nsfwPath, $filterSettings);
            }

            // メモリ解放
            imagedestroy($sourceImage);

            return [
                'success' => true,
                'image_path' => 'uploads/images/' . $webpFilename,
                'thumb_path' => 'uploads/thumbs/' . $webpFilename,
                'error' => null
            ];

        } catch (Exception $e) {
            // エラー時のクリーンアップ
            if (isset($imagePath) && file_exists($imagePath)) {
                unlink($imagePath);
            }
            if (isset($thumbPath) && file_exists($thumbPath)) {
                unlink($thumbPath);
            }

            return [
                'success' => false,
                'image_path' => null,
                'thumb_path' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * サムネイルを生成
     *
     * @param resource $sourceImage 元画像のGDリソース
     * @param string $outputPath 出力パス
     * @param int $maxWidth 最大幅
     * @param int $maxHeight 最大高さ
     */
    private function createThumbnail($sourceImage, string $outputPath, int $maxWidth, int $maxHeight): void
    {
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // アスペクト比を保持してリサイズ
        if ($originalWidth > $originalHeight) {
            $newWidth = min($maxWidth, $originalWidth);
            $newHeight = (int)($originalHeight * ($newWidth / $originalWidth));
        } else {
            $newHeight = min($maxHeight, $originalHeight);
            $newWidth = (int)($originalWidth * ($newHeight / $originalHeight));
        }

        $thumbImage = imagecreatetruecolor($newWidth, $newHeight);

        // PNG透過対応
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);

        imagecopyresampled(
            $thumbImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        imagewebp($thumbImage, $outputPath, 85);
        imagedestroy($thumbImage);
    }

    /**
     * StackBlurを適用
     */
    private function applyStackBlur(&$image, $width, $height, $radius)
    {
        if ($radius < 1) return;

        $referenceImage = imagecreatetruecolor($width, $height);
        imagecopy($referenceImage, $image, 0, 0, 0, 0, $width, $height);
        $divsum = ($radius * 2 + 1);

        // 水平パス
        for ($y = 0; $y < $height; $y++) {
            $rt = $gt = $bt = $at = 0;
            $stack = array_fill(0, $radius * 2 + 1, 0);
            $stackpointer = $radius;

            for ($i = -$radius; $i <= $radius; $i++) {
                $x = min(max($i, 0), $width - 1);
                $pixel = imagecolorat($referenceImage, $x, $y);
                $stack[$i + $radius] = $pixel;
                $rt += ($pixel >> 16) & 0xFF;
                $gt += ($pixel >> 8) & 0xFF;
                $bt += $pixel & 0xFF;
                $at += ($pixel >> 24) & 0xFF;
            }

            for ($x = 0; $x < $width; $x++) {
                $r = (int)($rt / $divsum);
                $g = (int)($gt / $divsum);
                $b = (int)($bt / $divsum);
                $a = (int)($at / $divsum);

                $color = ($a << 24) | ($r << 16) | ($g << 8) | $b;
                imagesetpixel($image, $x, $y, $color);

                $stackpointer = ($stackpointer + 1) % ($radius * 2 + 1);
                $pixelOut = $stack[$stackpointer];
                $rt -= ($pixelOut >> 16) & 0xFF;
                $gt -= ($pixelOut >> 8) & 0xFF;
                $bt -= $pixelOut & 0xFF;
                $at -= ($pixelOut >> 24) & 0xFF;

                $xi = min($x + $radius + 1, $width - 1);
                $pixelIn = imagecolorat($referenceImage, $xi, $y);
                $stack[$stackpointer] = $pixelIn;
                $rt += ($pixelIn >> 16) & 0xFF;
                $gt += ($pixelIn >> 8) & 0xFF;
                $bt += ($pixelIn & 0xFF);
                $at += ($pixelIn >> 24) & 0xFF;
            }
        }

        // 中間バッファを破棄
        imagedestroy($referenceImage);
        $referenceImage = imagecreatetruecolor($width, $height);
        imagecopy($referenceImage, $image, 0, 0, 0, 0, $width, $height);

        // 垂直パス
        for ($x = 0; $x < $width; $x++) {
            $rt = $gt = $bt = $at = 0;
            $stack = array_fill(0, $radius * 2 + 1, 0);
            $stackpointer = $radius;

            for ($i = -$radius; $i <= $radius; $i++) {
                $y = min(max($i, 0), $height - 1);
                $pixel = imagecolorat($referenceImage, $x, $y);
                $stack[$i + $radius] = $pixel;
                $rt += ($pixel >> 16) & 0xFF;
                $gt += ($pixel >> 8) & 0xFF;
                $bt += ($pixel & 0xFF);
                $at += ($pixel >> 24) & 0xFF;
            }

            for ($y = 0; $y < $height; $y++) {
                $r = (int)($rt / $divsum);
                $g = (int)($gt / $divsum);
                $b = (int)($bt / $divsum);
                $a = (int)($at / $divsum);

                $color = ($a << 24) | ($r << 16) | ($g << 8) | $b;
                imagesetpixel($image, $x, $y, $color);

                $stackpointer = ($stackpointer + 1) % ($radius * 2 + 1);
                $pixelOut = $stack[$stackpointer];
                $rt -= ($pixelOut >> 16) & 0xFF;
                $gt -= ($pixelOut >> 8) & 0xFF;
                $bt -= ($pixelOut & 0xFF);
                $at -= ($pixelOut >> 24) & 0xFF;

                $yi = min($y + $radius + 1, $height - 1);
                $pixelIn = imagecolorat($referenceImage, $x, $yi);
                $stack[$stackpointer] = $pixelIn;
                $rt += ($pixelIn >> 16) & 0xFF;
                $gt += ($pixelIn >> 8) & 0xFF;
                $bt += ($pixelIn & 0xFF);
                $at += ($pixelIn >> 24) & 0xFF;
            }
        }

        imagedestroy($referenceImage);
    }


    /**
     * NSFWフィルターサムネイルを生成（すりガラス効果）
     *
     * @param string $sourcePath 元サムネイルのパス
     * @param string $outputPath 出力パス
     * @param array $settings フィルター設定（blur_strength, brightness, contrast, white_overlay, quality）
     */
    public function createNsfwThumbnail(string $sourcePath, string $outputPath, array $settings = []): void
    {
        $image = imagecreatefromwebp($sourcePath);
        if ($image === false) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // デフォルト設定
        $blurStrength = $settings['blur_strength'];
        $brightness = $settings['brightness'];
        $contrast = $settings['contrast'];
        $whiteOverlay = $settings['white_overlay'];
        $quality = $settings['quality'];

        // stack-blur
        $this->applyStackBlur($image, $width, $height, $blurStrength);

        // 2. コントラスト調整（柔らかい印象に）
        imagefilter($image, IMG_FILTER_CONTRAST, $contrast);

        // 3. 明度調整（明るく透明感を出す）
        imagefilter($image, IMG_FILTER_BRIGHTNESS, $brightness);

        // 4. 白い半透明オーバーレイ（すりガラス感）
        $whiteOverlay = min(100, max(0, $whiteOverlay));

        // 白 (255, 255, 255) とブレンド
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // 白とブレンド（white_overlay%の白を混ぜる）
                $r = (int)($r + (255 - $r) * $whiteOverlay / 100);
                $g = (int)($g + (255 - $g) * $whiteOverlay / 100);
                $b = (int)($b + (255 - $b) * $whiteOverlay / 100);

                $newColor = imagecolorallocate($image, $r, $g, $b);
                imagesetpixel($image, $x, $y, $newColor);
            }
        }

        imagewebp($image, $outputPath, $quality);
        imagedestroy($image);
    }

    /**
     * ユニークなファイル名を生成
     *
     * @param string $prefix プレフィックス（例: "bulk_", "post_"）
     * @return string 拡張子なしのファイル名
     */
    public function generateUniqueFilename(string $prefix = ''): string
    {
        return $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(8));
    }
}
