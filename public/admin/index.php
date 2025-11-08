<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Security/SecurityUtil.php';
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Security\CsrfProtection;
use App\Utils\PathHelper;

// セッション開始 & 認証チェック（共通化）
\App\Controllers\AdminControllerBase::ensureAuthenticated(true);
// (ensureAuthenticated がリダイレクトまたは継続する)

// CSRFトークンを生成
$csrfToken = CsrfProtection::generateToken();
$username = 'Admin';
try {
    if (class_exists('\App\\Services\\Session')) {
        $username = \App\Services\Session::getInstance()->get('admin_username', $username);
    } else {
        $username = $_SESSION['admin_username'] ?? $username;
    }
} catch (Throwable $e) {
    $username = $_SESSION['admin_username'] ?? $username;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理ダッシュボード - イラストポートフォリオ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <?php echo \App\Utils\AssetHelper::linkTag('/res/css/admin.css'); ?>
</head>
<body>
    <!-- ナビゲーションバー -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= PathHelper::getAdminUrl('index.php') ?>">
                <i class="bi bi-palette-fill me-2"></i>管理ダッシュボード
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/" target="_blank">
                    <i class="bi bi-eye me-1"></i>サイトを表示
                </a>
                <?php
                // ペイント機能へのリンク（機能が有効な場合のみ表示）
                try {
                    $paintEnabled = \App\Utils\FeatureGate::isEnabled('paint');
                } catch (Throwable $e) {
                    $paintEnabled = true;
                }
                if (!empty($paintEnabled)): ?>
                    <a class="nav-link" href="<?= PathHelper::getAdminUrl('paint/index.php') ?>" target="_blank">
                        <i class="bi bi-brush me-1"></i>ペイント
                    </a>
                <?php endif; ?>
                <span class="nav-link">
                    <i class="bi bi-person-circle me-1"></i><?= escapeHtml($username) ?>
                </span>
                <form method="POST" action="<?= PathHelper::getAdminUrl('logout.php') ?>" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                    <button type="submit" class="btn btn-link nav-link text-light" style="text-decoration: none;">
                        <i class="bi bi-box-arrow-right me-1"></i>ログアウト
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- タブナビゲーション -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="posts-tab" data-bs-toggle="tab" data-bs-target="#posts" type="button" role="tab" aria-controls="posts" aria-selected="true">
                    <i class="bi bi-image me-2"></i>投稿（シングル）
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="group-posts-tab" data-bs-toggle="tab" data-bs-target="#group-posts" type="button" role="tab" aria-controls="group-posts" aria-selected="false">
                    <i class="bi bi-images me-2"></i>投稿（グループ）
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="theme-tab" data-bs-toggle="tab" data-bs-target="#theme" type="button" role="tab" aria-controls="theme" aria-selected="false">
                    <i class="bi bi-palette me-2"></i>テーマ設定
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false">
                    <i class="bi bi-gear me-2"></i>サイト設定
                </button>
            </li>
        </ul>

        <!-- タブコンテンツ -->
        <div class="tab-content" id="adminTabsContent">
            <!-- 投稿管理タブ -->
            <div class="tab-pane fade show active" id="posts" role="tabpanel" aria-labelledby="posts-tab">
                <div class="row">
                    <!-- 画像アップロードフォーム -->
                    <div class="col-lg-5">
                        <!-- クリップボードからアップロード -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-clipboard-check me-2"></i>クリップボードから投稿
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="toggleClipboardUpload">
                                    <i class="bi bi-chevron-down" id="clipboardToggleIcon"></i>
                                </button>
                            </div>
                            <div class="card-body" id="clipboardUploadSection" style="display: none;">
                                <div id="clipboardAlert" class="alert alert-success d-none" role="alert"></div>
                                <div id="clipboardError" class="alert alert-danger d-none" role="alert"></div>

                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>使い方:</strong> 下のエリアをクリックして <kbd>Ctrl+V</kbd> (Mac: <kbd>⌘+V</kbd>) で画像を貼り付けてください
                                </div>

                                <form id="clipboardUploadForm">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                                    <!-- ペーストエリア -->
                                    <div class="mb-3">
                                        <label class="form-label">画像を貼り付け</label>
                                        <div id="clipboardPasteArea" class="clipboard-paste-area" tabindex="0">
                                            <div id="clipboardPasteHint" class="text-center text-muted">
                                                <i class="bi bi-clipboard2-plus" style="font-size: 3rem;"></i>
                                                <p class="mt-2">クリックしてフォーカスし、Ctrl+V で画像を貼り付け</p>
                                            </div>
                                            <div id="clipboardPreview" style="display: none; position: relative;">
                                                <img id="clipboardPreviewImg" alt="プレビュー" style="max-width: 100%; border-radius: 4px;">
                                                <button type="button" class="btn btn-sm btn-danger" id="clearClipboardImage"
                                                        style="position: absolute; top: 10px; right: 10px;">
                                                    <i class="bi bi-x-circle"></i> クリア
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="clipboardTitle" class="form-label">タイトル <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="clipboardTitle" name="title" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="clipboardTags" class="form-label">タグ（カンマ区切り）</label>
                                        <input type="text" class="form-control" id="clipboardTags" name="tags" placeholder="例: R18, ファンタジー, ドラゴン">
                                    </div>

                                    <div class="mb-3">
                                        <label for="clipboardDetail" class="form-label">詳細説明</label>
                                        <textarea class="form-control" id="clipboardDetail" name="detail" rows="3"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="clipboardIsSensitive" name="is_sensitive" value="1">
                                            <label class="form-check-label" for="clipboardIsSensitive">
                                                センシティブコンテンツ（18禁）
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="clipboardIsVisible" name="is_visible" value="1" checked>
                                            <label class="form-check-label" for="clipboardIsVisible">
                                                <strong>公開ページに表示する</strong>
                                            </label>
                                            <div class="form-text">オフにすると、この投稿は管理画面でのみ表示されます</div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="clipboardFormat" class="form-label">保存形式</label>
                                        <select class="form-select" id="clipboardFormat" name="format">
                                            <option value="webp" selected>WebP（推奨・軽量）</option>
                                            <option value="jpg">JPEG</option>
                                            <option value="png">PNG</option>
                                        </select>
                                        <div class="form-text">WebPは高品質かつファイルサイズが小さいため推奨です</div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-grow-1" id="clipboardUploadBtn" disabled>
                                            <i class="bi bi-upload me-2"></i>アップロード
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="clipboardCancelBtn">
                                            <i class="bi bi-x-circle me-2"></i>キャンセル
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-cloud-upload me-2"></i>新規投稿
                            </div>
                            <div class="card-body">
                                <div id="uploadAlert" class="alert alert-success d-none" role="alert"></div>
                                <div id="uploadError" class="alert alert-danger d-none" role="alert"></div>

                                <form id="uploadForm">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                                    <div class="mb-3">
                                        <label for="title" class="form-label">タイトル <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="tags" class="form-label">タグ（カンマ区切り）</label>
                                        <input type="text" class="form-control" id="tags" name="tags" placeholder="例: R18, ファンタジー, ドラゴン">
                                    </div>

                                    <div class="mb-3">
                                        <label for="detail" class="form-label">詳細説明</label>
                                        <textarea class="form-control" id="detail" name="detail" rows="3"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="isSensitive" name="is_sensitive" value="1">
                                            <label class="form-check-label" for="isSensitive">
                                                センシティブコンテンツ（18禁）
                                            </label>
                                            <div class="form-text">18歳未満の閲覧に適さないコンテンツの場合はチェックしてください</div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="isVisible" name="is_visible" value="1" checked>
                                            <label class="form-check-label" for="isVisible">
                                                <strong>公開ページに表示する</strong>
                                            </label>
                                            <div class="form-text">オフにすると、この投稿は管理画面でのみ表示されます</div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="image" class="form-label">画像ファイル <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/webp" required>
                                        <div class="form-text">JPEG, PNG, WebP形式（最大10MB）</div>
                                        <img id="imagePreview" alt="プレビュー">
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-upload me-2"></i>アップロード
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- 一括アップロード -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-file-earmark-image me-2"></i>一括アップロード
                            </div>
                            <div class="card-body">
                                <div id="bulkUploadAlert" class="alert alert-success d-none" role="alert"></div>
                                <div id="bulkUploadError" class="alert alert-danger d-none" role="alert"></div>

                                <form id="bulkUploadForm">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                                    <div class="mb-3">
                                        <label for="bulkImages" class="form-label">画像を選択 (複数可)</label>
                                        <input type="file" class="form-control" id="bulkImages" name="images[]" accept="image/*" multiple required>
                                        <div class="form-text">
                                            一括でアップロードした画像は<strong>すべて非表示状態</strong>で登録されます。<br>
                                            タイトルやタグは後から編集画面で設定してください。
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div id="bulkPreviewList" class="row g-2"></div>
                                    </div>

                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-cloud-upload me-2"></i>一括アップロード
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 投稿一覧 -->
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-images me-2"></i>投稿一覧
                                </div>
                                <div class="d-flex align-items-center gap-2" id="bulkActionButtons" style="display: none;">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">
                                        <i class="bi bi-check-square me-1"></i>全選択
                                    </button>
                                    <span class="badge bg-secondary" id="selectionCount" style="display: none;">0件選択中</span>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-success" id="bulkPublishBtn" disabled>
                                            <i class="bi bi-eye me-1"></i>一括公開
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" id="bulkUnpublishBtn" disabled>
                                            <i class="bi bi-eye-slash me-1"></i>一括非公開
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div id="postsList">
                                    <div class="text-center p-4 text-muted">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">読み込み中...</span>
                                        </div>
                                        <p class="mt-2">投稿を読み込み中...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- グループ投稿管理タブ -->
            <div class="tab-pane fade" id="group-posts" role="tabpanel" aria-labelledby="group-posts-tab">
                <div class="row">
                    <!-- グループアップロードフォーム -->
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-images me-2"></i>グループ投稿を作成
                            </div>
                            <div class="card-body">
                                <div id="groupUploadAlert" class="alert alert-success d-none" role="alert"></div>
                                <div id="groupUploadError" class="alert alert-danger d-none" role="alert"></div>

                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>グループ投稿とは？</strong><br>
                                    複数の画像を1つの投稿として管理します。漫画や連作イラストに最適です。
                                </div>

                                <form id="groupUploadForm">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                                    <div class="mb-3">
                                        <label for="groupImages" class="form-label">画像を選択 (複数枚) <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" id="groupImages" name="images[]" accept="image/*" multiple required>
                                        <div class="form-text">選択した順番で画像が表示されます</div>
                                    </div>

                                    <div class="mb-3">
                                        <div id="groupPreviewList" class="row g-2"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="groupPostTitle" class="form-label">タイトル <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="groupPostTitle" name="title" required placeholder="例: 漫画タイトル 第1話">
                                    </div>

                                    <div class="mb-3">
                                        <label for="groupPostTags" class="form-label">タグ（カンマ区切り）</label>
                                        <input type="text" class="form-control" id="groupPostTags" name="tags" placeholder="例: 漫画, オリジナル">
                                    </div>

                                    <div class="mb-3">
                                        <label for="groupPostDetail" class="form-label">詳細説明</label>
                                        <textarea class="form-control" id="groupPostDetail" name="detail" rows="3"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="groupPostIsSensitive" name="is_sensitive" value="1">
                                            <label class="form-check-label" for="groupPostIsSensitive">
                                                センシティブコンテンツ（18禁）
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="groupPostIsVisible" name="is_visible" value="1" checked>
                                            <label class="form-check-label" for="groupPostIsVisible">
                                                <strong>公開ページに表示する</strong>
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-cloud-upload me-2"></i>グループ投稿を作成
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- グループ投稿一覧 -->
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-folder me-2"></i>グループ投稿一覧
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadGroupPosts()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>再読み込み
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="groupPostsList">
                                    <div class="text-center py-5">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">読み込み中...</span>
                                        </div>
                                        <p class="mt-2">グループ投稿を読み込み中...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- テーマ設定タブ -->
            <div class="tab-pane fade" id="theme" role="tabpanel" aria-labelledby="theme-tab">
                <div class="row">
                    <!-- 左側：設定フォーム -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-palette-fill me-2"></i>テーマ設定
                            </div>
                            <div class="card-body">
                                <div id="themeAlert" class="alert alert-success d-none" role="alert"></div>
                                <div id="themeError" class="alert alert-danger d-none" role="alert"></div>

                                <form id="themeForm">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                                    <!-- アコーディオン形式のテーマ設定 -->
                                    <div class="accordion" id="themeAccordion">

                                        <!-- ========== ヘッダー設定 ========== -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingHeader">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHeader" aria-expanded="true" aria-controls="collapseHeader">
                                                    <i class="bi bi-layout-text-window me-2"></i>ヘッダー設定
                                                </button>
                                            </h2>
                                            <div id="collapseHeader" class="accordion-collapse collapse show" aria-labelledby="headingHeader" data-bs-parent="#themeAccordion">
                                                <div class="accordion-body">

                                    <!-- サイト基本情報 -->
                                    <div class="mb-3">
                                        <label for="siteTitle" class="form-label">サイトタイトル</label>
                                        <input type="text" class="form-control" id="siteTitle" name="site_title" placeholder="例: イラストポートフォリオ">
                                        <div class="form-text">サイトのメインタイトル（ヘッダーに表示）</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="siteSubtitle" class="form-label">サブタイトル</label>
                                        <input type="text" class="form-control" id="siteSubtitle" name="site_subtitle" placeholder="例: Illustration Portfolio">
                                        <div class="form-text">サイトのサブタイトル（英語表記など）</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="siteDescription" class="form-label">サイト説明</label>
                                        <textarea class="form-control" id="siteDescription" name="site_description" rows="2" placeholder="例: イラストレーターのポートフォリオサイト"></textarea>
                                        <div class="form-text">SEO用のサイト説明文</div>
                                    </div>

                                    <!-- ヘッダー画像 -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">ロゴ画像</label>
                                            <div id="logoImagePreview" class="mb-2">
                                                <img src="" alt="ロゴプレビュー" style="max-width: 150px; display: none;" id="logoPreviewImg">
                                            </div>
                                            <input type="file" class="form-control form-control-sm" id="logoImage" accept="image/*">
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-primary" id="uploadLogo">
                                                    <i class="bi bi-upload me-1"></i>アップロード
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" id="deleteLogo" style="display: none;">
                                                    <i class="bi bi-trash me-1"></i>削除
                                                </button>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">背景画像</label>
                                            <div id="headerImagePreview" class="mb-2">
                                                <img src="" alt="ヘッダー背景プレビュー" style="max-width: 150px; display: none;" id="headerPreviewImg">
                                            </div>
                                            <input type="file" class="form-control form-control-sm" id="headerImage" accept="image/*">
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-primary" id="uploadHeader">
                                                    <i class="bi bi-upload me-1"></i>アップロード
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" id="deleteHeader" style="display: none;">
                                                    <i class="bi bi-trash me-1"></i>削除
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ヘッダー色 -->
                                    <div class="color-grid mb-3">
                                        <div class="color-item">
                                            <label for="primaryColor" class="form-label small mb-1">プライマリ色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="primaryColor" name="primary_color" value="#8B5AFA">
                                        </div>
                                        <div class="color-item">
                                            <label for="secondaryColor" class="form-label small mb-1">セカンダリ色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="secondaryColor" name="secondary_color" value="#667eea">
                                        </div>
                                        <div class="color-item">
                                            <label for="headingColor" class="form-label small mb-1">見出し色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="headingColor" name="heading_color" value="#ffffff">
                                        </div>
                                    </div>

                                    <!-- カスタムHTML（上級者向け） -->
                                    <div class="mb-4">
                                        <label for="headerText" class="form-label small text-muted">
                                            <i class="bi bi-code-slash me-1"></i>カスタムHTML（上級者向け）
                                        </label>
                                        <input type="text" class="form-control form-control-sm" id="headerText" name="header_html" placeholder="空欄の場合はサイトタイトルを表示">
                                        <div class="form-text">空欄の場合は上記のサイトタイトルが自動表示されます</div>
                                    </div>

                                                </div>
                                            </div>
                                        </div>

                                        <!-- ========== コンテンツ設定 ========== -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingContent">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseContent" aria-expanded="false" aria-controls="collapseContent">
                                                    <i class="bi bi-file-earmark-text me-2"></i>コンテンツ設定
                                                </button>
                                            </h2>
                                            <div id="collapseContent" class="accordion-collapse collapse" aria-labelledby="headingContent" data-bs-parent="#themeAccordion">
                                                <div class="accordion-body">

                                    <!-- 背景・テキスト色 -->
                                    <div class="color-grid mb-3">
                                        <div class="color-item">
                                            <label for="backgroundColor" class="form-label small mb-1">背景色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="backgroundColor" name="background_color" value="#1a1a1a">
                                        </div>
                                        <div class="color-item">
                                            <label for="textColor" class="form-label small mb-1">本文色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="textColor" name="text_color" value="#ffffff">
                                        </div>
                                        <div class="color-item">
                                            <label for="accentColor" class="form-label small mb-1">アクセント色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="accentColor" name="accent_color" value="#FFD700">
                                        </div>
                                    </div>

                                    <!-- リンク色 -->
                                    <div class="color-grid mb-3">
                                        <div class="color-item">
                                            <label for="linkColor" class="form-label small mb-1">リンク色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="linkColor" name="link_color" value="#8B5AFA">
                                        </div>
                                        <div class="color-item">
                                            <label for="linkHoverColor" class="form-label small mb-1">リンクホバー色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="linkHoverColor" name="link_hover_color" value="#a177ff">
                                        </div>
                                    </div>

                                    <!-- タグ色 -->
                                    <h6 class="mt-3 mb-2 small text-muted">タグ設定</h6>
                                    <div class="color-grid mb-3">
                                        <div class="color-item">
                                            <label for="tagBgColor" class="form-label small mb-1">タグ背景色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="tagBgColor" name="tag_bg_color" value="#8B5AFA">
                                        </div>
                                        <div class="color-item">
                                            <label for="tagTextColor" class="form-label small mb-1">タグ文字色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="tagTextColor" name="tag_text_color" value="#ffffff">
                                        </div>
                                        <div class="color-item col-span-2">
                                            <label class="form-label small mb-1">プレビュー</label>
                                            <div style="padding: 8px;">
                                                <span id="tagColorPreview" class="badge" style="background-color: #8B5AFA; color: #ffffff;">サンプルタグ</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- フィルタ設定 -->
                                    <h6 class="mt-3 mb-2 small text-muted">フィルタ設定（選択時の色）</h6>
                                    <div class="color-grid mb-3">
                                        <div class="color-item">
                                            <label for="filterActiveBgColor" class="form-label small mb-1">フィルタ選択時背景色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="filterActiveBgColor" name="filter_active_bg_color" value="#8B5AFA">
                                        </div>
                                        <div class="color-item">
                                            <label for="filterActiveTextColor" class="form-label small mb-1">フィルタ選択時文字色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="filterActiveTextColor" name="filter_active_text_color" value="#ffffff">
                                        </div>
                                        <div class="color-item col-span-2">
                                            <label class="form-label small mb-1">プレビュー</label>
                                            <div style="padding: 8px;">
                                                <span id="filterActiveColorPreview" class="badge" style="background-color: #8B5AFA; color: #ffffff;">選択中フィルタ</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- カード設定 -->
                                    <h6 class="mt-3 mb-2 small text-muted">カード設定</h6>
                                    <div class="color-grid mb-3">
                                        <div class="color-item">
                                            <label for="cardBgColor" class="form-label small mb-1">カード背景色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="cardBgColor" name="card_bg_color" value="#252525">
                                        </div>
                                        <div class="color-item">
                                            <label for="cardBorderColor" class="form-label small mb-1">カード枠線色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="cardBorderColor" name="card_border_color" value="#333333">
                                        </div>
                                        <div class="color-item col-span-2">
                                            <label for="cardShadowOpacity" class="form-label small mb-1">カード影の濃さ</label>
                                            <input type="range" class="form-range" id="cardShadowOpacity" name="card_shadow_opacity" min="0" max="1" step="0.1" value="0.3">
                                            <div class="form-text small">現在: <span id="shadowValue">0.3</span></div>
                                        </div>
                                    </div>

                                                </div>
                                            </div>
                                        </div>

                                        <!-- ========== ナビゲーション設定 ========== -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingThemeNavigation">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThemeNavigation" aria-expanded="false" aria-controls="collapseThemeNavigation">
                                                    <i class="bi bi-arrow-left-circle me-2"></i>ナビゲーション設定
                                                </button>
                                            </h2>
                                            <div id="collapseThemeNavigation" class="accordion-collapse collapse" aria-labelledby="headingThemeNavigation" data-bs-parent="#themeAccordion">
                                                <div class="accordion-body">
                                                    <p class="text-muted small mb-3">
                                                        詳細ページの「一覧に戻る」ボタンのデザインをカスタマイズできます
                                                    </p>

                                                    <div class="mb-3">
                                                        <label for="backButtonText" class="form-label">ボタンテキスト</label>
                                                        <input type="text" class="form-control" id="backButtonText" name="back_button_text" placeholder="一覧に戻る" maxlength="20">
                                                        <div class="form-text">ボタンに表示するテキスト（20文字以内）</div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="backButtonBgColor" class="form-label">背景色</label>
                                                            <input type="color" class="form-control form-control-color" id="backButtonBgColor" name="back_button_bg_color" value="#8B5AFA">
                                                            <div class="form-text">ボタンの背景色</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="backButtonTextColor" class="form-label">テキスト色</label>
                                                            <input type="color" class="form-control form-control-color" id="backButtonTextColor" name="back_button_text_color" value="#FFFFFF">
                                                            <div class="form-text">ボタンのテキスト色</div>
                                                        </div>
                                                    </div>

                                                    <!-- プレビュー -->
                                                    <div class="mt-3 p-3 bg-light rounded">
                                                        <label class="form-label small text-muted">プレビュー:</label>
                                                        <div id="backButtonPreview" class="header-back-button" style="display: inline-block; background-color: #8B5AFA; color: #FFFFFF; padding: 10px 20px; border-radius: 8px; cursor: pointer;">
                                                            一覧に戻る
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ========== フッター設定 ========== -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingFooter">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFooter" aria-expanded="false" aria-controls="collapseFooter">
                                                    <i class="bi bi-layout-text-window-reverse me-2"></i>フッター設定
                                                </button>
                                            </h2>
                                            <div id="collapseFooter" class="accordion-collapse collapse" aria-labelledby="headingFooter" data-bs-parent="#themeAccordion">
                                                <div class="accordion-body">

                                    <!-- フッター色 -->
                                    <div class="color-grid mb-3">
                                        <div class="color-item">
                                            <label for="footerBgColor" class="form-label small mb-1">背景色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="footerBgColor" name="footer_bg_color" value="#2a2a2a">
                                        </div>
                                        <div class="color-item">
                                            <label for="footerTextColor" class="form-label small mb-1">文字色</label>
                                            <input type="color" class="form-control form-control-color w-100" id="footerTextColor" name="footer_text_color" value="#cccccc">
                                        </div>
                                    </div>

                                    <!-- フッターHTML -->
                                    <div class="mb-4">
                                        <label for="footerText" class="form-label">フッターテキスト</label>
                                        <textarea class="form-control" id="footerText" name="footer_html" rows="3" placeholder="例: © 2025 Portfolio Site. All rights reserved."></textarea>
                                        <div class="form-text">フッターに表示されるテキスト（HTMLタグも使用可）</div>
                                    </div>

                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-2"></i>すべて保存
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 右側：リアルタイムプレビュー -->
                    <div class="col-lg-6">
                        <div class="card sticky-preview">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-display me-2"></i>リアルタイムプレビュー
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary active" data-preview-size="100%" title="デスクトップ">
                                        <i class="bi bi-laptop"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" data-preview-size="768px" title="タブレット">
                                        <i class="bi bi-tablet"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" data-preview-size="375px" title="モバイル">
                                        <i class="bi bi-phone"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0" style="background: #f5f5f5;">
                                <div id="previewContainer" style="display: flex; justify-content: center; padding: 20px; min-height: 600px;">
                                    <div id="previewFrame" style="width: 100%; max-width: 100%; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background: white;">
                                        <iframe
                                            id="sitePreview"
                                            src="/"
                                            style="width: 100%; height: 600px; border: none; border-radius: 4px;"
                                            title="サイトプレビュー"
                                        ></iframe>
                                    </div>
                                </div>
                                <div class="card-footer text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    色やテキストを変更すると、リアルタイムでプレビューに反映されます
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- サイト設定タブ -->
            <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-gear-fill me-2"></i>サイト設定
                            </div>
                            <div class="card-body">
                                <div id="settingsAlert" class="alert d-none" role="alert"></div>

                                <form id="settingsForm">
                                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                                    <!-- アコーディオン形式の設定 -->
                                    <div class="accordion" id="settingsAccordion">

                                        <!-- コンテンツ表示設定 -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingDisplay">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDisplay" aria-expanded="true" aria-controls="collapseDisplay">
                                                    <i class="bi bi-eye me-2"></i>コンテンツ表示設定
                                                </button>
                                            </h2>
                                            <div id="collapseDisplay" class="accordion-collapse collapse show" aria-labelledby="headingDisplay" data-bs-parent="#settingsAccordion">
                                                <div class="accordion-body">
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="showViewCount" checked>
                                                        <label class="form-check-label" for="showViewCount">
                                                            <strong>閲覧回数を表示する</strong>
                                                        </label>
                                                        <div class="form-text mt-2">
                                                            オフにすると、すべての投稿で閲覧回数が非表示になります
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- OGP/SNSシェア設定 -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingOGP">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOGP" aria-expanded="false" aria-controls="collapseOGP">
                                                    <i class="bi bi-share me-2"></i>OGP/SNSシェア設定
                                                </button>
                                            </h2>
                                            <div id="collapseOGP" class="accordion-collapse collapse" aria-labelledby="headingOGP" data-bs-parent="#settingsAccordion">
                                                <div class="accordion-body">
                                                    <p class="text-muted small mb-3">
                                                        TwitterやFacebookなどのSNSでシェアされた際に表示される情報を設定します
                                                    </p>

                                                    <div class="mb-3">
                                                        <label for="ogpTitle" class="form-label">OGPタイトル</label>
                                                        <input type="text" class="form-control" id="ogpTitle" name="ogp_title" placeholder="空欄の場合はサイトタイトルを使用">
                                                        <div class="form-text">SNSでシェアされた際のタイトル（60文字以内推奨）</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="ogpDescription" class="form-label">OGP説明文</label>
                                                        <textarea class="form-control" id="ogpDescription" name="ogp_description" rows="3" placeholder="空欄の場合はサイト説明を使用"></textarea>
                                                        <div class="form-text">SNSでシェアされた際の説明文（120文字以内推奨）</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">OGP画像</label>
                                                        <div id="ogpImagePreview" class="mb-2">
                                                            <img src="" alt="OGP画像プレビュー" style="max-width: 300px; display: none;" id="ogpImagePreviewImg">
                                                        </div>
                                                        <input type="file" class="form-control" id="ogpImageFile" accept="image/*">
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-primary" id="uploadOgpImage">
                                                                <i class="bi bi-upload me-1"></i>アップロード
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" id="deleteOgpImage" style="display: none;">
                                                                <i class="bi bi-trash me-1"></i>削除
                                                            </button>
                                                        </div>
                                                        <div class="form-text">推奨サイズ: 1200x630px（横長）。Twitterでは2:1の比率が推奨されます</div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="twitterCard" class="form-label">Twitter Cardタイプ</label>
                                                            <select class="form-select" id="twitterCard" name="twitter_card">
                                                                <option value="summary">summary（正方形）</option>
                                                                <option value="summary_large_image" selected>summary_large_image（大きな画像）</option>
                                                            </select>
                                                            <div class="form-text">Twitterでの表示タイプ</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="twitterSite" class="form-label">Twitterアカウント</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">@</span>
                                                                <input type="text" class="form-control" id="twitterSite" name="twitter_site" placeholder="username">
                                                            </div>
                                                            <div class="form-text">@なしで入力</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-2"></i>すべて保存
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 編集モーダル -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center w-100">
                        <button type="button" class="btn btn-outline-secondary btn-sm me-3" id="prevPostBtn" title="前の投稿">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <h5 class="modal-title mb-0 flex-grow-1" id="editModalLabel">
                            <i class="bi bi-pencil-square me-2"></i>投稿を編集
                        </h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-3" id="nextPostBtn" title="次の投稿">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div id="editAlert" class="alert alert-success d-none" role="alert"></div>
                    <div id="editError" class="alert alert-danger d-none" role="alert"></div>

                    <div class="row">
                        <!-- 左側：編集フォーム -->
                        <div class="col-md-6">
                            <form id="editForm">
                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                <input type="hidden" id="editPostId" name="id">

                                <div class="mb-3">
                                    <label for="editTitle" class="form-label">タイトル <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editTitle" name="title" required>
                                </div>

                                <div class="mb-3">
                                    <label for="editTags" class="form-label">タグ（カンマ区切り）</label>
                                    <input type="text" class="form-control" id="editTags" name="tags" placeholder="例: R18, ファンタジー, ドラゴン">
                                </div>

                                <div class="mb-3">
                                    <label for="editDetail" class="form-label">詳細説明</label>
                                    <textarea class="form-control" id="editDetail" name="detail" rows="4"></textarea>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="editIsSensitive" name="is_sensitive" value="1">
                                        <label class="form-check-label" for="editIsSensitive">
                                            センシティブコンテンツ（18禁）
                                        </label>
                                        <div class="form-text">18歳未満の閲覧に適さないコンテンツの場合はチェックしてください</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="editSortOrder" class="form-label">表示順序</label>
                                    <input type="number" class="form-control" id="editSortOrder" name="sort_order" value="0">
                                    <div class="form-text">
                                        0: 通常（作成日時順）<br>
                                        プラス値: 優先度アップ（前方に表示）<br>
                                        マイナス値: 優先度ダウン（後方に表示）
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="editIsVisible" name="is_visible" value="1" checked>
                                        <label class="form-check-label" for="editIsVisible">
                                            <strong>公開ページに表示する</strong>
                                        </label>
                                        <div class="form-text">オフにすると、この投稿は管理画面でのみ表示されます</div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- 右側：画像プレビュー -->
                        <div class="col-md-6">
                            <div class="sticky-top" style="top: 20px;">
                                <label class="form-label">画像</label>
                                <div class="edit-image-preview-container mb-3">
                                    <img id="editImagePreview" alt="画像プレビュー" class="img-fluid rounded">
                                </div>

                                <!-- 画像差し替え -->
                                <div class="mb-3">
                                    <label for="editImageFile" class="form-label">
                                        <i class="bi bi-image me-1"></i>画像を差し替え（任意）
                                    </label>
                                    <input type="file" class="form-control" id="editImageFile" accept="image/*">
                                    <div class="form-text">
                                        画像を選択すると、現在の画像が置き換えられます。<br>
                                        選択しない場合は画像は変更されません。
                                    </div>
                                </div>

                                <!-- 差し替え画像のプレビュー -->
                                <div id="editImageReplacePreview" style="display: none;">
                                    <label class="form-label text-primary">新しい画像プレビュー</label>
                                    <div class="edit-image-preview-container mb-2">
                                        <img id="editImageReplacePreviewImg" alt="新しい画像プレビュー" class="img-fluid rounded border border-primary">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>キャンセル
                    </button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">
                        <i class="bi bi-check-circle me-1"></i>保存
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        // 管理画面パスをJavaScriptで使用可能にする
        const ADMIN_PATH = '<?= PathHelper::getAdminPath() ?>';
    </script>
    <?php echo \App\Utils\AssetHelper::scriptTag(PathHelper::getAdminUrl('js/admin.js')); ?>
    <?php echo \App\Utils\AssetHelper::scriptTag(PathHelper::getAdminUrl('js/sns-share.js')); ?>
</body>
</html>
