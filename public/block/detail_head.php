<?php
/**
 * 詳細ページの共通ヘッダー部分
 *
 * 使用方法:
 * - detail.php または group_detail.php から include する
 *
 * 必要な変数:
 * @var array $data 投稿データ（$post または $groupPost）
 * @var array $theme テーマ設定
 * @var bool $isGroupPost グループ投稿かどうか
 */

// データの取得
$title = escapeHtml($data['title']);
$siteTitle = escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ');
$description = escapeHtml($data['detail'] ?? $data['title']);

// SNS共有用の画像パスを決定
$isSensitive = isset($data['is_sensitive']) && $data['is_sensitive'] == 1;
$shareImagePath = '';

if ($isGroupPost) {
    // グループ投稿の場合：最初の画像のサムネイル
    if (!empty($data['images']) && !empty($data['images'][0]['thumb_path'])) {
        $shareImagePath = $data['images'][0]['thumb_path'];

        if ($isSensitive) {
            $pathInfo = pathinfo($shareImagePath);
            $nsfwFilename = basename($pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp'));
            $shareImagePath = $pathInfo['dirname'] . '/' . $nsfwFilename;
        }
    }
} else {
    // 単一投稿の場合
    if (!empty($data['image_path'])) {
        if ($isSensitive) {
            // NSFW画像の場合はNSFWフィルター版を使用
            $pathInfo = pathinfo($data['image_path']);
            $nsfwFilename = basename($pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp'));
            $shareImagePath = $pathInfo['dirname'] . '/' . $nsfwFilename;

            // パスの検証（uploadsディレクトリ内であることを確認）
            $fullPath = realpath(__DIR__ . '/../' . $shareImagePath);
            $uploadsDir = realpath(__DIR__ . '/../uploads/');

            // NSFWフィルター版が存在しない、または不正なパスの場合はサムネイルのNSFWフィルター版を使用
            if (!$fullPath || !$uploadsDir || strpos($fullPath, $uploadsDir) !== 0 || !file_exists($fullPath)) {
                if (!empty($data['thumb_path'])) {
                    $thumbInfo = pathinfo($data['thumb_path']);
                    $nsfwThumbFilename = basename($thumbInfo['filename'] . '_nsfw.' . ($thumbInfo['extension'] ?? 'webp'));
                    $shareImagePath = $thumbInfo['dirname'] . '/' . $nsfwThumbFilename;
                } else {
                    $shareImagePath = '';
                }
            }
        } else {
            // 通常の画像はサムネイルを使用
            $shareImagePath = $data['thumb_path'] ?? $data['image_path'];
        }
    }
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$fullUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'];
$imageUrl = !empty($shareImagePath) ? $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . $shareImagePath : '';
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - <?= $siteTitle ?></title>
    <meta name="description" content="<?= $description ?>">

    <!-- OGP (Open Graph Protocol) -->
    <meta property="og:title" content="<?= $title ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= escapeHtml($fullUrl) ?>">
    <meta property="og:description" content="<?= escapeHtml(mb_substr($data['detail'] ?? $data['title'], 0, 200)) ?>">
    <meta property="og:site_name" content="<?= $siteTitle ?>">
    <?php if (!empty($imageUrl)): ?>
    <meta property="og:image" content="<?= escapeHtml($imageUrl) ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $title ?>">
    <meta name="twitter:description" content="<?= escapeHtml(mb_substr($data['detail'] ?? $data['title'], 0, 200)) ?>">
    <?php if (!empty($imageUrl)): ?>
    <meta name="twitter:image" content="<?= escapeHtml($imageUrl) ?>">
    <?php endif; ?>

    <!-- Googleフォント -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- スタイルシート -->
    <link rel="stylesheet" href="/res/css/main.css">

    <!-- テーマカラー -->
    <style>
        <?php require_once(__DIR__ . "/style.php") ?>
    </style>
</head>
