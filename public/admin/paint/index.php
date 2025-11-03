<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Security\CsrfProtection;

// 管理画面用の簡易キャンバス UI（テスト/開発用）
initSecureSession();

// Note: 通常は管理者の認証フローを通すこと。
// テスト環境では /test/session_setup.php を使ってセッションを準備できます。
$csrf = CsrfProtection::getToken();
?><!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Paint - 管理</title>
    <link rel="stylesheet" href="/admin/paint/css/style.css">
</head>
<body>
<div class="toolbar">
    <label>色: <input id="color" type="color" value="#000000"></label>
    <label>サイズ: <input id="size" type="range" min="1" max="50" value="4"></label>
    <button id="eraser">消しゴム</button>
    <button id="undo">Undo</button>
    <button id="redo">Redo</button>
    <button id="save">保存</button>
    <span id="status"></span>
</div>

<div id="canvas-wrap">
    <!-- 4 layers -->
    <canvas class="layer" data-layer="0" width="512" height="512"></canvas>
    <canvas class="layer" data-layer="1" width="512" height="512"></canvas>
    <canvas class="layer" data-layer="2" width="512" height="512"></canvas>
    <canvas class="layer" data-layer="3" width="512" height="512"></canvas>
</div>

<script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrf, ENT_QUOTES, "UTF-8"); ?>';</script>
<script src="/admin/paint/js/paint.js"></script>
</body>
</html>
