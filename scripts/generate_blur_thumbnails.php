<?php

declare(strict_types=1);

/**
 * 既存NSFW投稿のフィルターサムネイル生成マイグレーションスクリプト
 *
 * 使用方法:
 *   php generate_blur_thumbnails.php           - NSFWフィルター画像を生成
 *   php generate_blur_thumbnails.php --force   - 強制再生成
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Utils\ImageUploader;

// NSFW設定を読み込み
$config = require __DIR__ . '/../config/config.php';
$nsfwConfig = $config['nsfw'];
$filterSettings = $nsfwConfig['filter_settings'];

// コマンドライン引数を確認
$forceRegenerate = in_array('--force', $argv ?? []);

echo "==========================================\n";
echo "NSFWフィルターサムネイル生成スクリプト\n";
echo "==========================================\n\n";
echo "フィルター効果: すりガラス (ガウシアンブラー + 白オーバーレイ)\n\n";

if ($forceRegenerate) {
    echo "⚠️  強制再生成モード: 既存のNSFWフィルター画像を削除して再生成します\n\n";
}

try {
    // データベース接続
    $db = Connection::getInstance();

    // ImageUploaderのインスタンスを作成（ディレクトリパスはダミー）
    $imageUploader = new ImageUploader(
        __DIR__ . '/../public/uploads/images',
        __DIR__ . '/../public/uploads/thumbs'
    );

    // is_sensitive=1の投稿を取得
    $stmt = $db->query("SELECT id, image_path, thumb_path, is_sensitive FROM posts WHERE is_sensitive = 1");
    $posts = $stmt->fetchAll();

    if (empty($posts)) {
        echo "センシティブな投稿が見つかりませんでした。\n";
        exit(0);
    }

    echo "センシティブな投稿: " . count($posts) . "件\n\n";

    $successCount = 0;
    $failCount = 0;
    $skippedCount = 0;

    foreach ($posts as $post) {
        $id = $post['id'];
        $thumbPath = $post['thumb_path'] ?? $post['image_path'];

        if (empty($thumbPath)) {
            echo "[投稿ID: {$id}] スキップ: 画像パスが空です\n";
            $skippedCount++;
            continue;
        }

        // 物理ファイルパスを構築
        $thumbFullPath = __DIR__ . '/../public/' . $thumbPath;

        if (!file_exists($thumbFullPath)) {
            echo "[投稿ID: {$id}] スキップ: 画像ファイルが見つかりません ({$thumbFullPath})\n";
            $skippedCount++;
            continue;
        }

        // NSFWフィルター版のパスを生成
        $pathInfo = pathinfo($thumbFullPath);
        $nsfwPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_nsfw.' . $pathInfo['extension'];

        // 強制再生成モードの場合、既存のファイルを削除
        if ($forceRegenerate && file_exists($nsfwPath)) {
            unlink($nsfwPath);
            echo "[投稿ID: {$id}] 既存のNSFWフィルター画像を削除しました\n";
        }

        // すでにNSFWフィルター版が存在する場合はスキップ
        if (file_exists($nsfwPath)) {
            echo "[投稿ID: {$id}] スキップ: NSFWフィルター版が既に存在します\n";
            $skippedCount++;
            continue;
        }

        // NSFWフィルター画像を生成（ImageUploaderクラスを使用）
        try {
            $imageUploader->createNsfwThumbnail($thumbFullPath, $nsfwPath, $filterSettings);
            echo "[投稿ID: {$id}] 成功: NSFWフィルターサムネイルを生成しました\n";
            $successCount++;
        } catch (Exception $e) {
            echo "[投稿ID: {$id}] 失敗: {$e->getMessage()}\n";
            $failCount++;
        }
    }

    echo "\n==========================================\n";
    echo "処理完了\n";
    echo "==========================================\n";
    echo "成功: {$successCount}件\n";
    echo "失敗: {$failCount}件\n";
    echo "スキップ: {$skippedCount}件\n";
    echo "合計: " . count($posts) . "件\n\n";

    if ($successCount > 0) {
        echo "✅ NSFWフィルター画像（すりガラス効果）が生成されました。\n";
    }

} catch (Exception $e) {
    echo "\nエラーが発生しました: " . $e->getMessage() . "\n";
    echo "スタックトレース:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
