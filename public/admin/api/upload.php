<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Cache\CacheManager;
use App\Models\Post;
use App\Utils\ImageUploader;

class UploadController extends AdminControllerBase
{
    private Post $postModel;

    public function __construct()
    {
        $this->postModel = new Post();
    }

    protected function onProcess(string $method): void
    {
        if ($method !== 'POST') {
            $this->sendError('POSTメソッドのみ許可されています', 405);
        }

        $this->handleUpload();
    }

    private function handleUpload(): void
    {
        // 入力検証
        if (empty($_POST['title'])) {
            $this->sendError('タイトルは必須です');
        }

        if (!isset($_FILES['image'])) {
            $this->sendError('画像ファイルが必要です');
        }

        // NSFW設定を読み込み
        $config = \App\Config\ConfigManager::getInstance()->getConfig();
        $nsfwConfig = $config['nsfw'];
        $filterSettings = $nsfwConfig['filter_settings'];

        // ImageUploaderを初期化
        $imageUploader = new ImageUploader(
            __DIR__ . '/../../uploads/images',
            __DIR__ . '/../../uploads/thumbs',
            10 * 1024 * 1024 // 10MB
        );

        // ファイルアップロード検証
        $validation = $imageUploader->validateFile($_FILES['image']);
        if (!$validation['valid']) {
            $this->sendError($validation['error']);
        }

        // 投稿データを取得
        $title = trim($_POST['title']);
        $tags = isset($_POST['tags']) ? trim($_POST['tags']) : null;
        $detail = isset($_POST['detail']) ? trim($_POST['detail']) : null;
        $isSensitive = isset($_POST['is_sensitive']) ? (int)$_POST['is_sensitive'] : 0;
        $isVisible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 1;

        // ユニークなファイル名を生成
        $uniqueName = $imageUploader->generateUniqueFilename();

        // 画像を処理して保存
        $uploadResult = $imageUploader->processAndSave(
            $_FILES['image']['tmp_name'],
            $validation['mime_type'],
            $uniqueName,
            $isSensitive == 1, // センシティブ画像の場合はNSFWフィルター版も生成
            $filterSettings // フィルター設定
        );

        if (!$uploadResult['success']) {
            $this->sendError($uploadResult['error']);
        }

        // データベースに保存
        $postId = $this->postModel->create(
            $title,
            $tags,
            $detail,
            $uploadResult['image_path'],
            $uploadResult['thumb_path'],
            $isSensitive,
            $isVisible
        );

        // キャッシュを無効化
        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        // 成功レスポンス
        $this->sendSuccess([
            'id' => $postId,
            'message' => '投稿が作成されました'
        ]);
    }
}

// コントローラーを実行
$controller = new UploadController();
$controller->execute();
