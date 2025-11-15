<?php
/**
 * Paint Gallery - イラスト一覧ページ
 * public/paint/index.php
 */

require_once(__DIR__ . '/../../vendor/autoload.php');
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Models\Theme;
use App\Models\Setting;
use App\Utils\Logger;

try {
    // テーマ設定を取得
    $themeModel = new Theme();
    $theme = $themeModel->getCurrent();

    // サイト設定を取得
    $settingModel = new Setting();
    $siteTitle = $theme['site_title'] ?? 'ペイントギャラリー';
    $siteSubtitle = $theme['site_subtitle'] ?? 'キャンバスで描いたオリジナルイラスト作品集';

    // NSFW設定を取得
    $nsfwConfig = $config['nsfw'];
    $ageVerificationMinutes = $nsfwConfig['age_verification_minutes'];
    $nsfwConfigVersion = $nsfwConfig['config_version'];
} catch (Exception $e) {
    Logger::getInstance()->error('Paint Gallery Error: ' . $e->getMessage());
    $theme = [];
    $siteTitle = 'ペイントギャラリー';
    $siteSubtitle = 'キャンバスで描いたオリジナルイラスト作品集';
    $ageVerificationMinutes = 10080;
    $nsfwConfigVersion = 1;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ペイントギャラリー</title>
    <meta name="description" content="オリジナルイラスト作品ギャラリー">
    
    <!-- Googleフォント -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- スタイルシート -->
    <?php echo \App\Utils\AssetHelper::linkTag('/res/css/main.css'); ?>
    <?php echo \App\Utils\AssetHelper::linkTag('/paint/css/gallery.css'); ?>
    <?php echo \App\Utils\AssetHelper::linkTag('/res/css/inline-styles.css'); ?>

    <!-- テーマカラー -->
    <style>
        <?php require_once(__DIR__ . '/../block/style.php') ?>
    </style>
</head>
<body data-age-verification-minutes="<?= $ageVerificationMinutes ?>" data-nsfw-config-version="<?= $nsfwConfigVersion ?>">
    <script nonce="<?= \App\Security\CspMiddleware::getInstance()->getNonce() ?>">
        // 設定値をdata属性から読み込み（const定義で改ざん防止）
        const AGE_VERIFICATION_MINUTES = parseFloat(document.body.dataset.ageVerificationMinutes) || 10080;
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
                <p class="muted-small">
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
            <img src="/<?= escapeHtml($theme['logo_image']) ?>" alt="<?= escapeHtml($theme['site_title'] ?? 'ロゴ') ?>" class="img-logo">
        <?php endif; ?>
        <h1><?= escapeHtml($siteTitle) ?></h1>

        <?php if (!empty($siteSubtitle)): ?>
            <p><?= escapeHtml($siteSubtitle) ?></p>
        <?php endif; ?>
    </header>
    <a href="/index.php" class="back-link">
        <div class="header-back-button">
            <?= escapeHtml($theme['back_button_text'] ?? '一覧に戻る') ?>
        </div>
    </a>
    
    <!-- メインコンテンツ -->
    <div class="container">
        <!-- フィルターセクション -->
        <div class="filter-section">
            <div class="filter-row">
                <span class="filter-label">表示:</span>
                <button class="filter-btn active" data-nsfw-filter="all" onclick="setNSFWFilter('all')">すべて</button>
                <button class="filter-btn" data-nsfw-filter="safe" onclick="setNSFWFilter('safe')">一般</button>
                <button class="filter-btn" data-nsfw-filter="nsfw" onclick="setNSFWFilter('nsfw')">NSFW</button>
            </div>
            <div class="filter-row mt-3">
                <span class="filter-label">タグ:</span>
                <button class="tag-btn active" data-tag="" onclick="showAllPaints()">すべて</button>
                <div id="tagList"></div>
            </div>
            <div class="filter-row mt-3">
                <span class="filter-label">検索:</span>
                <div class="search-box">
                    <input
                        type="text"
                        id="searchInput"
                        class="search-input"
                        placeholder="タイトルや説明で検索..."
                    >
                </div>
            </div>
        </div>
        
        <!-- ギャラリーグリッド -->
        <div id="galleryGrid" class="gallery-grid">
            <!-- JavaScriptで動的に読み込まれます -->
        </div>
        
        <!-- ローディング -->
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>読み込み中...</p>
        </div>
    </div>

    <!-- JavaScript -->
    <?php echo \App\Utils\AssetHelper::scriptTag('/paint/js/gallery.js'); ?>
</body>
</html>
