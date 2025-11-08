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
<?php if (defined("ENABLE_BACK_BUTTON")) { ?>
<?php
// 一覧に戻るボタンの設定
$backButtonText = $theme['back_button_text'] ?? '一覧に戻る';
$backButtonBgColor = $theme['back_button_bg_color'] ?? '#8B5AFA';
$backButtonTextColor = $theme['back_button_text_color'] ?? '#FFFFFF';
?>
<a href="/index.php" class="back-link">
    <div class="header-back-button" style="background-color: <?= escapeHtml($backButtonBgColor) ?>; color: <?= escapeHtml($backButtonTextColor) ?>;">
        <?= escapeHtml($backButtonText) ?>
    </div>
</a>
<?php } ?>