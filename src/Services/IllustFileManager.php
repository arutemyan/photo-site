<?php
declare(strict_types=1);

namespace App\Services;

/**
 * ファイル操作専門クラス
 * - ディレクトリ作成
 * - ファイル保存/削除
 * - バックアップ管理
 * - パス操作
 */
class IllustFileManager
{
    private string $uploadsDir;

    public function __construct(string $uploadsDir)
    {
        $this->uploadsDir = rtrim($uploadsDir, '/');
    }

    /**
     * イラストIDに基づいてパス情報を生成
     *
     * @return array{
     *   dataPath: string,
     *   imagePath: string,
     *   thumbPath: string,
     *   timelapsePath: string,
     *   dataDir: string,
     *   imagesDir: string,
     *   timelapseDir: string
     * }
     */
    public function generatePaths(int $id): array
    {
        $sub = sprintf('%03d', $id % 1000);
        $basePath = $this->uploadsDir . '/paintfiles';

        $imagesDir = $basePath . '/images/' . $sub;
        $dataDir = $basePath . '/data/' . $sub;
        $timelapseDir = $basePath . '/timelapse/' . $sub;

        return [
            'dataPath' => $dataDir . '/illust_' . $id . '.illust',
            'imagePath' => $imagesDir . '/illust_' . $id . '.jpg',
            'thumbPath' => $imagesDir . '/illust_' . $id . '_thumb.webp',
            'timelapsePath' => $timelapseDir . '/timelapse_' . $id . '.csv.gz',
            'dataDir' => $dataDir,
            'imagesDir' => $imagesDir,
            'timelapseDir' => $timelapseDir,
        ];
    }

    /**
     * 必要なディレクトリを作成
     */
    public function ensureDirectories(array $paths): void
    {
        @mkdir($paths['imagesDir'], 0755, true);
        @mkdir($paths['dataDir'], 0755, true);
        @mkdir($paths['timelapseDir'], 0755, true);
    }

    /**
     * .illustファイルを保存（アトミック書き込み）
     */
    public function saveIllustFile(string $path, string $content): void
    {
        $tmpFile = $path . '.tmp';
        if (file_put_contents($tmpFile, $content) === false) {
            throw new \RuntimeException('Failed to write .illust file');
        }
        if (!@rename($tmpFile, $path)) {
            throw new \RuntimeException('Failed to move .illust file into place');
        }
    }

    /**
     * タイムラプスファイルを保存（アトミック書き込み）
     */
    public function saveTimelapseFile(string $path, string $gzippedData): void
    {
        $tmpFile = $path . '.tmp';
        if (file_put_contents($tmpFile, $gzippedData) === false) {
            throw new \RuntimeException('Failed to write timelapse file');
        }
        if (!@rename($tmpFile, $path)) {
            throw new \RuntimeException('Failed to move timelapse into place');
        }
    }

    /**
     * 既存ファイルのバックアップを作成
     *
     * @param string[] $filePaths バックアップ対象ファイルパス配列
     * @return array バックアップ情報 [[元ファイル, バックアップファイル], ...]
     */
    public function createBackups(array $filePaths): array
    {
        $backups = [];
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                $backupPath = $filePath . '.bak';
                if (@copy($filePath, $backupPath)) {
                    $backups[] = [$filePath, $backupPath];
                }
            }
        }
        return $backups;
    }

    /**
     * バックアップからファイルを復元
     *
     * @param array $backups createBackups()の戻り値
     */
    public function restoreBackups(array $backups): void
    {
        foreach ($backups as [$originalPath, $backupPath]) {
            if (file_exists($backupPath)) {
                @copy($backupPath, $originalPath);
                @unlink($backupPath);
            }
        }
    }

    /**
     * バックアップファイルを削除
     *
     * @param array $backups createBackups()の戻り値
     */
    public function cleanupBackups(array $backups): void
    {
        foreach ($backups as [$_, $backupPath]) {
            if (file_exists($backupPath)) {
                @unlink($backupPath);
            }
        }
    }

    /**
     * 作成したファイルを削除（ロールバック用）
     *
     * @param string[] $filePaths
     */
    public function deleteFiles(array $filePaths): void
    {
        foreach ($filePaths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * 絶対パスを公開パスに変換
     */
    public function toPublicPath(string $absPath): string
    {
        $cwd = getcwd();
        if (strpos($absPath, $cwd) === 0) {
            $rel = substr($absPath, strlen($cwd));
        } else {
            $rel = $absPath;
        }

        $normalized = $this->normalizePath($rel);

        if ($normalized === '' || $normalized[0] !== '/') {
            $normalized = '/' . ltrim($normalized, '/');
        }

        return $normalized;
    }

    /**
     * パスを正規化（. や .. を展開）
     */
    private function normalizePath(string $path): string
    {
        $parts = preg_split('#/+#', $path, -1, PREG_SPLIT_NO_EMPTY);
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                if (!empty($stack)) {
                    array_pop($stack);
                }
                continue;
            }
            $stack[] = $part;
        }
        return '/' . implode('/', $stack);
    }
}
