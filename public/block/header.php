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