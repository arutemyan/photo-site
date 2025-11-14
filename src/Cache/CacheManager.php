<?php

declare(strict_types=1);

namespace App\Cache;

use Exception;

require_once __DIR__ . '/../Security/SecurityUtil.php';

/**
 * 静的キャッシュ管理クラス
 *
 * JSONファイルベースの高速キャッシュシステム
 * - 原子的書き込み（ファイル破損防止）
 * - 3-10msの超高速レスポンスを実現
 */
class CacheManager
{
    private string $cacheDir;
    private array $config;

    /**
     * コンストラクタ
     *
     * @param string|null $cacheDir キャッシュディレクトリのパス（nullの場合は設定ファイルから読み込み）
     */
    public function __construct(?string $cacheDir = null)
    {
        // 設定ファイルを読み込み（ConfigManager 経由）
        $this->config = \App\Config\ConfigManager::getInstance()->get('cache', []);

        // キャッシュディレクトリのパスを決定
        if ($cacheDir !== null) {
            // 引数で指定された場合はそれを使用（後方互換性）
            $this->cacheDir = rtrim($cacheDir, '/');
        } else {
            // 設定ファイルから読み込み
            $this->cacheDir = rtrim($this->config['cache_dir'] ?? __DIR__ . '/../../cache', '/');
        }

        // キャッシュディレクトリを作成して保護
        $permissions = $this->config['dir_permissions'] ?? 0755;
        ensureSecureDirectory($this->cacheDir, $permissions);
    }

    /**
     * キャッシュを取得
     *
     * @param string $key キャッシュキー
     * @return mixed|null キャッシュデータ、存在しない場合はnull
     */
    public function get(string $key): mixed
    {
        $filePath = $this->getCacheFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // JSON形式でデコード
        $data = json_decode($content, true);
        return $data !== null ? $data : null;
    }

    /**
     * キャッシュを保存（原子的書き込み）
     *
     * @param string $key キャッシュキー
     * @param mixed $data 保存するデータ
     * @return bool 成功した場合true
     */
    public function set(string $key, mixed $data): bool
    {
        $filePath = $this->getCacheFilePath($key);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false) {
            return false;
        }

        // 原子的書き込み（一時ファイル→rename）
        $tempFile = $filePath . '.tmp.' . uniqid();

        try {
            // 一時ファイルに書き込み
            if (file_put_contents($tempFile, $json, LOCK_EX) === false) {
                return false;
            }

            // 原子的に上書き
            if (!rename($tempFile, $filePath)) {
                @unlink($tempFile);
                return false;
            }

            return true;
        } catch (Exception $e) {
            // エラーをログに記録（デバッグ用）
            \App\Utils\Logger::getInstance()->error('Cache write failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
            @unlink($tempFile);
            return false;
        }
    }

    /**
     * キャッシュを削除
     *
     * @param string $key キャッシュキー
     * @return bool 成功した場合true
     */
    public function delete(string $key): bool
    {
        $filePath = $this->getCacheFilePath($key);

        if (!file_exists($filePath)) {
            return true;
        }

        return @unlink($filePath);
    }

    /**
     * すべてのキャッシュをクリア
     *
     * @return int 削除したファイル数
     */
    public function clear(): int
    {
        $count = 0;
        $files = glob($this->cacheDir . '/*.json');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * キャッシュが存在するか確認
     *
     * @param string $key キャッシュキー
     * @return bool 存在する場合true
     */
    public function has(string $key): bool
    {
        return file_exists($this->getCacheFilePath($key));
    }

    /**
     * キャッシュファイルの内容を直接読み取り（高速）
     *
     * @param string $key キャッシュキー
     * @return string|null ファイル内容、存在しない場合はnull
     */
    public function readRaw(string $key): ?string
    {
        $filePath = $this->getCacheFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        return $content !== false ? $content : null;
    }

    /**
     * キャッシュファイルのパスを取得
     *
     * @param string $key キャッシュキー
     * @return string ファイルパス
     */
    private function getCacheFilePath(string $key): string
    {
        // キーをサニタイズ（安全なファイル名に変換）
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . '/' . $safeKey . '.json';
    }

    /**
     * 投稿一覧キャッシュを無効化
     */
    public function invalidatePostsList(): void
    {
        $this->delete('posts_list');
    }

    /**
     * 投稿詳細キャッシュを無効化
     *
     * @param int $postId 投稿ID
     */
    public function invalidatePost(int $postId): void
    {
        $this->delete("post_{$postId}");
    }

    /**
     * すべての投稿キャッシュを無効化
     */
    public function invalidateAllPosts(): void
    {
        $this->invalidatePostsList();

        // すべてのpost_*.jsonを削除
        $files = glob($this->cacheDir . '/post_*.json');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
}
