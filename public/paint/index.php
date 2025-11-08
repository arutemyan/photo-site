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
} catch (Exception $e) {
    Logger::getInstance()->error('Paint Gallery Error: ' . $e->getMessage());
    $theme = [];
    $siteTitle = 'ペイントギャラリー';
    $siteSubtitle = 'キャンバスで描いたオリジナルイラスト作品集';
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

    <!-- テーマカラー -->
    <style>
        <?php require_once(__DIR__ . '/../block/style.php') ?>
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <header>
        <?php if (!empty($theme['logo_image'])): ?>
            <img src="/<?= escapeHtml($theme['logo_image']) ?>" alt="<?= escapeHtml($theme['site_title'] ?? 'ロゴ') ?>" style="max-height: 80px; margin-bottom: 10px;">
        <?php endif; ?>
        <h1>🎨 <?= escapeHtml($siteTitle) ?></h1>

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
                <span class="filter-label">タグ:</span>
                <button class="tag-btn active" data-tag="" onclick="showAllIllusts()">すべて</button>
                <div id="tagList"></div>
            </div>
            <div class="filter-row" style="margin-top: 15px;">
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
