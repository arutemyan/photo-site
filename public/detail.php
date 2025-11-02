<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Security/SecurityUtil.php';

use App\Models\Post;
use App\Models\GroupPost;
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

    // 投稿を取得（タイプに応じて切り替え）
    if ($isGroupPost) {
        $model = new GroupPost();
        $data = $model->getById($id);

        if ($data === null) {
            header('Location: /index.php');
            exit;
        }

        // グループ投稿の閲覧数をインクリメント
        $model->incrementViewCount($id);
    } else {
        $model = new Post();
        $data = $model->getById($id);

        if ($data === null) {
            header('Location: /index.php');
            exit;
        }
    }

} catch (Exception $e) {
    error_log('Post Detail Error (' . $type . '): ' . $e->getMessage());
    header('Location: /index.php');
    exit;
}

function createNsfwThumb($post) {
    // NSFW画像の場合はNSFWフィルター版を使用
    $pathInfo = pathinfo($post['image_path'] ?? $data['thumb_path'] ?? '');
    // basename()でディレクトリトラバーサルを防止
    $nsfwFilename = basename($pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp'));
    $shareImagePath = $pathInfo['dirname'] . '/' . $nsfwFilename;

    // パスの検証（uploadsディレクトリ内であることを確認）
    $fullPath = realpath(__DIR__ . '/' . $shareImagePath);
    $uploadsDir = realpath(__DIR__ . '/uploads/');

    // NSFWフィルター版が存在しない、または不正なパスの場合はサムネイルのNSFWフィルター版を使用
    if (!$fullPath || !$uploadsDir || strpos($fullPath, $uploadsDir) !== 0 || !file_exists($fullPath)) {
        if (!empty($post['thumb_path'])) {
            $thumbInfo = pathinfo($post['thumb_path']);
            $nsfwThumbFilename = basename($thumbInfo['filename'] . '_nsfw.' . ($thumbInfo['extension'] ?? 'webp'));
            return $thumbInfo['dirname'] . '/' . $nsfwThumbFilename;
        }
    }
    return "";
}

?>
<!DOCTYPE html>
<html lang="ja">
<?php require_once(__DIR__ . "/block/detail_head.php") ?>
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
                            if ($isSensitive) {
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

                <?php require_once(__DIR__ . "/block/detail_meta.php") ?>

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
