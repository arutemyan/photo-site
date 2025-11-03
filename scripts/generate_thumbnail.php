<?php
// Usage: php scripts/generate_thumbnail.php <id>
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) exit(1);
if ($argc < 2) {
    echo "Usage: php scripts/generate_thumbnail.php <id>\n";
    exit(1);
}
$id = (int)$argv[1];
$sub = sprintf('%03d', $id % 1000);
$imagesDir = $projectRoot . '/uploads/paintfiles/images/' . $sub;
$src = $imagesDir . '/illust_' . $id . '.png';
// prefer webp if possible
$useWebp = function_exists('imagewebp');
$thumbExt = $useWebp ? 'webp' : 'png';
$dst = $imagesDir . '/illust_' . $id . '_thumb.' . $thumbExt;

if (!file_exists($src)) {
    echo "Source image not found: {$src}\n";
    exit(2);
}

// Try Imagick first
$ok = false;
if (extension_loaded('imagick')) {
    try {
        $im = new \Imagick($src);
        $format = $useWebp ? 'webp' : 'png';
        $im->setImageFormat($format);
        $im->thumbnailImage(320, 0);
        $im->writeImage($dst);
        $im->clear();
        $im->destroy();
        $ok = file_exists($dst);
    } catch (\Throwable $e) {
        echo "Imagick failed: " . $e->getMessage() . "\n";
    }
}

if (!$ok && extension_loaded('gd') && function_exists('imagecreatefromstring')) {
    $data = @file_get_contents($src);
    if ($data === false) {
        echo "Failed to read source image: {$src}\n";
        exit(3);
    }
    $im = @imagecreatefromstring($data);
    if ($im !== false) {
        $thumb = imagescale($im, 320, -1);
        if ($useWebp && function_exists('imagewebp')) {
            @imagewebp($thumb, $dst, 80);
        } else {
            @imagepng($thumb, $dst);
        }
        imagedestroy($thumb);
        imagedestroy($im);
        $ok = file_exists($dst);
    } else {
        echo "GD failed to create image from source\n";
    }
}

if ($ok) {
    echo "Thumbnail generated: {$dst}\n";
    exit(0);
}

echo "Thumbnail generation failed\n";
exit(4);
