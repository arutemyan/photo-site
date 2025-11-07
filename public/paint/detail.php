<?php
/**
 * Paint Detail - イラスト詳細ページ
 * public/paint/detail.php
 */

require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../../config/config.php');

use App\Models\Theme;
use App\Models\Setting;
use App\Utils\Logger;

// テーマ設定を読み込む
try {
    $themeModel = new Theme();
    $theme = $themeModel->getCurrent();
    $siteTitle = $theme['site_title'] ?? 'ペイントギャラリー';
    $siteSubtitle = $theme['site_subtitle'] ?? 'キャンバスで描いたオリジナルイラスト作品集';
} catch (Exception $e) {
    $theme = [];
    $siteTitle = 'ペイントギャラリー';
    $siteSubtitle = 'キャンバスで描いたオリジナルイラスト作品集';
}

// パラメータ取得
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /paint/');
    exit;
}

try {
    $db = \App\Database\Connection::getInstance();
    
    // イラスト情報取得
    $sql = "SELECT 
                i.id,
                i.title,
                '' as detail,
                i.image_path,
                i.thumbnail_path as thumb_path,
                i.data_path,
                i.timelapse_path,
                i.canvas_width as width,
                i.canvas_height as height,
                i.created_at,
                i.updated_at,
                '' as tags
            FROM illusts i
            WHERE i.id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $illust = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$illust) {
        header('Location: /paint/');
        exit;
    }
    
    // タグを配列に変換
    $tags = []; // $illust['tags'] ? explode(',', $illust['tags']) : [];
    
    // 関連イラスト取得（最新のイラストを取得）
    $relatedIllusts = [];
    /*
    if (!empty($tags)) {
        $placeholders = implode(',', array_fill(0, count($tags), '?'));
        $relatedSql = "SELECT DISTINCT
                        i.id,
                        i.title,
                        i.thumb_path,
                        i.image_path
                    FROM illusts i
                    INNER JOIN illust_tags it ON i.id = it.illust_id
                    INNER JOIN tags t ON it.tag_id = t.id
                    WHERE t.name IN ($placeholders)
                      AND i.id != ?
                    ORDER BY i.created_at DESC
                    LIMIT 6";
        
        $relatedStmt = $db->prepare($relatedSql);
        foreach ($tags as $index => $tag) {
            $relatedStmt->bindValue($index + 1, trim($tag));
        }
        $relatedStmt->bindValue(count($tags) + 1, $id, PDO::PARAM_INT);
        $relatedStmt->execute();
        $relatedIllusts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    */
    
    // タグがない場合は最新のイラストを取得
    $relatedSql = "SELECT 
                    i.id,
                    i.title,
                    i.thumbnail_path as thumb_path,
                    i.image_path
                FROM illusts i
                WHERE i.id != ?
                ORDER BY i.created_at DESC
                LIMIT 6";
    
    $relatedStmt = $db->prepare($relatedSql);
    $relatedStmt->bindValue(1, $id, PDO::PARAM_INT);
    $relatedStmt->execute();
    $relatedIllusts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    Logger::getInstance()->error('Illust Detail Error: ' . $e->getMessage());
    header('Location: /paint/');
    exit;
}

