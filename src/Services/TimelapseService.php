<?php
declare(strict_types=1);

namespace App\Services;

use App\Utils\EnvChecks;
use App\Utils\Logger;

class TimelapseService
{
    private string $timelapsePath;

    public function __construct(string $timelapsePath)
    {
        $this->timelapsePath = rtrim($timelapsePath, '/');
    }

    /**
     * Save raw gzipped timelapse data (CSV or other gzipped payload) to path. Caller should validate size/header.
     */
    public function save(string $idSubdir, string $filename, string $binary): string
    {
        $dir = $this->timelapsePath . '/' . $idSubdir;
        @mkdir($dir, 0755, true);
        $path = $dir . '/' . $filename;
        file_put_contents($path, $binary);
        return $path;
    }

    public function load(string $path): string
    {
        if (!file_exists($path)) {
            throw new \RuntimeException('Timelapse not found');
        }
        return file_get_contents($path);
    }

    /**
     * タイムラプスデータを取得して解析
     * 
     * @param int $illustId イラストID
     * @param string $publicRoot publicディレクトリの絶対パス
     * @return array ['success' => bool, 'format' => string, 'csv' => string | 'timelapse' => array, 'error' => string]
     */
    public static function getTimelapseData(int $illustId, string $publicRoot): array
    {
        try {
            if ($illustId <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid id'
                ];
            }
            
            $db = \App\Database\Connection::getInstance();
            
            // イラスト情報を取得
            $stmt = $db->prepare("SELECT timelapse_path FROM paint WHERE id = ?");
            $stmt->execute([$illustId]);
            $illust = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$illust || empty($illust['timelapse_path'])) {
                return [
                    'success' => false,
                    'error' => 'Timelapse not found'
                ];
            }
            
            // タイムラプスファイルのパスを解決
            $timelapseFile = $publicRoot . $illust['timelapse_path'];
            
            if (!file_exists($timelapseFile)) {
                Logger::getInstance()->error("Timelapse file not found: {$timelapseFile} (from timelapse_path: {$illust['timelapse_path']})");
                return [
                    'success' => false,
                    'error' => 'Timelapse file not found'
                ];
            }
            
            // ファイル拡張子で処理を分岐
            $ext = pathinfo($timelapseFile, PATHINFO_EXTENSION);
            
            if ($ext === 'gz') {
                // gzip圧縮されたCSVファイル
                $content = gzdecode(file_get_contents($timelapseFile));
                
                if ($content === false) {
                    return [
                        'success' => false,
                        'error' => 'Failed to decompress timelapse data'
                    ];
                }
                
                return [
                    'success' => true,
                    'format' => 'csv',
                    'csv' => $content
                ];
                
            } else if ($ext === 'json') {
                // JSONファイル
                $content = file_get_contents($timelapseFile);
                $data = json_decode($content, true);
                
                if ($data === null) {
                    return [
                        'success' => false,
                        'error' => 'Failed to parse timelapse JSON'
                    ];
                }
                
                return [
                    'success' => true,
                    'format' => 'json',
                    'timelapse' => $data
                ];
                
            } else {
                return [
                    'success' => false,
                    'error' => 'Unsupported timelapse format'
                ];
            }
            
        } catch (\Exception $e) {
            Logger::getInstance()->error('TimelapseService::getTimelapseData Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Server error'
            ];
        }
    }

    // msgpack support removed; timelapse uses gzipped CSV
}
