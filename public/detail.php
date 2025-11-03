<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Security/SecurityUtil.php';

use App\Models\Post;
use App\Models\GroupPostImage;
use App\Models\Theme;
use App\Models\Setting;

// パラメータの検証
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /index.php');
    exit;
}
if (!isset($_GET['viewtype']) || !is_numeric($_GET['viewtype'])) {
    header('Location: /index.php');
    exit;
}
$id = (int)$_GET['id'];
$type = (int)$_GET['viewtype'];
if (!(0 <= $type && $type <= 1)) {
    header('Location: /index.php');
    exit;
}
$isGroupPost = ($type === 1);

try {
    // テーマ設定を取得
    $themeModel = new Theme();
    $theme = $themeModel->getCurrent();

    // サイト設定を取得
    $settingModel = new Setting();
    $showViewCount = $settingModel->get('show_view_count', '1') === '1';

    // 設定を読み込み
    $config = require __DIR__ . '/../config/config.php';
    $nsfwConfig = $config['nsfw'];
    $ageVerificationMinutes = $nsfwConfig['age_verification_minutes'];
    $nsfwConfigVersion = $nsfwConfig['config_version'];

    // 投稿を取得（統一されたPostモデルを使用）
    $model = new Post();
    $data = $model->getById($id);

    if ($data === null) {
        header('Location: /index.php');
        exit;
    }

    // post_typeが一致するか確認
    if ($data['post_type'] != $type) {
        header('Location: /index.php');
        exit;
    }

    // グループ投稿の場合は画像を取得
    if ($isGroupPost) {
        $groupPostImageModel = new GroupPostImage();
        $data['images'] = $groupPostImageModel->getImagesByPostId($id);
    }

    // 閲覧数をインクリメント
    $model->incrementViewCount($id);

} catch (Exception $e) {
    error_log('Post Detail Error (' . $type . '): ' . $e->getMessage());
    header('Location: /index.php');
    exit;
}

/**
 * NSFWフィルター版の画像パスを生成
 * @param string $imagePath 元の画像パス
 * @return string NSFWフィルター版の画像パス
 */