// OGP用の画像URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$imageUrl = $protocol . $host . ($illust['thumb_path'] ?? $illust['image_path']);
$pageUrl = $protocol . $host . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapeHtml($illust['title']) ?> - <?= escapeHtml($siteTitle) ?></title>
    <meta name="description" content="<?= escapeHtml(mb_substr($illust['detail'] ?? $illust['title'], 0, 200)) ?>">
    
    <!-- OGP -->
    <meta property="og:title" content="<?= escapeHtml($illust['title']) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= escapeHtml($pageUrl) ?>">
    <meta property="og:image" content="<?= escapeHtml($imageUrl) ?>">
    <meta property="og:description" content="<?= escapeHtml(mb_substr($illust['detail'] ?? $illust['title'], 0, 200)) ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= escapeHtml($illust['title']) ?>">
    <meta name="twitter:description" content="<?= escapeHtml(mb_substr($illust['detail'] ?? $illust['title'], 0, 200)) ?>">
    <meta name="twitter:image" content="<?= escapeHtml($imageUrl) ?>">
    
    <!-- Googleフォント -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- スタイルシート -->
    <?php echo \App\Utils\AssetHelper::linkTag('/paint/css/gallery.css'); ?>
    <?php echo \App\Utils\AssetHelper::linkTag('/paint/css/detail.css'); ?>
    
    <!-- テーマカラー -->
    <style>
        <?php require_once(__DIR__ . '/../block/style.php') ?>
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <header class="header">
        <a href="/paint/" class="back-link">← ギャラリー</a>
        <?php if (!empty($theme['logo_image'])): ?>
            <img src="/<?= escapeHtml($theme['logo_image']) ?>" alt="<?= escapeHtml($siteTitle) ?>" style="max-height: 80px; margin-bottom: 10px;">
        <?php endif; ?>
        <h1>🎨 <?= escapeHtml($siteTitle) ?></h1>
    </header>
    
    <!-- メインコンテンツ -->
    <div class="detail-container">
        <div class="detail-card">
            <!-- イラスト画像 -->
            <div class="detail-image-wrapper">
                <img 
                    src="<?= escapeHtml($illust['image_path']) ?>" 
                    alt="<?= escapeHtml($illust['title']) ?>"
                    class="detail-image"
                >
            </div>
            
            <!-- イラスト情報 -->
            <div class="detail-content">
                <div class="detail-header">
                    <h2 class="detail-title"><?= escapeHtml($illust['title']) ?></h2>
                    <div class="detail-meta">
                        <span class="detail-meta-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?= date('Y年m月d日', strtotime($illust['created_at'])) ?>
                        </span>
                        <?php if ($illust['width'] && $illust['height']): ?>
                        <span class="detail-meta-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            </svg>
                            <?= $illust['width'] ?>×<?= $illust['height'] ?>px
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($illust['detail'])): ?>
                <div class="detail-description">
                    <?= nl2br(escapeHtml($illust['detail'])) ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($tags)): ?>
                <div class="detail-tags">
                    <?php foreach ($tags as $tag): ?>
                    <a href="/paint/?tag=<?= urlencode(trim($tag)) ?>" class="tag">
                        <?= escapeHtml(trim($tag)) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="detail-actions">
                    <a href="<?= escapeHtml($illust['image_path']) ?>" download class="action-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        画像をダウンロード
                    </a>
                    <?php if (!empty($illust['timelapse_path'])): ?>
                    <button class="action-btn" onclick="openTimelapseOverlay()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                        </svg>
                        タイムラプスを再生
                    </button>
                    <?php endif; ?>
                    <a href="/paint/" class="action-btn secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        ギャラリーに戻る
                    </a>
                </div>
            </div>
        </div>
        
        <!-- 関連イラスト -->
        <?php if (!empty($relatedIllusts)): ?>
        <div class="related-section">
            <h3 class="related-title">関連イラスト</h3>
            <div class="related-grid">
                <?php foreach ($relatedIllusts as $related): ?>
                <div class="illust-card" onclick="window.location.href='/paint/detail.php?id=<?= $related['id'] ?>'">
                    <div class="illust-image-wrapper">
                        <img 
                            src="<?= escapeHtml($related['thumb_path'] ?? $related['image_path']) ?>" 
                            alt="<?= escapeHtml($related['title']) ?>"
                            class="illust-image"
                            loading="lazy"
                        >
                    </div>
                    <div class="illust-info">
                        <h4 class="illust-title"><?= escapeHtml($related['title']) ?></h4>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- タイムラプスオーバーレイ -->
    <?php if (!empty($illust['timelapse_path'])): ?>
    <div id="timelapseOverlay" class="timelapse-overlay" onclick="closeTimelapseOverlay(event)">
        <div class="timelapse-overlay-content" onclick="event.stopPropagation()">
            <button class="timelapse-overlay-close" onclick="closeTimelapseOverlay()">&times;</button>
            <h3 class="timelapse-overlay-title">制作過程タイムラプス</h3>
            <div class="timelapse-player">
                <canvas id="timelapseCanvas" class="timelapse-canvas"></canvas>
                <div class="timelapse-controls">
                    <button id="timelapsePlayBtn" class="timelapse-play-btn" onclick="togglePlayback()">▶</button>
                    <div id="timelapseProgress" class="timelapse-progress">
                        <div id="timelapseProgressBar" class="timelapse-progress-bar"></div>
                    </div>
                    <div id="timelapseTime" class="timelapse-time">0 / 0</div>
                    <div class="timelapse-speed">
                        <button class="speed-btn" data-speed="0.5" onclick="changeSpeed(0.5)">0.5x</button>
                        <button class="speed-btn active" data-speed="1" onclick="changeSpeed(1)">1x</button>
                        <button class="speed-btn" data-speed="2" onclick="changeSpeed(2)">2x</button>
                        <button class="speed-btn" data-speed="4" onclick="changeSpeed(4)">4x</button>
                    </div>
                    <div class="timelapse-options" style="margin-top: 10px; text-align: center;">
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="ignoreTimestamps" onchange="toggleIgnoreTimestamps(this.checked)" checked>
                            <span>時間を無視（等間隔再生）</span>
                        </label>
                        <div>
                            <small style="display: block; margin-top: 4px; color: #666; font-size: 0.85em;">
                                ※ チェックを外すと制作時の実時間で再生します（タイムスタンプが記録されている場合）
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <script type="module" src="/paint/js/detail.js"></script>
    <?php if (!empty($illust['timelapse_path'])): ?>
    <script type="module">
        import { initTimelapse } from '/paint/js/detail.js';
        document.addEventListener('DOMContentLoaded', () => {
            initTimelapse(<?= $id ?>);
        });
    </script>
    <?php endif; ?>
</body>
</html>
