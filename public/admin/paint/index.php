<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Security\CsrfProtection;
use App\Utils\PathHelper;

// 管理画面用お絵描き機能
// 共通認証ヘルパで認証を統一
\App\Controllers\AdminControllerBase::ensureAuthenticated(true);

// Admin check - support both session formats (legacy compatibility)
$isAdmin = false;
$sess = \App\Services\Session::getInstance();
if ($sess->get('admin_logged_in') === true) {
    $isAdmin = true;
} elseif (is_array($sess->get('admin'))) {
    $isAdmin = true;
}

$csrf = null;
try {
    $sess = \App\Services\Session::getInstance();
    $csrf = $sess->getCsrfToken();
} catch (Throwable $e) {
    // fall back to legacy CsrfProtection
    $csrf = CsrfProtection::getToken();
}
?><!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>お絵描き - 管理</title>
    <?php echo \App\Utils\AssetHelper::linkTag(PathHelper::getAdminUrl('/paint/css/style.css')); ?>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="header-left">
        <h1 class="header-title">お絵描き</h1>
        <div class="illust-info-display">
            <div class="illust-id-display">
                <strong>ID:</strong> <span id="illust-id">(未保存)</span>
            </div>
            <div class="illust-title-display">
                <strong>タイトル:</strong> <span id="illust-title-display">(未保存)</span>
            </div>
        </div>
    </div>

    <div class="header-center">
        <button class="header-btn secondary" id="btn-new">新規作成</button>
    <button class="header-btn secondary" id="btn-open">開く</button>
        <button class="header-btn secondary" id="btn-clear">クリア</button>
        <button class="header-btn secondary" id="btn-resize">サイズ変更</button>
    </div>

    <div class="header-right">
    <button class="header-btn" id="btn-save" style="display:none;">保存</button>
        <button class="header-btn secondary" id="btn-save-as">名前を付けて保存</button>
        <button class="header-btn secondary" id="btn-timelapse">タイムラプス</button>
        <button class="header-btn secondary" id="btn-export">エクスポート</button>
        <label class="header-btn secondary" for="import-file-input" id="btn-import" style="cursor:pointer;">インポート</label>
        <input type="file" id="import-file-input" accept=".json,.gz,.json.gz,.paint" style="display:none" />
    </div>
</header>

<!-- 管理セッションがない場合は認証で弾かれるはずなので、公開 UI を表示しないようにしました -->

