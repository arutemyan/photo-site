<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Models\Post;
use App\Models\GroupPostImage;
use App\Utils\ImageUploader;
use App\Cache\CacheManager;

class GroupImageReplaceController extends AdminControllerBase
{
    private Post $postModel;
    private GroupPostImage $groupPostImageModel;
    private ImageUploader $imageUploader;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->groupPostImageModel = new GroupPostImage();
        $this->imageUploader = new ImageUploader(
            __DIR__ . '/../../uploads/images',
            __DIR__ . '/../../uploads/thumbs',
            20 * 1024 * 1024 // 20MB
        );
    }

    protected function onProcess(string $method): void
    {
        switch ($method) {
            case 'POST':
                $this->handleReplace();
                break;
            case 'DELETE':
                $this->handleDelete();
                break;
            default:
                $this->sendError('POSTまたはDELETEメソッドのみ許可されています', 405);
        }
    }

    private function handleDelete(): void
    {
        $imageId = (int)($_POST['image_id'] ?? 0);

        if ($imageId <= 0) {
            $this->sendError('画像IDが無効です', 400);
        }

        // 画像情報を取得
        $image = $this->groupPostImageModel->getImageById($imageId);
        if (!$image) {
            $this->sendError('画像が見つかりません', 404);
        }

        // グループ内の画像数を確認（最低1枚は必要）
        $postId = $image['post_id'];
        $imageCount = $this->groupPostImageModel->getImageCountByPostId($postId);
        if ($imageCount <= 1) {
            $this->sendError('グループ内の最後の画像は削除できません', 400);
        }

        // ファイルを削除
        $imagePath = __DIR__ . '/../../' . $image['image_path'];
        $thumbPath = __DIR__ . '/../../' . $image['thumb_path'];

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        if ($image['thumb_path'] && file_exists($thumbPath)) {
            unlink($thumbPath);
        }

        // NSFWフィルター版の画像も削除
        $nsfwImages = [
            str_replace('.webp', '_blur.webp', $imagePath),
            str_replace('.webp', '_frosted.webp', $imagePath),
            str_replace('.webp', '_blur.webp', $thumbPath),
            str_replace('.webp', '_frosted.webp', $thumbPath)
        ];
        foreach ($nsfwImages as $nsfwPath) {
            if (file_exists($nsfwPath)) {
                unlink($nsfwPath);
            }
        }

        // DBから削除
        $success = $this->groupPostImageModel->deleteImage($imageId);

        if (!$success) {
            $this->sendError('削除に失敗しました', 500);
        }

        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        $this->sendSuccess(['message' => '画像を削除しました']);
    }

    private function handleReplace(): void
    {
        $imageId = (int)($_POST['image_id'] ?? 0);

        if ($imageId <= 0) {
            $this->sendError('画像IDが無効です', 400);
        }

        // 画像情報を取得
        $image = $this->groupPostImageModel->getImageById($imageId);
        if (!$image) {
            $this->sendError('画像が見つかりません', 404);
        }

        // 新しい画像ファイルをチェック
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->sendError('画像ファイルがアップロードされていません', 400);
        }

        // グループ投稿情報を取得（is_sensitive設定のため）
        $postId = $image['post_id'];
        $groupPost = $this->postModel->getById($postId);
        if (!$groupPost || $groupPost['post_type'] != 1) {
            $this->sendError('グループ投稿が見つかりません', 404);
        }

        // ファイル検証
        $validation = $this->imageUploader->validateFile($_FILES['image']);
        if (!$validation['valid']) {
            $this->sendError($validation['error'], 400);
        }

        // NSFW設定を読み込み
        $config = \App\Config\ConfigManager::getInstance()->getConfig();
        $nsfwConfig = $config['nsfw'];
        $filterSettings = $nsfwConfig['filter_settings'];

        // ユニークなファイル名を生成
        $uniqueName = $this->imageUploader->generateUniqueFilename('group_');

        // 画像を処理して保存
        $uploadResult = $this->imageUploader->processAndSave(
            $_FILES['image']['tmp_name'],
            $validation['mime_type'],
            $uniqueName,
            $groupPost['is_sensitive'] == 1, // NSFWフィルター版を作成
            $filterSettings
        );

        if (!$uploadResult['success']) {
            $this->sendError($uploadResult['error'], 500);
        }

        // 古い画像ファイルを削除
        $oldImagePath = __DIR__ . '/../../' . $image['image_path'];
        $oldThumbPath = __DIR__ . '/../../' . $image['thumb_path'];

        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }
        if ($image['thumb_path'] && file_exists($oldThumbPath)) {
            unlink($oldThumbPath);
        }

        // 古いNSFWフィルター版の画像も削除
        $oldNsfwImages = [
            str_replace('.webp', '_blur.webp', $oldImagePath),
            str_replace('.webp', '_frosted.webp', $oldImagePath),
            str_replace('.webp', '_blur.webp', $oldThumbPath),
            str_replace('.webp', '_frosted.webp', $oldThumbPath)
        ];
        foreach ($oldNsfwImages as $nsfwPath) {
            if (file_exists($nsfwPath)) {
                unlink($nsfwPath);
            }
        }

        // DBを更新
        $success = $this->groupPostImageModel->updateImage(
            $imageId,
            $uploadResult['image_path'],
            $uploadResult['thumb_path']
        );

        if (!$success) {
            $this->sendError('更新に失敗しました', 500);
        }

        $cache = new CacheManager();
        $cache->invalidateAllPosts();

        $this->sendSuccess([
            'message' => '画像を差し替えました',
            'image_path' => $uploadResult['image_path'],
            'thumb_path' => $uploadResult['thumb_path']
        ]);
    }
}

// コントローラーを実行
$controller = new GroupImageReplaceController();
$controller->execute();
