<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Security/SecurityUtil.php';

use App\Models\Post;
use App\Models\GroupPost;
use App\Models\Theme;
use App\Models\Setting;
use App\Models\Tag;
use App\Database\Connection;

// セットアップチェック
try {
    $db = Connection::getInstance();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        // セットアップが必要
        // setup-*.php ファイルを探す
        $setupFiles = glob(__DIR__ . '/setup*.php');

        if (!empty($setupFiles)) {
            $setupFile = basename($setupFiles[0]);
            ?>
            <!DOCTYPE html>
            <html lang="ja">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>初回セットアップが必要です</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                    }
                    .container {
                        background: white;
                        padding: 40px;
                        border-radius: 10px;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                        max-width: 500px;
                        width: 100%;
                        text-align: center;
                    }
                    h1 {
                        color: #333;
                        margin-bottom: 20px;
                        font-size: 2em;
                    }
                    p {
                        color: #666;
                        line-height: 1.6;
                        margin-bottom: 30px;
                    }
                    .btn {
                        display: inline-block;
                        padding: 14px 28px;
                        background: #667eea;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: 600;
                        transition: background 0.3s;
                    }
                    .btn:hover {
                        background: #764ba2;
                    }
                    .icon {
                        font-size: 4em;
                        margin-bottom: 20px;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="icon">🎨</div>
                    <h1>ようこそ！</h1>
                    <p>
                        このサイトは初回セットアップが必要です。<br>
                        管理者アカウントを作成してください。
                    </p>
                    <a href="/<?= htmlspecialchars($setupFile) ?>" class="btn">セットアップを開始</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        } else {
            // setup.phpが見つからない
            ?>
            <!DOCTYPE html>
            <html lang="ja">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>セットアップファイルが見つかりません</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                    }
                    .container {
                        background: white;
                        padding: 40px;
                        border-radius: 10px;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                        max-width: 600px;
                        width: 100%;
                    }
                    h1 {
                        color: #dc3545;
                        margin-bottom: 20px;
                    }
                    .alert {
                        background: #f8d7da;
                        border: 1px solid #f5c6cb;
                        border-radius: 5px;
                        padding: 15px;
                        margin: 20px 0;
                        color: #721c24;
                    }
                    code {
                        background: #f4f4f4;
                        padding: 2px 6px;
                        border-radius: 3px;
                        font-family: monospace;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>⚠️ エラー</h1>
                    <div class="alert">
                        セットアップファイル（setup.php）が見つかりません。<br><br>
                        プロジェクトルートから以下のコマンドを実行して、<br>
                        CLIでセットアップを行ってください：<br><br>
                        <code>php init.php</code>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
} catch (Exception $e) {
    error_log('Setup check error: ' . $e->getMessage());
    // エラーが発生してもページは表示する
}

try {
    // テーマ設定を取得
    $themeModel = new Theme();
    $theme = $themeModel->getCurrent();

    // サイト設定を取得
    $settingModel = new Setting();
    $showViewCount = $settingModel->get('show_view_count', '1') === '1';

    // OGP設定を取得
    $ogpTitle = $settingModel->get('ogp_title', '') ?: ($theme['site_title'] ?? 'イラストポートフォリオ');
    $ogpDescription = $settingModel->get('ogp_description', '') ?: ($theme['site_description'] ?? 'イラストレーターのポートフォリオサイト');
    $ogpImage = $settingModel->get('ogp_image', '');
    $twitterCard = $settingModel->get('twitter_card', 'summary_large_image');
    $twitterSite = $settingModel->get('twitter_site', '');

    // OGP画像の絶対URLを生成
    $ogpImageUrl = '';
    if ($ogpImage) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $ogpImageUrl = $protocol . '://' . $host . '/' . $ogpImage;
    }

    // 設定を読み込み
    $config = require __DIR__ . '/../config/config.php';
    $nsfwConfig = $config['nsfw'];
    $ageVerificationMinutes = $nsfwConfig['age_verification_minutes'];
    $nsfwConfigVersion = $nsfwConfig['config_version'];

    // シングル投稿を取得
    $postModel = new Post();
    $singlePosts = $postModel->getAll(18);

    // グループ投稿を取得
    $groupPostModel = new GroupPost();
    $groupPosts = $groupPostModel->getAll(18);

    // 両方をマージして作成日時でソート
    $allPosts = array_merge($singlePosts, $groupPosts);
    usort($allPosts, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // 最大18件に制限
    $posts = array_slice($allPosts, 0, 18);

    // 各投稿にタイプを追加
    foreach ($posts as &$post) {
        // image_countがあればグループ投稿
        if (isset($post['image_count'])) {
            $post['post_type'] = 'group';
        } else {
            $post['post_type'] = 'single';
        }
    }

    // タグ一覧を取得（ID, name, post_count）
    $tagModel = new Tag();
    $tags = $tagModel->getPopular(50); // 上位50件のタグ

} catch (Exception $e) {
    error_log('Index Error: ' . $e->getMessage());
    $posts = [];
    $tags = [];
    $theme = ['header_html' => '', 'footer_html' => '', 'site_title' => 'イラストポートフォリオ', 'site_description' => 'イラストレーターのポートフォリオサイト'];
    $showViewCount = true;
    $ageVerificationMinutes = 10080;
    $nsfwConfigVersion = 1;
    $ogpTitle = 'イラストポートフォリオ';
    $ogpDescription = 'イラストレーターのポートフォリオサイト';
    $ogpImage = '';
    $ogpImageUrl = '';
    $twitterCard = 'summary_large_image';
    $twitterSite = '';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ') ?></title>
    <meta name="description" content="<?= escapeHtml($theme['site_description'] ?? 'イラストレーターのポートフォリオサイト') ?>">

    <!-- OGP (Open Graph Protocol) -->
    <meta property="og:title" content="<?= escapeHtml($ogpTitle ?? $theme['site_title'] ?? 'イラストポートフォリオ') ?>">
    <meta property="og:type" content="website">
    <meta property="og:description" content="<?= escapeHtml($ogpDescription ?? $theme['site_description'] ?? 'イラストレーターのポートフォリオサイト') ?>">
    <meta property="og:url" content="<?= htmlspecialchars((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'], ENT_QUOTES) ?>">
    <?php if (!empty($ogpImageUrl)): ?>
    <meta property="og:image" content="<?= escapeHtml($ogpImageUrl) ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?= escapeHtml($twitterCard ?? 'summary_large_image') ?>">
    <?php if (!empty($twitterSite)): ?>
    <meta name="twitter:site" content="@<?= escapeHtml($twitterSite) ?>">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= escapeHtml($ogpTitle ?? $theme['site_title'] ?? 'イラストポートフォリオ') ?>">
    <meta name="twitter:description" content="<?= escapeHtml($ogpDescription ?? $theme['site_description'] ?? 'イラストレーターのポートフォリオサイト') ?>">
    <?php if (!empty($ogpImageUrl)): ?>
    <meta name="twitter:image" content="<?= escapeHtml($ogpImageUrl) ?>">
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
        .btn-detail {
            background: var(--primary-color);
        }

        .btn-primary:hover,
        .btn-detail:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body data-age-verification-minutes="<?= $ageVerificationMinutes ?>" data-nsfw-config-version="<?= $nsfwConfigVersion ?>">
    <script>
        // 設定値をdata属性から読み込み（const定義で改ざん防止）
        const AGE_VERIFICATION_MINUTES = parseInt(document.body.dataset.ageVerificationMinutes) || 10080;
        const NSFW_CONFIG_VERSION = parseInt(document.body.dataset.nsfwConfigVersion) || 1;
        // タグ一覧（ID, name, post_count）
        const TAGS_DATA = <?= json_encode($tags, JSON_UNESCAPED_UNICODE) ?>;
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
        <?php if (!empty($theme['logo_image'])): ?>
            <img src="/<?= escapeHtml($theme['logo_image']) ?>" alt="<?= escapeHtml($theme['site_title'] ?? 'ロゴ') ?>" style="max-height: 80px; margin-bottom: 10px;">
        <?php endif; ?>
        <h1><?= !empty($theme['header_html']) ? escapeHtml($theme['header_html']) : escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ') ?></h1>
        <?php if (!empty($theme['site_subtitle'])): ?>
            <p><?= escapeHtml($theme['site_subtitle']) ?></p>
        <?php endif; ?>
    </header>

    <!-- メインコンテンツ -->
    <div class="container">
        <!-- フィルタエリア -->
        <div class="filter-section">
            <div class="filter-compact">
                <div class="filter-group">
                    <span class="filter-label">表示:</span>
                    <button class="filter-btn filter-btn-compact active" data-filter="all" onclick="setNSFWFilter('all')">すべて</button>
                    <button class="filter-btn filter-btn-compact" data-filter="safe" onclick="setNSFWFilter('safe')">一般</button>
                    <button class="filter-btn filter-btn-compact" data-filter="nsfw" onclick="setNSFWFilter('nsfw')">NSFW</button>
                    <span class="filter-separator">|</span>
                    <button class="toggle-btn active" id="toggleTags" onclick="toggleTagsVisibility()" title="タグの表示/非表示を切り替え">タグ</button>
                    <button class="toggle-btn active" id="toggleTitles" onclick="toggleTitlesVisibility()" title="タイトルの表示/非表示を切り替え">表題</button>
                </div>
                <div class="filter-group">
                    <span class="filter-label">タグ:</span>
                    <button class="tag-btn tag-btn-compact tag-btn-all active" data-tag="" onclick="clearTagFilter(); setActiveTagButton(this);">すべて</button>
                    <div id="tagList" style="display: inline;">
                        <!-- JavaScriptで動的に読み込まれます -->
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <span style="font-size: 4em;">🎨</span>
                <h2>まだ投稿がありません</h2>
                <p>管理画面から作品を投稿してください</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($posts as $post): ?>
                    <?php
                    $isSensitive = isset($post['is_sensitive']) && $post['is_sensitive'] == 1;
                    $thumbPath = '/' . escapeHtml($post['thumb_path'] ?? $post['image_path'] ?? '');
                    // センシティブ画像の場合、NSFWフィルター版を使用
                    if ($isSensitive) {
                        $pathInfo = pathinfo($thumbPath);
                        $nsfwPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp');
                        $imagePath = $nsfwPath;
                    } else {
                        $imagePath = $thumbPath;
                    }
                    $isGroup = isset($post['post_type']) && $post['post_type'] === 'group';
                    $detailUrl = $isGroup ? '/group_detail.php?id=' . $post['id'] : '/detail.php?id=' . $post['id'];
                    ?>
                    <div class="card <?= $isSensitive ? 'nsfw-card' : '' ?><?= $isGroup ? ' group-card' : '' ?>" data-post-id="<?= $post['id'] ?>" data-post-type="<?= $isGroup ? 'group' : 'single' ?>">
                        <div class="card-img-wrapper <?= $isSensitive ? 'nsfw-wrapper' : '' ?>"
                             <?= $isGroup ? 'onclick="window.location.href=\'' . $detailUrl . '\'"' : 'onclick="openImageOverlay(' . $post['id'] . ', ' . ($isSensitive ? 'true' : 'false') . ')"' ?>
                             style="cursor: pointer;">
                            <img
                                src="<?= $imagePath ?>"
                                alt="<?= escapeHtml($post['title']) ?>"
                                class="card-image"
                                loading="lazy"
                                onerror="if(!this.dataset.errorHandled){this.dataset.errorHandled='1';this.src='/uploads/thumbs/placeholder.webp';}"
                                <?= !$isGroup ? 'data-full-image="/' . escapeHtml($post['image_path'] ?? $post['thumb_path'] ?? '') . '"' : '' ?>
                                data-is-sensitive="<?= $isSensitive ? '1' : '0' ?>"
                            >
                            <?php if ($isGroup && isset($post['image_count'])): ?>
                                <div class="group-badge">
                                    <i class="bi bi-images"></i> <?= $post['image_count'] ?>枚
                                </div>
                            <?php endif; ?>
                            <?php if ($isSensitive): ?>
                                <div class="nsfw-overlay">
                                    <div class="nsfw-text">センシティブな内容</div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($post['tags'])): ?>
                                <div class="card-tags">
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
                        </div>
                        <div class="card-content">
                            <h2 class="card-title"><?= escapeHtml($post['title']) ?></h2>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ローディングインジケーター -->
            <div id="loadingIndicator" class="loading-indicator">
                <div class="loading-spinner"></div>
                <p>読み込み中...</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- 画像オーバーレイモーダル -->
    <div id="imageOverlay" class="image-overlay" onclick="closeImageOverlay(event)">
        <div class="image-overlay-content">
            <button class="image-overlay-close" onclick="closeImageOverlay(event)">&times;</button>
            <button class="image-overlay-nav image-overlay-prev" onclick="navigateOverlay(event, -1)" aria-label="前の画像">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button class="image-overlay-nav image-overlay-next" onclick="navigateOverlay(event, 1)" aria-label="次の画像">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <img id="overlayImage" src="" alt="画像プレビュー">
            <a id="overlayDetailButton" href="#" class="btn btn-detail overlay-detail-btn">
                詳細を表示
            </a>
        </div>
    </div>

    <!-- NSFW警告モーダル（オーバーレイナビゲーション用） -->
    <div id="nsfwWarningModal" class="modal">
        <div class="modal-content">
            <h2>⚠️ センシティブなコンテンツ</h2>
            <p>この画像にはセンシティブな内容が含まれています。</p>
            <p>表示しますか？</p>
            <div class="modal-buttons">
                <button class="btn btn-primary" onclick="acceptNsfwWarning()">表示する</button>
                <button class="btn btn-secondary" onclick="cancelNsfwWarning()">キャンセル</button>
            </div>
        </div>
    </div>

    <!-- フッター -->
    <footer>
        <p><?= !empty($theme['footer_html']) ? nl2br(escapeHtml($theme['footer_html'])) : '&copy; ' . date('Y') . ' イラストポートフォリオ. All rights reserved.' ?></p>
    </footer>

    <!-- JavaScript -->
    <script src="/res/js/main.js?v=<?= $nsfwConfigVersion ?>"></script>
</body>
</html>