<!-- Main Container -->
<div class="main-container">

    <!-- Toolbar (Vertical) -->
    <div class="toolbar">
        <button class="tool-btn active" id="tool-pen" title="ペン (P)" data-tool="pen">🖊️</button>
        <button class="tool-btn" id="tool-eraser" title="消しゴム (E)" data-tool="eraser">🧽</button>
        <button class="tool-btn" id="tool-watercolor" title="水彩ブラシ (W)" data-tool="watercolor">🎨</button>
        <button class="tool-btn" id="tool-bucket" title="塗りつぶし (B)" data-tool="bucket">🪣</button>
        <button class="tool-btn" id="tool-eyedropper" title="スポイト (I)" data-tool="eyedropper">💧</button>

        <div class="tool-separator"></div>

        <button class="tool-btn" id="tool-undo" title="元に戻す (Ctrl+Z)">↶</button>
        <button class="tool-btn" id="tool-redo" title="やり直し (Ctrl+Y)">↷</button>

        <div class="tool-separator"></div>

        <button class="tool-btn" id="tool-zoom-in" title="拡大">🔍+</button>
        <button class="tool-btn" id="tool-zoom-out" title="縮小">🔍-</button>
        <button class="tool-btn" id="tool-zoom-fit" title="フィット">📐</button>

        <div class="tool-separator"></div>

        <button class="tool-btn" id="tool-rotate-cw" title="右に90度回転">↻</button>
        <button class="tool-btn" id="tool-rotate-ccw" title="左に90度回転">↺</button>
        <button class="tool-btn" id="tool-flip-h" title="左右反転">⇄</button>
        <button class="tool-btn" id="tool-flip-v" title="上下反転">⇅</button>
    </div>

    <!-- Canvas Area -->
    <div class="canvas-area">
        <div id="canvas-wrap">
            <!-- 4 layers (from back to front) -->
            <canvas class="layer" data-layer="0" width="512" height="512"></canvas>
            <canvas class="layer" data-layer="1" width="512" height="512"></canvas>
            <canvas class="layer" data-layer="2" width="512" height="512"></canvas>
            <canvas class="layer" data-layer="3" width="512" height="512"></canvas>
            <div class="canvas-info">512 x 512 px</div>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">

        <!-- Color Palette Section -->
        <div class="panel-section color-palette" data-panel="color-palette">
            <div class="panel-header">
                <h3 class="panel-title">カラーパレット</h3>
                <button class="panel-toggle" title="開閉">▼</button>
            </div>
            <div class="panel-content">

            <div class="color-current">
                <div class="color-swatch" id="current-color" style="background:#000000;" title="現在の色"></div>
                <div style="flex: 1;">
                    <div id="current-color-hex" style="font-size:12px;font-weight:600;color:#666;">#000000</div>
                    <div id="current-color-rgb" style="font-size:10px;color:#999;">RGB(0, 0, 0)</div>
                </div>
                <button class="color-edit-btn" id="current-color-edit-btn" title="色を編集">EDIT</button>
            </div>

            <div style="font-size:10px;color:#999;margin-bottom:8px;text-align:center;">
                パレット: クリック=選択 / ダブルクリック=編集
            </div>

            <div class="color-grid" id="color-palette-grid">
                <!-- 16色パレット (動的生成) -->
            </div>
            </div>
            <div class="panel-resize-handle"></div>
        </div>

        <!-- Tool Settings Section -->
        <div class="panel-section tool-settings" data-panel="tool-settings">
            <div class="panel-header">
                <h3 class="panel-title">ツール設定</h3>
                <button class="panel-toggle" title="開閉">▼</button>
            </div>
            <div class="panel-content">

            <div id="pen-settings" class="tool-settings-group">
                <div class="setting-row">
                    <label class="setting-label">
                        太さ: <span class="setting-value" id="pen-size-value">4</span>px
                    </label>
                    <input type="range" id="pen-size" class="setting-slider" min="1" max="50" value="4">
                </div>

                <div class="setting-row">
                    <label class="setting-label">
                        筆圧を有効にする:
                        <input type="checkbox" id="pen-pressure-enabled" checked>
                    </label>
                </div>

                <div class="setting-row">
                    <label class="setting-label">
                        筆圧影響度: <span class="setting-value" id="pen-pressure-value">100%</span>
                    </label>
                    <input type="range" id="pen-pressure-influence" class="setting-slider" min="0" max="100" value="100">
                </div>

                <div class="setting-row">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="pen-antialias" checked>
                        <label for="pen-antialias">アンチエイリアス</label>
                    </div>
                </div>
            </div>

            <div id="eraser-settings" class="tool-settings-group hidden">
                <div class="setting-row">
                    <label class="setting-label">
                        太さ: <span class="setting-value" id="eraser-size-value">10</span>px
                    </label>
                    <input type="range" id="eraser-size" class="setting-slider" min="1" max="100" value="10">
                </div>
                <div class="setting-row">
                    <label class="setting-label">
                        筆圧を有効にする（消しゴム）:
                        <input type="checkbox" id="eraser-pressure-enabled" checked>
                    </label>
                </div>

                <div class="setting-row">
                    <label class="setting-label">
                        筆圧影響度（消しゴム）: <span class="setting-value" id="eraser-pressure-value">100%</span>
                    </label>
                    <input type="range" id="eraser-pressure-influence" class="setting-slider" min="0" max="100" value="100">
                </div>
            </div>

            <div id="bucket-settings" class="tool-settings-group hidden">
                <div class="setting-row">
                    <label class="setting-label">
                        許容値: <span class="setting-value" id="bucket-tolerance-value">32</span>
                    </label>
                    <input type="range" id="bucket-tolerance" class="setting-slider" min="0" max="255" value="32">
                </div>
            </div>

            <div id="watercolor-settings" class="tool-settings-group hidden">
                <div class="setting-row">
                    <label class="setting-label">
                        サイズ: <span class="setting-value" id="watercolor-max-size-value">40</span>px
                    </label>
                    <input type="range" id="watercolor-max-size" class="setting-slider" min="5" max="200" value="40">
                </div>

                <div class="setting-row">
                    <label class="setting-label">
                        硬さ: <span class="setting-value" id="watercolor-hardness-value">50</span>%
                    </label>
                    <input type="range" id="watercolor-hardness" class="setting-slider" min="0" max="100" value="50">
                </div>

                <div class="setting-row">
                    <label class="setting-label">
                        不透明度: <span class="setting-value" id="watercolor-opacity-value">30</span>%
                    </label>
                    <input type="range" id="watercolor-opacity" class="setting-slider" min="1" max="100" value="30">
                </div>
            </div>
            </div>
            <div class="panel-resize-handle"></div>
        </div>

        <!-- Layers Panel Section -->
        <div class="panel-section layers-panel" data-panel="layers">
            <div class="panel-header">
                <h3 class="panel-title">レイヤー</h3>
                <button class="panel-toggle" title="開閉">▼</button>
            </div>
            <div class="panel-content">
            <div class="layer-actions">
                <button class="layer-action-btn" id="btn-add-layer" title="新規レイヤー">➕</button>
            </div>
            <div id="layers-list">
                <!-- レイヤー一覧 (動的生成) -->
            </div>
            </div>
        </div>

    </div>

