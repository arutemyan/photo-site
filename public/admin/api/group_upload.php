<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Models\Post;
use App\Models\GroupPostImage;
use App\Utils\ImageUploader;
use App\Cache\CacheManager;

class GroupUploadController extends AdminControllerBase
{
    private Post $postModel;
    private GroupPostImage $groupPostImageModel;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->groupPostImageModel = new GroupPostImage();
    }

    protected function onProcess(string $method): void
    {
        if ($method !== 'POST') {
            $this->sendError('POSTメソッドが必要です', 405);
        }

        $this->handleGroupUpload();
    }

    private function handleGroupUpload(): void
    {
        // アップロードされたファイルを確認
        if (empty($_FILES['images'])) {
            $this->sendError('画像ファイルが選択されていません');
        }

        $groupPostId = isset($_POST['group_post_id']) ? (int)$_POST['group_post_id'] : 0;
        $isAddingToExisting = $groupPostId > 0;

        // 新規作成の場合はタイトルが必須
        if (!$isAddingToExisting) {
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            if (empty($title)) {
                $this->sendError('タイトルは必須です');
            }
        }

        // 既存グループに追加の場合は存在確認
        $groupPost = null;
        $isSensitive = 0;
        $maxDisplayOrder = 0;

        if ($isAddingToExisting) {
            $groupPost = $this->postModel->getById($groupPostId);
            if (!$groupPost || $groupPost['post_type'] != 1) {
                $this->sendError('グループ投稿が見つかりません', 404);
            }

            // 既存グループのセンシティブ設定を使用
            $isSensitive = $groupPost['is_sensitive'];

            // 現在のグループ内の最大display_orderを取得
            $images = $this->groupPostImageModel->getImagesByPostId($groupPostId);
            foreach ($images as $img) {
                if ($img['display_order'] > $maxDisplayOrder) {
                    $maxDisplayOrder = $img['display_order'];
                }
            }
        } else {
            // 新規作成の場合はPOSTパラメータから取得
            $isSensitive = isset($_POST['is_sensitive']) ? (int)$_POST['is_sensitive'] : 0;
        }

        $uploadedFiles = $_FILES['images'];

        // ImageUploaderを初期化
        $imageUploader = new ImageUploader(
            __DIR__ . '/../../uploads/images',
            __DIR__ . '/../../uploads/thumbs',
            20 * 1024 * 1024 // 20MB
        );

        $imagePaths = [];
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        // 複数ファイルの処理
        $fileCount = count($uploadedFiles['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $filename = $uploadedFiles['name'][$i];
            $tmpPath = $uploadedFiles['tmp_name'][$i];

            // ファイル配列を構築
            $file = [
                'name' => $uploadedFiles['name'][$i],
                'tmp_name' => $tmpPath,
                'error' => $uploadedFiles['error'][$i],
                'size' => $uploadedFiles['size'][$i]
            ];

            // ファイル検証
            $validation = $imageUploader->validateFile($file);
            if (!$validation['valid']) {
                $results[] = [
                    'filename' => $filename,
                    'success' => false,
                    'error' => $validation['error']
                ];
                $errorCount++;
                continue;
            }

            try {
                // NSFW設定を読み込み
                $config = \App\Config\ConfigManager::getInstance()->getConfig();
                $nsfwConfig = $config['nsfw'];
                $filterSettings = $nsfwConfig['filter_settings'];

                // ユニークなファイル名を生成
                $uniqueName = $imageUploader->generateUniqueFilename('group_');

                // 画像を処理して保存
                $uploadResult = $imageUploader->processAndSave(
                    $tmpPath,
                    $validation['mime_type'],
                    $uniqueName,
                    $isSensitive == 1, // NSFWフィルター版を作成
                    $filterSettings
                );

                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['error']);
                }

                // 新規作成の場合は配列に追加、既存グループへの追加の場合は直接DB登録
                if ($isAddingToExisting) {
                    $maxDisplayOrder++;
                    $this->groupPostImageModel->addImage(
                        $groupPostId,
                        $uploadResult['image_path'],
                        $uploadResult['thumb_path'],
                        $maxDisplayOrder
                    );
                } else {
                    $imagePaths[] = [
                        'image' => $uploadResult['image_path'],
                        'thumb' => $uploadResult['thumb_path']
                    ];
                }

                $results[] = [
                    'filename' => $filename,
                    'success' => true
                ];
                $successCount++;

            } catch (Exception $e) {
                $results[] = [
                    'filename' => $filename,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        // 少なくとも1枚の画像が成功している必要がある
        if ($successCount === 0) {
            $this->sendError('有効な画像がアップロードされませんでした', 400, ['results' => $results]);
        }

        // 新規作成の場合のみグループ投稿を作成
        if (!$isAddingToExisting) {
            $tags = $_POST['tags'] ?? '';
            $detail = $_POST['detail'] ?? '';
            $isVisible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 1;

            $groupPostId = $this->postModel->createGroupPost(
                $title,
                $imagePaths,
                $tags,
                $detail,
                $isSensitive,
                $isVisible
            );
        }

        // キャッシュを無効化
        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        // レスポンス
        if ($isAddingToExisting) {
            $message = "{$successCount}枚の画像をグループ投稿に追加しました";
        } else {
            $message = "グループ投稿「{$title}」を作成しました（{$successCount}枚の画像）";
        }

        $this->sendSuccess([
            'group_post_id' => $groupPostId,
            'total' => $fileCount,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
            'message' => $message
        ]);
    }
}

// コントローラーを実行
$controller = new GroupUploadController();
$controller->execute();
