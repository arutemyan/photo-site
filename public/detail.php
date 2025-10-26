<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Security/SecurityUtil.php';

use App\Models\Post;
use App\Models\Theme;
use App\Models\Setting;

// IDパラメータの検証
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /index.php');
    exit;
}

$postId = (int)$_GET['id'];

try {
    // テーマ設定を取得
    $themeModel = new Theme();
    $theme = $themeModel->getCurrent();

    // サイト設定を取得
    $settingModel = new Setting();
    $showViewCount = $settingModel->get('show_view_count', '1') === '1';

    // 設定を読み込み
    $config = require __DIR__ . '/../config/config.php';
    $nsfwConfig = $config['nsfw'];
    $ageVerificationMinutes = $nsfwConfig['age_verification_minutes'];
    $nsfwConfigVersion = $nsfwConfig['config_version'];

    // 投稿を取得
    $postModel = new Post();
    $post = $postModel->getById($postId);

    if ($post === null) {
        header('Location: /index.php');
        exit;
    }

} catch (Exception $e) {
    error_log('Detail Error: ' . $e->getMessage());
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeHtml($post['title']) ?> - <?= escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ') ?></title>
    <meta name="description" content="<?= escapeHtml($post['detail'] ?? $post['title']) ?>">

    <?php
    // SNS共有用の画像パスを決定
    $isSensitive = isset($post['is_sensitive']) && $post['is_sensitive'] == 1;
    $shareImagePath = '';
    if (!empty($post['image_path'])) {
        if ($isSensitive) {
            // NSFW画像の場合はNSFWフィルター版を使用
            $pathInfo = pathinfo($post['image_path']);
            // basename()でディレクトリトラバーサルを防止
            $nsfwFilename = basename($pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp'));
            $shareImagePath = $pathInfo['dirname'] . '/' . $nsfwFilename;

            // パスの検証（uploadsディレクトリ内であることを確認）
            $fullPath = realpath(__DIR__ . '/' . $shareImagePath);
            $uploadsDir = realpath(__DIR__ . '/uploads/');

            // NSFWフィルター版が存在しない、または不正なパスの場合はサムネイルのNSFWフィルター版を使用
            if (!$fullPath || !$uploadsDir || strpos($fullPath, $uploadsDir) !== 0 || !file_exists($fullPath)) {
                if (!empty($post['thumb_path'])) {
                    $thumbInfo = pathinfo($post['thumb_path']);
                    $nsfwThumbFilename = basename($thumbInfo['filename'] . '_nsfw.' . ($thumbInfo['extension'] ?? 'webp'));
                    $shareImagePath = $thumbInfo['dirname'] . '/' . $nsfwThumbFilename;
                } else {
                    $shareImagePath = '';
                }
            }
        } else {
            // 通常の画像はサムネイルを使用
            $shareImagePath = $post['thumb_path'] ?? $post['image_path'];
        }
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $fullUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'];
    $imageUrl = !empty($shareImagePath) ? $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . $shareImagePath : '';
    ?>

    <!-- OGP (Open Graph Protocol) -->
    <meta property="og:title" content="<?= escapeHtml($post['title']) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= escapeHtml($fullUrl) ?>">
    <meta property="og:description" content="<?= escapeHtml(mb_substr($post['detail'] ?? $post['title'], 0, 200)) ?>">
    <meta property="og:site_name" content="<?= escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ') ?>">
    <?php if (!empty($imageUrl)): ?>
    <meta property="og:image" content="<?= escapeHtml($imageUrl) ?>">
    <meta property="og:image:alt" content="<?= escapeHtml($post['title']) ?>">
    <meta property="og:image:width" content="600">
    <meta property="og:image:height" content="600">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= escapeHtml($post['title']) ?>">
    <meta name="twitter:description" content="<?= escapeHtml(mb_substr($post['detail'] ?? $post['title'], 0, 200)) ?>">
    <?php if (!empty($imageUrl)): ?>
    <meta name="twitter:image" content="<?= escapeHtml($imageUrl) ?>">
    <?php endif; ?>

    <!-- CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
    <link href="/res/css/main.css" rel="stylesheet">

    <!-- テーマカラー -->
    <style>
        :root {
            --primary-color: <?= escapeHtml($theme['primary_color'] ?? '#8B5AFA') ?>;
            --secondary-color: <?= escapeHtml($theme['secondary_color'] ?? '#667eea') ?>;
            --accent-color: <?= escapeHtml($theme['accent_color'] ?? '#FFD700') ?>;
            --background-color: <?= escapeHtml($theme['background_color'] ?? '#1a1a1a') ?>;
            --text-color: <?= escapeHtml($theme['text_color'] ?? '#ffffff') ?>;
            --heading-color: <?= escapeHtml($theme['heading_color'] ?? '#ffffff') ?>;
            --footer-bg-color: <?= escapeHtml($theme['footer_bg_color'] ?? '#2a2a2a') ?>;
            --footer-text-color: <?= escapeHtml($theme['footer_text_color'] ?? '#cccccc') ?>;
            --card-border-color: <?= escapeHtml($theme['card_border_color'] ?? '#333333') ?>;
            --card-bg-color: <?= escapeHtml($theme['card_bg_color'] ?? '#252525') ?>;
            --card-shadow-opacity: <?= escapeHtml($theme['card_shadow_opacity'] ?? '0.3') ?>;
            --link-color: <?= escapeHtml($theme['link_color'] ?? '#8B5AFA') ?>;
            --link-hover-color: <?= escapeHtml($theme['link_hover_color'] ?? '#a177ff') ?>;
            --tag-bg-color: <?= escapeHtml($theme['tag_bg_color'] ?? '#8B5AFA') ?>;
            --tag-text-color: <?= escapeHtml($theme['tag_text_color'] ?? '#ffffff') ?>;
            --filter-active-bg-color: <?= escapeHtml($theme['filter_active_bg_color'] ?? '#8B5AFA') ?>;
            --filter-active-text-color: <?= escapeHtml($theme['filter_active_text_color'] ?? '#ffffff') ?>;
        }

        body {
            background-color: var(--background-color);
        }

        header {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            <?php if (!empty($theme['header_image'])): ?>
            background-image: url('/<?= escapeHtml($theme['header_image']) ?>');
            background-size: cover;
            background-position: center;
            background-blend-mode: overlay;
            <?php endif; ?>
        }

        .btn-primary,
        .btn-secondary {
            background: var(--primary-color);
        }

        .btn-primary:hover,
        .btn-secondary:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body data-age-verification-minutes="<?= $ageVerificationMinutes ?>" data-nsfw-config-version="<?= $nsfwConfigVersion ?>" data-post-id="<?= $postId ?>" data-is-sensitive="<?= isset($post['is_sensitive']) && $post['is_sensitive'] == 1 ? '1' : '0' ?>">
    <script>
        // 設定値をdata属性から読み込み（const定義で改ざん防止）
        const AGE_VERIFICATION_MINUTES = parseInt(document.body.dataset.ageVerificationMinutes) || 10080;
        const NSFW_CONFIG_VERSION = parseInt(document.body.dataset.nsfwConfigVersion) || 1;
    </script>

    <!-- 年齢確認モーダル -->
    <div id="ageVerificationModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h2 class="modal-title">年齢確認</h2>
                <button type="button" class="modal-close" onclick="denyAge()">&times;</button>
            </div>
            <div class="modal-body">
                <p>このコンテンツは18歳未満の閲覧に適さない可能性があります。</p>
                <p><strong>あなたは18歳以上ですか？</strong></p>
                <p style="font-size: 0.9em; color: #999; margin-top: 20px;">
                    <?php
                    if ($ageVerificationMinutes < 60) {
                        $displayTime = $ageVerificationMinutes . '分間';
                    } elseif ($ageVerificationMinutes < 1440) {
                        $displayTime = round($ageVerificationMinutes / 60, 1) . '時間';
                    } else {
                        $displayTime = round($ageVerificationMinutes / 1440, 1) . '日間';
                    }
                    ?>
                    ※一度確認すると、ブラウザに記録され一定期間（<?= $displayTime ?>）は再度確認されません。<br>
                    記録を削除したい場合はブラウザのCookieを削除してください。
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="denyAge()">いいえ</button>
                <button type="button" class="btn btn-primary" onclick="confirmAge()">はい、18歳以上です</button>
            </div>
        </div>
    </div>

    <!-- ヘッダー -->
    <header>
        <div class="header-content">
            <a href="/index.php" class="back-link">一覧に戻る</a>
            <?php if (!empty($theme['logo_image'])): ?>
                <img src="/<?= escapeHtml($theme['logo_image']) ?>" alt="<?= escapeHtml($theme['site_title'] ?? 'ロゴ') ?>" style="max-height: 60px;">
            <?php else: ?>
                <span style="color: white; font-weight: 700;"><?= !empty($theme['header_html']) ? escapeHtml($theme['header_html']) : escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ') ?></span>
            <?php endif; ?>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <div class="container">
        <div class="detail-card">
            <?php
            $isSensitive = isset($post['is_sensitive']) && $post['is_sensitive'] == 1;
            $imagePath = '/' . escapeHtml($post['image_path'] ?? $post['thumb_path'] ?? '');
            // センシティブ画像の場合、最初はNSFWフィルター版を表示
            if ($isSensitive) {
                $pathInfo = pathinfo($imagePath);
                $nsfwPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? '');
                $displayPath = $nsfwPath;
            } else {
                $displayPath = $imagePath;
            }
            ?>
            <img
                id="detailImage"
                src="<?= $displayPath ?>"
                <?= $isSensitive ? 'data-original="' . $imagePath . '"' : '' ?>
                alt="<?= escapeHtml($post['title']) ?>"
                class="detail-image"
            >

            <div class="detail-content">
                <?php if (isset($post['is_sensitive']) && $post['is_sensitive'] == 1): ?>
                    <div class="detail-nsfw-badge">NSFW / 18+</div>
                <?php endif; ?>

                <h1 class="detail-title"><?= escapeHtml($post['title']) ?></h1>

                <div class="detail-meta">
                    <span class="meta-item">
                        📅 <?= date('Y年m月d日', strtotime($post['created_at'])) ?>
                    </span>
                    <?php if ($showViewCount && isset($post['view_count'])): ?>
                        <span class="meta-item view-count">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: -2px;">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                            </svg>
                            <?= number_format($post['view_count']) ?> 回閲覧
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($post['tags'])): ?>
                    <div class="detail-tags">
                        <?php
                        $tags = explode(',', $post['tags']);
                        foreach ($tags as $tag):
                            $tag = trim($tag);
                            if (!empty($tag)):
                        ?>
                            <span class="tag"><?= escapeHtml($tag) ?></span>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($post['detail'])): ?>
<div class="detail-description"><?= escapeHtml($post['detail']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- フッター -->
    <footer>
        <p><?= !empty($theme['footer_html']) ? nl2br(escapeHtml($theme['footer_html'])) : '&copy; ' . date('Y') . ' イラストポートフォリオ. All rights reserved.' ?></p>
    </footer>

    <!-- JavaScript -->
    <script src="/res/js/detail.js?v=<?= $nsfwConfigVersion ?>"></script>
    <script>
        // DOMロード後に初期化
        document.addEventListener('DOMContentLoaded', function() {
            // data属性から値を読み取る
            const isSensitive = document.body.dataset.isSensitive === '1';
            const postId = parseInt(document.body.dataset.postId);

            // 年齢確認チェック
            initDetailPage(isSensitive);

            // 閲覧回数をインクリメント
            incrementViewCount(postId);
        });
    </script>
</body>
</html>
