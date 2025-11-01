<!-- フッター -->
<footer>
    <p><?= !empty($theme['footer_html']) ? nl2br(escapeHtml($theme['footer_html'])) : '&copy; ' . date('Y') . ' イラストポートフォリオ. All rights reserved.' ?></p>
</footer>