</div>

<!-- Status Bar -->
<div class="status-bar">
    <div class="status-text" id="status-text">準備完了</div>
    <div class="status-info">
        <span id="status-tool">ツール: ペン</span>
        <span id="status-layer">レイヤー: 3</span>
    </div>
</div>

<!-- Layer Context Menu -->
<div class="context-menu hidden" id="layer-context-menu">
    <div class="context-menu-item" data-action="duplicate">レイヤーを複製</div>
    <div class="context-menu-item" data-action="merge-down">下のレイヤーと結合</div>
    <div class="context-menu-item" data-action="clear">レイヤーをクリア</div>
    <div class="context-menu-item" data-action="delete">レイヤーを削除</div>
</div>

<!-- Open Illustration Modal -->
<div class="open-modal-overlay" id="open-modal-overlay">
    <div class="open-modal">
        <div class="open-modal-header">
            <h2 class="open-modal-title">イラストを開く</h2>
            <button class="timelapse-close" id="open-modal-close">×</button>
        </div>
        <div class="open-modal-content">
            <div id="illust-grid" class="illust-grid">
                <!-- Illustration list will be populated here -->
            </div>
            <div id="open-modal-empty" class="empty-state hidden">
                <div class="empty-state-icon">📁</div>
                <div class="empty-state-text">保存されたイラストがありません</div>
            </div>
        </div>
        <div class="open-modal-actions">
            <button class="modal-btn" id="open-modal-cancel">キャンセル</button>
            <button class="modal-btn primary" id="open-modal-load" disabled>開く</button>
        </div>
    </div>
</div>

<!-- Timelapse Modal Overlay -->
<div class="timelapse-overlay" id="timelapse-overlay">
    <div class="timelapse-modal">
        <div class="timelapse-header">
            <h2 class="timelapse-title">タイムラプス再生</h2>
            <button class="timelapse-close" id="timelapse-close">×</button>
        </div>

        <div class="timelapse-canvas-container">
            <div class="timelapse-canvas-wrap">
                <canvas id="timelapse-canvas" width="512" height="512"></canvas>
            </div>
        </div>

        <div class="timelapse-controls">
            <div class="timelapse-buttons">
                <button class="timelapse-btn" id="timelapse-restart" title="最初へ">⏮</button>
                <button class="timelapse-btn primary" id="timelapse-play" title="再生/停止">▶️</button>
                <button class="timelapse-btn" id="timelapse-stop" title="停止">⏹</button>
            </div>

            <div class="timelapse-seek-container">
                <input type="range" id="timelapse-seek" class="timelapse-seek" min="0" max="100" value="0">
                <div class="timelapse-time">
                    <span id="timelapse-current-time">0:00</span> / <span id="timelapse-total-time">0:00</span>
                </div>
            </div>

            <div class="timelapse-speed">
                <label class="timelapse-speed-label">
                    速度: <span id="timelapse-speed-value">1.0</span>x
                </label>
                <input type="range" id="timelapse-speed" class="timelapse-speed-slider" min="0.1" max="5" step="0.1" value="1">
            </div>
        </div>
    </div>
</div>

            <div class="timelapse-speed-control">
                <label class="timelapse-speed-label">再生速度:</label>
                <input type="range" id="timelapse-speed" class="timelapse-speed setting-slider" min="0.25" max="4" step="0.25" value="1">
                <span class="setting-value" id="timelapse-speed-value">1.0x</span>
            </div>

            <div class="timelapse-options">
                <div class="timelapse-option">
                    <input type="checkbox" id="timelapse-ignore-time">
                    <label for="timelapse-ignore-time">時間を無視（等間隔再生）</label>
                </div>
                <div class="timelapse-option">
                    <input type="checkbox" id="timelapse-real-time">
                    <label for="timelapse-real-time">リアル時間再生（中断時間を除外）</label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resize Canvas Modal -->