function getNsfwImagePath($imagePath) {
    $pathInfo = pathinfo($imagePath);
    // basename()でディレクトリトラバーサルを防止
    $nsfwFilename = basename($pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp'));
    return $pathInfo['dirname'] . '/' . $nsfwFilename;
}

/**
 * 投稿データからNSFWサムネイルパスを取得（フォールバック付き）
 * @param array $post 投稿データ
 * @return string NSFWサムネイルパス
 */
function createNsfwThumb($post) {
    // NSFW画像の場合はNSFWフィルター版を使用
    $imagePath = $post['image_path'] ?? $post['thumb_path'] ?? '';
    if (empty($imagePath)) {
        return '';
    }
    
    $shareImagePath = getNsfwImagePath($imagePath);

    // パスの検証（uploadsディレクトリ内であることを確認）
    $fullPath = realpath(__DIR__ . '/' . $shareImagePath);
    $uploadsDir = realpath(__DIR__ . '/uploads/');

    // NSFWフィルター版が存在しない、または不正なパスの場合はサムネイルのNSFWフィルター版を使用
    if (!$fullPath || !$uploadsDir || strpos($fullPath, $uploadsDir) !== 0 || !file_exists($fullPath)) {
        if (!empty($post['thumb_path'])) {
            return getNsfwImagePath($post['thumb_path']);
        }
    }
    return $shareImagePath;
}

?>
<!DOCTYPE html>
<html lang="ja">
<?php
// ----------------------------------------------------------------
// データの取得
$title = escapeHtml($data['title']);
$siteTitle = escapeHtml($theme['site_title'] ?? 'イラストポートフォリオ');
$description = escapeHtml($data['detail'] ?? $data['title']);

// SNS共有用の画像パスを決定
$isSensitive = isset($data['is_sensitive']) && $data['is_sensitive'] == 1;
$shareImagePath = '';

if ($isGroupPost) {
    // グループ投稿の場合：最初の画像のサムネイル
    if (!empty($data['images']) && !empty($data['images'][0]['thumb_path'])) {
        $shareImagePath = $data['images'][0]['thumb_path'];

        if ($isSensitive) {
            $shareImagePath = getNsfwImagePath($shareImagePath);
        }
    }
} else {
    // 単一投稿の場合
    if (!empty($data['image_path'])) {
        if ($isSensitive) {
            // NSFW画像の場合はNSFWフィルター版を使用
            $shareImagePath = getNsfwImagePath($data['image_path']);

            // パスの検証（uploadsディレクトリ内であることを確認）
            $fullPath = realpath(__DIR__ . '/../' . $shareImagePath);
            $uploadsDir = realpath(__DIR__ . '/../uploads/');

            // NSFWフィルター版が存在しない、または不正なパスの場合はサムネイルのNSFWフィルター版を使用
            if (!$fullPath || !$uploadsDir || strpos($fullPath, $uploadsDir) !== 0 || !file_exists($fullPath)) {
                if (!empty($data['thumb_path'])) {
                    $shareImagePath = getNsfwImagePath($data['thumb_path']);
                } else {
                    $shareImagePath = '';
                }
            }
        } else {
            // 通常の画像はサムネイルを使用
            $shareImagePath = $data['thumb_path'] ?? $data['image_path'];
        }
    }
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$fullUrl = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'];
$imageUrl = !empty($shareImagePath) ? $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . $shareImagePath : '';
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - <?= $siteTitle ?></title>
    <meta name="description" content="<?= $description ?>">

    <!-- OGP (Open Graph Protocol) -->
    <meta property="og:title" content="<?= $title ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= escapeHtml($fullUrl) ?>">
    <meta property="og:description" content="<?= escapeHtml(mb_substr($data['detail'] ?? $data['title'], 0, 200)) ?>">
    <meta property="og:site_name" content="<?= $siteTitle ?>">
    <?php if (!empty($imageUrl)): ?>
    <meta property="og:image" content="<?= escapeHtml($imageUrl) ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $title ?>">
    <meta name="twitter:description" content="<?= escapeHtml(mb_substr($data['detail'] ?? $data['title'], 0, 200)) ?>">
    <?php if (!empty($imageUrl)): ?>
    <meta name="twitter:image" content="<?= escapeHtml($imageUrl) ?>">
    <?php endif; ?>

    <!-- Googleフォント -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- スタイルシート -->
    <link rel="stylesheet" href="/res/css/main.css">

    <!-- テーマカラー -->
    <style>
        <?php require_once(__DIR__ . "/block/style.php") ?>
    </style>
</head>
<?php /**************************************************************/ ?>
<body data-age-verification-minutes="<?= $ageVerificationMinutes ?>" data-nsfw-config-version="<?= $nsfwConfigVersion ?>" data-post-id="<?= $id ?>" data-is-sensitive="<?= isset($data['is_sensitive']) && $data['is_sensitive'] == 1 ? '1' : '0' ?>">
    <script>
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
                <p style="font-size: 0.9em; color: #999; margin-top: 20px;">
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

    <?php define("ENABLE_BACK_BUTTON", 1) ?>
    <?php require_once(__DIR__."/block/header.php") ?>

    <!-- メインコンテンツ -->
    <div class="container">
        <div class="detail-card">
            <?php if ($isGroupPost): ?>
                <!-- グループ投稿：画像ギャラリー -->
                <?php if (!empty($data['images'])): ?>
                    <div class="image-gallery">
                        <?php foreach ($data['images'] as $index => $image):
                            $isSensitive = isset($data['is_sensitive']) && $data['is_sensitive'] == 1;
                            $imagePath = '/' . escapeHtml($image['image_path']);
                            // センシティブ画像の場合、最初はNSFWフィルター版を表示
                            if ($index === 0 && $isSensitive) {
                                $displayPath = createNsfwThumb($image);
                            } else {
                                $displayPath = $imagePath;
                            }
                        ?>
                            <img
                                id="detailImage"
                                class="gallery-image<?= $index === 0 ? ' active' : '' ?>"
                                src="<?= $displayPath ?>"
                                <?= $isSensitive ? 'data-original="' . $imagePath . '"' : '' ?>
                                alt="<?= escapeHtml($data['title']) ?> - <?= $index + 1 ?>"
                                data-index="<?= $index ?>"
                            >
                        <?php endforeach; ?>
                    </div>

                    <!-- ギャラリーナビゲーション -->
                    <?php if (count($data['images']) > 1): ?>
                        <div class="gallery-nav">
                            <button class="gallery-prev" onclick="previousImage()">&lt; 前へ</button>
                            <span class="gallery-counter">
                                <span id="currentImageIndex">1</span> / <?= count($data['images']) ?>
                            </span>
                            <button class="gallery-next" onclick="nextImage()">次へ &gt;</button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <!-- 単一投稿：単一画像 -->
                <?php
                $isSensitive = isset($data['is_sensitive']) && $data['is_sensitive'] == 1;
                $imagePath = '/' . escapeHtml($data['image_path'] ?? $data['thumb_path'] ?? '');
                // センシティブ画像の場合、最初はNSFWフィルター版を表示
                if ($isSensitive) {
                    $displayPath = createNsfwThumb($data);
                } else {
                    $displayPath = $imagePath;
                }
                ?>
                <img
                    id="detailImage"
                    src="<?= $displayPath ?>"
                    <?= $isSensitive ? 'data-original="' . $imagePath . '"' : '' ?>
                    alt="<?= escapeHtml($data['title']) ?>"
                    class="detail-image"
                >
            <?php endif; ?>

            <div class="detail-content">
                <?php if (isset($data['is_sensitive']) && $data['is_sensitive'] == 1): ?>
                    <div class="detail-nsfw-badge">NSFW / 18+</div>
                <?php endif; ?>

                <h1 class="detail-title"><?= escapeHtml($data['title']) ?></h1>
                <?php /*----------------------------------------------------------*/ ?>
                <div class="detail-meta">
                    <?php if ($isGroupPost && isset($data['image_count'])): ?>
                        <span class="meta-item">
                            <i class="bi bi-images me-1"></i><?= $data['image_count'] ?>枚
                        </span>
                    <?php endif; ?>

                    <span class="meta-item">
                        📅 投稿: <?= date('Y年m月d日', strtotime($data['created_at'])) ?>
                    </span>

                    <?php
                    // 最終更新日の表示（2000年以下の場合は作成日と同じとして扱う）
                    $updatedAt = $data['updated_at'] ?? $data['created_at'];
                    $updatedYear = (int)date('Y', strtotime($updatedAt));
                    if ($updatedYear <= 2000) {
                        $updatedAt = $data['created_at'];
                    }
                    // 作成日と更新日が異なる場合のみ表示
                    if ($updatedAt !== $data['created_at']):
                    ?>
                        <span class="meta-item">
                            🔄 更新: <?= date('Y年m月d日', strtotime($updatedAt)) ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($showViewCount && isset($data['view_count'])): ?>
                        <span class="meta-item view-count">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: -2px;">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                            </svg>
                            <?= number_format($data['view_count']) ?> 回閲覧
                        </span>
                    <?php endif; ?>
                </div>
                <?php /*----------------------------------------------------------*/ ?>
                <?php if (!empty($data['tags'])): ?>
                    <div class="detail-tags">
                        <?php
                        $tags = explode(',', $data['tags']);
                        foreach ($tags as $tag):
                            $tag = trim($tag);
                            if (!empty($tag)):
                        ?>
                            <span class="tag"><?= escapeHtml($tag) ?></span>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($data['detail'])): ?>
                    <div class="detail-description"><?= nl2br(escapeHtml($data['detail'])) ?></div>
                <?php endif; ?>

                <!-- SNS共有ボタン -->
                <div class="detail-actions" style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="shareToSNS('twitter')" style="display: inline-flex; align-items: center; gap: 8px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M5.026 15c6.038 0 9.341-5.003 9.341-9.334 0-.14 0-.282-.006-.422A6.685 6.685 0 0 0 16 3.542a6.658 6.658 0 0 1-1.889.518 3.301 3.301 0 0 0 1.447-1.817 6.533 6.533 0 0 1-2.087.793A3.286 3.286 0 0 0 7.875 6.03a9.325 9.325 0 0 1-6.767-3.429 3.289 3.289 0 0 0 1.018 4.382A3.323 3.323 0 0 1 .64 6.575v.045a3.288 3.288 0 0 0 2.632 3.218 3.203 3.203 0 0 1-.865.115 3.23 3.23 0 0 1-.614-.057 3.283 3.283 0 0 0 3.067 2.277A6.588 6.588 0 0 1 .78 13.58a6.32 6.32 0 0 1-.78-.045A9.344 9.344 0 0 0 5.026 15z"/>
                        </svg>
                        X (Twitter) で共有
                    </button>
                    <button class="btn btn-primary" onclick="shareToSNS('misskey')" style="display: inline-flex; align-items: center; gap: 8px; background-color: #86b300; border-color: #86b300;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M11.19 12.195c2.016-.24 3.77-1.475 3.99-2.603.348-1.778.32-4.339.32-4.339 0-3.47-2.286-4.488-2.286-4.488C12.062.238 10.083.017 8.027 0h-.05C5.92.017 3.942.238 2.79.765c0 0-2.285 1.017-2.285 4.488l-.002.662c-.004.64-.007 1.35.011 2.091.083 3.394.626 6.74 3.78 7.57 1.454.383 2.703.463 3.709.408 1.823-.1 2.847-.647 2.847-.647l-.06-1.317s-1.303.41-2.767.36c-1.45-.05-2.98-.156-3.215-1.928a3.614 3.614 0 0 1-.033-.496s1.424.346 3.228.428c1.103.05 2.137-.064 3.188-.189zm1.613-2.47H11.13v-4.08c0-.859-.364-1.295-1.091-1.295-.804 0-1.207.517-1.207 1.541v2.233H7.168V5.89c0-1.024-.403-1.541-1.207-1.541-.727 0-1.091.436-1.091 1.296v4.079H3.197V5.522c0-.859.22-1.541.66-2.046.456-.505 1.052-.764 1.793-.764.856 0 1.504.328 1.933.983L8 4.39l.417-.695c.429-.655 1.077-.983 1.934-.983.74 0 1.336.259 1.791.764.442.505.661 1.187.661 2.046v4.203z"/>
                        </svg>
                        Misskey で共有
                    </button>
                    <button class="btn btn-secondary" onclick="copyPageUrl()" style="display: inline-flex; align-items: center; gap: 8px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/>
                            <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/>
                        </svg>
                        URLをコピー
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php require_once(__DIR__."/block/footer.php") ?>

    <!-- JavaScript -->
    <script src="/res/js/detail.js?v=<?= $nsfwConfigVersion ?>"></script>
    <script>
        // DOMロード後に初期化
        document.addEventListener('DOMContentLoaded', function() {
            // 年齢確認チェック
            initDetailPage(<?= isset($data['is_sensitive']) && $data['is_sensitive'] == 1 ? 'true' : 'false' ?>, <?= $type ?>);
        });
    </script>


    <?php if ($isGroupPost): ?>
    <!-- グループ投稿用のギャラリーJS -->
    <script>
        let currentImageIndex = 0;
        const images = document.querySelectorAll('.gallery-image');
        const totalImages = images.length;

        function showImage(index) {
            images.forEach((img, i) => {
                img.classList.toggle('active', i === index);
            });
            document.getElementById('currentImageIndex').textContent = index + 1;
            currentImageIndex = index;
        }

        function nextImage() {
            const nextIndex = (currentImageIndex + 1) % totalImages;
            showImage(nextIndex);
        }

        function previousImage() {
            const prevIndex = (currentImageIndex - 1 + totalImages) % totalImages;
            showImage(prevIndex);
        }

        // キーボードナビゲーション
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') nextImage();
            if (e.key === 'ArrowLeft') previousImage();
        });
    </script>
    <?php endif; ?>

    <script>
        // SNS共有機能
        function shareToSNS(platform) {
            const title = <?= json_encode($data['title']) ?>;
            const url = encodeURIComponent(window.location.href);
            const encodedTitle = encodeURIComponent(title);
            const hashtags = 'イラスト,artwork';
            const isSensitive = <?= (isset($data['is_sensitive']) && $data['is_sensitive'] == 1) ? 'true' : 'false' ?>;
            const nsfwHashtag = isSensitive ? ',NSFW' : '';
            const fullHashtags = encodeURIComponent(hashtags + nsfwHashtag);

            let shareUrl;
            if (platform === 'twitter') {
                shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${encodedTitle}&hashtags=${fullHashtags}`;
            } else if (platform === 'misskey') {
                shareUrl = `https://misskey-hub.net/share/?text=${encodedTitle}%20${url}`;
            }

            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        // URLコピー機能
        function copyPageUrl() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                alert('URLをコピーしました！');
            }).catch(err => {
                console.error('コピーに失敗しました:', err);
            });
        }
    </script>
</body>
</html>
