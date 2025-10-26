<?php

declare(strict_types=1);

/**
 * 管理画面API ルーター
 *
 * すべての管理画面APIエンドポイントをここで管理
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Http\Router;
use App\Models\Post;
use App\Models\Theme;
use App\Models\Setting;
use App\Security\CsrfProtection;
use App\Utils\ImageUploader;

// セッション開始
initSecureSession();

$router = new Router();

/**
 * 認証ミドルウェア
 */
$router->middleware(function ($method, $path) {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        Router::error('認証が必要です。ログインしてください。', 401);
        return false;
    }
    return true;
});

/**
 * GET /admin/api/posts
 * 投稿一覧を取得（非表示含む）
 */
$router->get('/admin/api/posts', function () {
    try {
        $postModel = new Post();
        $posts = $postModel->getAllForAdmin(100);

        Router::json([
            'success' => true,
            'count' => count($posts),
            'posts' => $posts
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (GET /admin/api/posts): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました: ' . $e->getMessage(), 500);
    }
});

/**
 * GET /admin/api/posts/:id
 * 単一投稿を取得（非表示含む）
 */
$router->get('/admin/api/posts/:id', function (string $id) {
    try {
        $postId = (int)$id;
        $postModel = new Post();
        $post = $postModel->getByIdForAdmin($postId);

        if ($post === null) {
            Router::error('投稿が見つかりません', 404);
            return;
        }

        Router::json([
            'success' => true,
            'post' => $post
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (GET /admin/api/posts/:id): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * POST /admin/api/posts
 * 新規投稿を作成
 */
$router->post('/admin/api/posts', function () {
    try {
        // CSRF検証
        if (!CsrfProtection::validatePost()) {
            Router::error('CSRFトークンが無効です', 403);
            return;
        }

        // パラメータ取得
        $title = $_POST['title'] ?? '';
        $tags = $_POST['tags'] ?? '';
        $detail = $_POST['detail'] ?? '';
        $isSensitive = isset($_POST['is_sensitive']) ? (int)$_POST['is_sensitive'] : 0;

        // バリデーション
        if (empty($title)) {
            Router::error('タイトルは必須です', 400);
            return;
        }

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            Router::error('画像ファイルが必要です', 400);
            return;
        }

        // 画像アップロード処理
        $imageUploader = new ImageUploader();
        $uploadDir = __DIR__ . '/../../../uploads/images/';
        $thumbDir = __DIR__ . '/../../../uploads/thumbs/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $uploadPath = $uploadDir . $filename;
        $thumbFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
        $thumbPath = $thumbDir . $thumbFilename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            Router::error('画像のアップロードに失敗しました', 500);
            return;
        }

        // サムネイル生成
        $imageUploader->createThumbnail($uploadPath, $thumbPath, 600, 600);

        // NSFWの場合、NSFWフィルターサムネイルも生成
        if ($isSensitive) {
            $nsfwPath = $thumbDir . pathinfo($thumbFilename, PATHINFO_FILENAME) . '_nsfw.webp';
            $imageUploader->createNsfwThumbnail($thumbPath, $nsfwPath);
        }

        // DB登録
        $postModel = new Post();
        $postId = $postModel->create(
            $title,
            $tags,
            $detail,
            'uploads/images/' . $filename,
            'uploads/thumbs/' . $thumbFilename,
            $isSensitive
        );

        Router::json([
            'success' => true,
            'message' => '投稿が作成されました',
            'post_id' => $postId
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (POST /admin/api/posts): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * PUT /admin/api/posts/:id
 * 投稿を更新
 */
$router->put('/admin/api/posts/:id', function (string $id) {
    try {
        // CSRF検証
        if (!CsrfProtection::validatePost()) {
            Router::error('CSRFトークンが無効です', 403);
            return;
        }

        $postId = (int)$id;
        $title = $_POST['title'] ?? '';
        $tags = $_POST['tags'] ?? '';
        $detail = $_POST['detail'] ?? '';
        $isSensitive = isset($_POST['is_sensitive']) ? (int)$_POST['is_sensitive'] : 0;
        $isVisible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 0;

        // バリデーション
        if (empty($title)) {
            Router::error('タイトルは必須です', 400);
            return;
        }

        $postModel = new Post();
        $existingPost = $postModel->getByIdForAdmin($postId);

        if ($existingPost === null) {
            Router::error('投稿が見つかりません', 404);
            return;
        }

        // NSFWフィルター設定を読み込み
        $config = require __DIR__ . '/../../../config/config.php';
        $filterSettings = $config['nsfw']['filter_settings'];

        // is_sensitiveが変更された場合、NSFWフィルター画像を処理
        $oldIsSensitive = (int)$existingPost['is_sensitive'];
        $newIsSensitive = $isSensitive;

        if ($oldIsSensitive !== $newIsSensitive) {
            $thumbPath = $existingPost['thumb_path'];
            if (!empty($thumbPath)) {
                $thumbFullPath = __DIR__ . '/../../../public/' . $thumbPath;
                $pathInfo = pathinfo($thumbFullPath);
                $nsfwPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_nsfw.' . ($pathInfo['extension'] ?? 'webp');

                if ($newIsSensitive === 1) {
                    // 0→1: NSFWフィルター画像を生成
                    if (file_exists($thumbFullPath)) {
                        $imageUploader = new ImageUploader(
                            __DIR__ . '/../../../public/uploads/images',
                            __DIR__ . '/../../../public/uploads/thumbs'
                        );
                        $imageUploader->createNsfwThumbnail($thumbFullPath, $nsfwPath, $filterSettings);
                    }
                } else {
                    // 1→0: NSFWフィルター画像を削除
                    if (file_exists($nsfwPath)) {
                        unlink($nsfwPath);
                    }
                }
            }
        }

        // 更新
        $result = $postModel->updateTextOnly($postId, $title, $tags, $detail, $isSensitive);

        if ($result) {
            $postModel->setVisibility($postId, $isVisible);
        }

        Router::json([
            'success' => $result,
            'message' => '投稿が更新されました'
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (PUT /admin/api/posts/:id): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * DELETE /admin/api/posts/:id
 * 投稿を削除
 */
$router->delete('/admin/api/posts/:id', function (string $id) {
    try {
        // CSRF検証
        if (!CsrfProtection::validatePost()) {
            Router::error('CSRFトークンが無効です', 403);
            return;
        }

        $postId = (int)$id;
        $postModel = new Post();
        $result = $postModel->delete($postId);

        Router::json([
            'success' => $result,
            'message' => '投稿が削除されました'
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (DELETE /admin/api/posts/:id): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * POST /admin/api/bulk-upload
 * 一括アップロード
 */
$router->post('/admin/api/bulk-upload', function () {
    try {
        // CSRF検証
        if (!CsrfProtection::validatePost()) {
            Router::error('CSRFトークンが無効です', 403);
            return;
        }

        if (empty($_FILES['images'])) {
            Router::error('画像ファイルが選択されていません', 400);
            return;
        }

        $uploadedFiles = $_FILES['images'];
        $postModel = new Post();
        $imageUploader = new ImageUploader(
            __DIR__ . '/../../../public/uploads/images',
            __DIR__ . '/../../../public/uploads/thumbs'
        );

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        $fileCount = count($uploadedFiles['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $filename = $uploadedFiles['name'][$i];
            $tmpPath = $uploadedFiles['tmp_name'][$i];
            $error = $uploadedFiles['error'][$i];
            $size = $uploadedFiles['size'][$i];

            if ($error !== UPLOAD_ERR_OK) {
                $results[] = ['filename' => $filename, 'success' => false, 'error' => 'アップロードエラー'];
                $errorCount++;
                continue;
            }

            if ($size > 20 * 1024 * 1024) {
                $results[] = ['filename' => $filename, 'success' => false, 'error' => 'ファイルサイズが大きすぎます'];
                $errorCount++;
                continue;
            }

            try {
                // ファイルの検証
                $fileData = [
                    'error' => $error,
                    'size' => $size,
                    'tmp_name' => $tmpPath
                ];
                $validation = $imageUploader->validateFile($fileData);

                if (!$validation['valid']) {
                    throw new Exception($validation['error']);
                }

                // ユニークなファイル名を生成
                $uniqueName = $imageUploader->generateUniqueFilename('bulk_');

                // 画像を処理して保存
                $uploadResult = $imageUploader->processAndSave(
                    $tmpPath,
                    $validation['mime_type'],
                    $uniqueName,
                    false  // 一括アップロードではNSFWフィルターは生成しない
                );

                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['error']);
                }

                // DB登録
                $postId = $postModel->createBulk(
                    $uploadResult['image_path'],
                    $uploadResult['thumb_path']
                );

                $results[] = ['filename' => $filename, 'success' => true, 'post_id' => $postId];
                $successCount++;
            } catch (Exception $e) {
                $results[] = ['filename' => $filename, 'success' => false, 'error' => $e->getMessage()];
                $errorCount++;
            }
        }

        Router::json([
            'success' => true,
            'total' => $fileCount,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (POST /admin/api/bulk-upload): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * GET /admin/api/theme
 * テーマ設定を取得
 */
$router->get('/admin/api/theme', function () {
    try {
        $themeModel = new Theme();
        $theme = $themeModel->getCurrent();

        Router::json([
            'success' => true,
            'theme' => $theme
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (GET /admin/api/theme): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * POST /admin/api/theme
 * テーマ設定を更新
 */
$router->post('/admin/api/theme', function () {
    try {
        // CSRF検証
        if (!CsrfProtection::validatePost()) {
            Router::error('CSRFトークンが無効です', 403);
            return;
        }

        // テーマデータを収集
        $data = [];
        $allowedFields = [
            'header_html', 'footer_html', 'site_title', 'site_subtitle',
            'site_description', 'primary_color', 'secondary_color',
            'accent_color', 'background_color'
        ];

        foreach ($allowedFields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
        }

        $themeModel = new Theme();
        $result = $themeModel->update($data);

        Router::json([
            'success' => $result,
            'message' => 'テーマ設定が更新されました'
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (POST /admin/api/theme): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * POST /admin/api/theme/upload-image
 * テーマ画像をアップロード
 */
$router->post('/admin/api/theme/upload-image', function () {
    try {
        // CSRF検証
        if (!CsrfProtection::validatePost()) {
            Router::error('CSRFトークンが無効です', 403);
            return;
        }

        $field = $_POST['field'] ?? ''; // header_image or logo_image

        if (!in_array($field, ['header_image', 'logo_image'])) {
            Router::error('無効なフィールドです', 400);
            return;
        }

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            Router::error('画像ファイルが必要です', 400);
            return;
        }

        // 画像アップロード
        $uploadDir = __DIR__ . '/../../../uploads/theme/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = $field . '_' . time() . '.' . $ext;
        $uploadPath = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            Router::error('画像のアップロードに失敗しました', 500);
            return;
        }

        // DBを更新
        $themeModel = new Theme();
        $themeModel->updateImage($field, 'uploads/theme/' . $filename);

        Router::json([
            'success' => true,
            'message' => '画像がアップロードされました',
            'path' => 'uploads/theme/' . $filename
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (POST /admin/api/theme/upload-image): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * GET /admin/api/settings
 * サイト設定を取得
 */
$router->get('/admin/api/settings', function () {
    try {
        $settingModel = new Setting();
        $showViewCount = $settingModel->get('show_view_count', '1');

        Router::json([
            'success' => true,
            'settings' => [
                'show_view_count' => $showViewCount === '1'
            ]
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (GET /admin/api/settings): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

/**
 * POST /admin/api/settings
 * サイト設定を更新
 */
$router->post('/admin/api/settings', function () {
    try {
        // CSRF検証
        if (!CsrfProtection::validatePost()) {
            Router::error('CSRFトークンが無効です', 403);
            return;
        }

        $showViewCount = isset($_POST['show_view_count']) && $_POST['show_view_count'] === '1' ? '1' : '0';

        $settingModel = new Setting();
        $settingModel->set('show_view_count', $showViewCount);

        Router::json([
            'success' => true,
            'message' => 'サイト設定が更新されました'
        ]);
    } catch (Exception $e) {
        error_log('Admin API Error (POST /admin/api/settings): ' . $e->getMessage());
        Router::error('サーバーエラーが発生しました', 500);
    }
});

// ルーティングを実行
$router->dispatch();