<div class="open-modal-overlay" id="resize-modal-overlay">
    <div class="open-modal">
        <div class="open-modal-header">
            <h2 class="open-modal-title">キャンバスサイズ変更</h2>
            <button class="timelapse-close" id="resize-modal-close">×</button>
        </div>
        <div class="open-modal-content" style="padding: 20px;">
            <div class="resize-options">
                <div class="setting-row">
                    <label class="setting-label">幅 (px):</label>
                    <input type="number" id="resize-width" class="resize-input" min="64" max="2048" value="512">
                </div>
                <div class="setting-row">
                    <label class="setting-label">高さ (px):</label>
                    <input type="number" id="resize-height" class="resize-input" min="64" max="2048" value="512">
                </div>
                <div class="setting-row">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="resize-keep-ratio" checked>
                        <label for="resize-keep-ratio">縦横比を維持</label>
                    </div>
                </div>
                <div class="resize-presets">
                    <h4 style="margin: 15px 0 10px; font-size: 0.9em; color: #999;">プリセット:</h4>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button class="preset-btn" data-width="512" data-height="512">512×512</button>
                        <button class="preset-btn" data-width="800" data-height="600">800×600</button>
                        <button class="preset-btn" data-width="1024" data-height="768">1024×768</button>
                        <button class="preset-btn" data-width="1280" data-height="720">1280×720</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="open-modal-actions">
            <button class="modal-btn" id="resize-modal-cancel">キャンセル</button>
            <button class="modal-btn primary" id="resize-modal-apply">適用</button>
        </div>
    </div>
</div>

<!-- Edit Color Modal -->
<div class="open-modal-overlay" id="edit-color-modal-overlay">
    <div class="open-modal" style="max-width: 400px;">
        <div class="open-modal-header">
            <h2 class="open-modal-title">パレット色の編集</h2>
            <button class="timelapse-close" id="edit-color-modal-close">×</button>
        </div>
        <div class="open-modal-content" style="padding: 20px;">
            <div class="edit-color-preview" style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
                <div style="width: 80px; height: 80px; border-radius: 8px; border: 2px solid #ddd;" id="edit-color-preview"></div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">カラーコード:</label>
                    <input type="text" id="edit-color-input" class="resize-input" placeholder="#000000" pattern="^#[0-9A-Fa-f]{6}$" maxlength="7">
                </div>
            </div>
            
            <!-- Tab buttons -->
            <div class="color-mode-tabs" style="display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 1px solid #ddd;">
                <button class="color-mode-tab active" data-mode="hsv">HSV</button>
                <button class="color-mode-tab" data-mode="rgb">RGB</button>
            </div>
            
            <!-- HSV Sliders -->
            <div id="hsv-sliders" class="color-sliders-group">
                <div class="rgb-slider-group">
                    <label class="rgb-label">
                        <span class="rgb-label-text" style="color: #ff6b6b;">H</span>
                        <input type="range" id="edit-hsv-h" class="rgb-slider" min="0" max="360" value="0">
                        <span class="rgb-value" id="edit-hsv-h-value">0°</span>
                    </label>
                </div>
                <div class="rgb-slider-group">
                    <label class="rgb-label">
                        <span class="rgb-label-text" style="color: #51cf66;">S</span>
                        <input type="range" id="edit-hsv-s" class="rgb-slider" min="0" max="100" value="0">
                        <span class="rgb-value" id="edit-hsv-s-value">0%</span>
                    </label>
                </div>
                <div class="rgb-slider-group">
                    <label class="rgb-label">
                        <span class="rgb-label-text" style="color: #4dabf7;">V</span>
                        <input type="range" id="edit-hsv-v" class="rgb-slider" min="0" max="100" value="0">
                        <span class="rgb-value" id="edit-hsv-v-value">0%</span>
                    </label>
                </div>
            </div>
            
            <!-- RGB Sliders -->
            <div id="rgb-sliders" class="color-sliders-group" style="display: none;">
                <div class="rgb-slider-group">
                    <label class="rgb-label">
                        <span class="rgb-label-text" style="color: #ff6b6b;">R</span>
                        <input type="range" id="edit-rgb-r" class="rgb-slider" min="0" max="255" value="0">
                        <span class="rgb-value" id="edit-rgb-r-value">0</span>
                    </label>
                </div>
                <div class="rgb-slider-group">
                    <label class="rgb-label">
                        <span class="rgb-label-text" style="color: #51cf66;">G</span>
                        <input type="range" id="edit-rgb-g" class="rgb-slider" min="0" max="255" value="0">
                        <span class="rgb-value" id="edit-rgb-g-value">0</span>
                    </label>
                </div>
                <div class="rgb-slider-group">
                    <label class="rgb-label">
                        <span class="rgb-label-text" style="color: #4dabf7;">B</span>
                        <input type="range" id="edit-rgb-b" class="rgb-slider" min="0" max="255" value="0">
                        <span class="rgb-value" id="edit-rgb-b-value">0</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="open-modal-actions">
            <button class="modal-btn" id="edit-color-modal-cancel">キャンセル</button>
            <button class="modal-btn primary" id="edit-color-modal-save">保存</button>
        </div>
    </div>
