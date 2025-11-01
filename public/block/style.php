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