</div>

<!-- Save Modal -->
<div id="save-modal-overlay" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>メタ情報を編集</h3>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="save-title">タイトル</label>
                <input type="text" id="save-title" placeholder="イラストのタイトルを入力" maxlength="100">
            </div>
            <div class="form-group">
                <label for="save-description">説明 (オプション)</label>
                <textarea id="save-description" placeholder="イラストの説明を入力" rows="3" maxlength="500"></textarea>
            </div>
            <div class="form-group">
                <label for="save-tags">タグ (オプション)</label>
                <input type="text" id="save-tags" placeholder="タグをカンマ区切りで入力 (例: 風景, 人物, イラスト)" maxlength="200">
            </div>
            <div class="form-group">
                <label><input type="checkbox" id="save-nsfw"> NSFW（成人向け）</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" id="save-visible" checked> 公開（表示する）</label>
            </div>
            <div class="form-group" id="save-mode-group" style="display:none;">
                <label>保存方法</label>
                <div>
                    <label><input type="radio" name="save-mode" value="new" id="save-mode-new" checked> 新規として保存</label>
                </div>
                <div>
                    <label><input type="radio" name="save-mode" value="overwrite" id="save-mode-overwrite"> 上書き保存</label>
                </div>
            </div>
        </div>
        <div class="modal-actions">
            <button class="modal-btn" id="save-modal-cancel">キャンセル</button>
            <button class="modal-btn primary" id="save-modal-save">保存</button>
        </div>
    </div>
</div>

<script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrf, ENT_QUOTES, "UTF-8"); ?>';</script>
<!-- Base URL for admin/paint assets (used by JS to build absolute paths) -->
<script>
    // Ensure trailing slash
    window.PAINT_BASE_URL = '<?php echo rtrim(PathHelper::getAdminUrl('/paint/'), '/'); ?>' + '/';
</script>
<!-- Shim Worker constructor for timelapse worker so relative "js/..." paths resolve under PAINT_BASE_URL -->
<script>
    (function(){
        if (typeof window === 'undefined' || typeof window.Worker === 'undefined') return;
        try {
            const OrigWorker = window.Worker;
            window.Worker = function(scriptUrl) {
                try {
                    if (typeof scriptUrl === 'string' && scriptUrl.indexOf('timelapse_worker.js') !== -1 && window.PAINT_BASE_URL) {
                        const base = String(window.PAINT_BASE_URL).replace(/\/$/, '');
                        return new OrigWorker(base + '/js/timelapse_worker.js');
                    }
                } catch (e) {
                    // ignore and fall back
                }
                return new OrigWorker(scriptUrl);
            };
            // preserve prototype
            window.Worker.prototype = OrigWorker.prototype;
        } catch (e) {
            // ignore
        }
    })();
            // Fetch wrapper: prefix relative "api/..." requests with PAINT_BASE_URL so JS can use simple relative paths
            // Also: special-case timelapse API responses to normalize them into a gzipped binary payload
            (function(){
                try {
                    if (typeof window === 'undefined' || typeof window.fetch !== 'function') return;
                    const _origFetch = window.fetch.bind(window);

                    // helper to gzip a string (if pako available)
                    const gzipString = async (str) => {
                        try{
                            if (typeof pako !== 'undefined' && typeof pako.gzip === 'function'){
                                const arr = pako.gzip(str);
                                return new Uint8Array(arr);
                            }
                        }catch(e){/* fallthrough */}
                        // no pako: return utf-8 bytes (non-gz) as fallback
                        const enc = new TextEncoder();
                        return enc.encode(str);
                    };

                    window.fetch = async function(input, init){
                        try {
                            // Only rewrite string URLs (most code uses string literals like 'api/...')
                            if (typeof input === 'string'){
                                const s = input.trim();
                                // ignore absolute URLs (scheme) and root-absolute paths
                                if (!/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(s) && s.charAt(0) !== '/'){
                                    // match optional ./ or ../ then api/...
                                    const m = s.match(/^(?:\.\/|\.\.\/)?(api\/.*)$/);
                                    if (m && m[1]){
                                        const base = (typeof window.PAINT_BASE_URL === 'string' ? String(window.PAINT_BASE_URL).replace(/\/$/, '') : '');
                                        if (base) {
                                            input = base + '/' + m[1];
                                        }
                                    }
                                }
                            } else if (input && input.url && typeof input.url === 'string' && !/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(input.url) && input.url.charAt(0) !== '/'){
                                // If a Request object is used, attempt to rewrite by creating a new Request
                                const s = input.url.trim();
                                const m = s.match(/^(?:\.\/|\.\.\/)?(api\/.*)$/);
                                if (m && m[1]){
                                    const base = (typeof window.PAINT_BASE_URL === 'string' ? String(window.PAINT_BASE_URL).replace(/\/$/, '') : '');
                                    if (base) {
                                        input = new Request(base + '/' + m[1], input);
                                    }
                                }
                            }
                        } catch (e) {
                            // fall back to original input
                        }

                        // Special-case: normalize timelapse API to gzipped binary so existing bundle logic (which expects gz) will work.
                        try{
                            let url = (typeof input === 'string') ? input : (input && input.url ? input.url : null);
                            if (url && /\/?api\/timelapse\.php(?:\?|$)/.test(url)){
                                // Fetch original response
                                const orig = await _origFetch(input, init);
                                if (!orig.ok) return orig;

                                // If already an octet-stream (gz) just return as-is
                                let ct = '';
                                try{ ct = orig.headers && orig.headers.get ? (orig.headers.get('content-type')||'') : ''; }catch(e){ct='';}
                                if(ct.indexOf('application/octet-stream') !== -1 || ct.indexOf('application/gzip') !== -1){
                                    return orig;
                                }

                                // Try to parse JSON wrapper first
                                let parsed = null;
                                try{
                                    parsed = await orig.json();
                                }catch(e){
                                    // not JSON, fall back to text
                                }

                                if(parsed && typeof parsed === 'object'){
                                    // server wrapper: { format: 'csv', csv: '...' }
                                    if(parsed.format === 'csv' && typeof parsed.csv === 'string'){
                                        const gz = await gzipString(parsed.csv);
                                        const blob = new Blob([gz], { type: 'application/octet-stream' });
                                        return new Response(blob, { status: 200, statusText: 'OK', headers: { 'Content-Type': 'application/octet-stream' } });
                                    }
                                    // server wrapper: { format: 'json', timelapse: {...} }
                                    if(parsed.format === 'json' && parsed.timelapse){
                                        // Unguarded: create a JSON text of the timelapse object so bundle can JSON.parse after ungzip
                                        const txt = JSON.stringify(parsed.timelapse);
                                        const gz = await gzipString(txt);
                                        const blob = new Blob([gz], { type: 'application/octet-stream' });
                                        return new Response(blob, { status: 200, statusText: 'OK', headers: { 'Content-Type': 'application/octet-stream' } });
                                    }
                                }

                                // If we get here: not a wrapper JSON; try to read text and gzip that
                                try{
                                    const txt = await orig.text();
                                    const gz = await gzipString(txt);
                                    const blob = new Blob([gz], { type: 'application/octet-stream' });
                                    return new Response(blob, { status: 200, statusText: 'OK', headers: { 'Content-Type': 'application/octet-stream' } });
                                }catch(e){
                                    return orig; // fallback
                                }
                            }
                        }catch(e){
                            // ignore and continue to normal fetch
                        }

                        return _origFetch(input, init);
                    };
                } catch (e) {
                    // ignore
                }
            })();
        </script>
<!-- pako (gzip) for timelapse compression -->
<script src="https://cdn.jsdelivr.net/npm/pako@2.1.0/dist/pako.min.js"></script>
<?php echo \App\Utils\AssetHelper::scriptTag(PathHelper::getAdminUrl('/paint/js/paint.js')); ?>
</body>
</html>
