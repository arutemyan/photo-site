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

                // Normalize on server: there are two observed storage styles
                //  - header with field names, rows with values (legacy)
                //  - numeric header 0,1,2,... where each cell contains a JSON event string
                // To simplify client logic, parse both and re-emit a canonical "headered CSV" where
                // each row is a single event with columns equal to the union of event keys.

                $lines = preg_split('/\r\n|\n|\r/', $content);
                if ($lines === false || count($lines) === 0) {
                    return [ 'success' => false, 'error' => 'Empty timelapse content' ];
                }

                // parse header line
                $headerLine = trim($lines[0]);
                $headers = str_getcsv($headerLine);
                $numericHeader = true;
                foreach ($headers as $h) {
                    if (!preg_match('/^\d+$/', trim((string)$h))) { $numericHeader = false; break; }
                }

                $events = [];

                if ($numericHeader) {
                    // each data cell is expected to be a JSON event string
                    for ($i = 1; $i < count($lines); $i++) {
                        $line = trim($lines[$i]);
                        if ($line === '') continue;
                        $cells = str_getcsv($line);
                        foreach ($cells as $cell) {
                            if ($cell === null || $cell === '') continue;
                            $decoded = json_decode($cell, true);
                            if ($decoded === null) {
                                // try to repair doubled quotes
                                $repaired = str_replace('""', '"', $cell);
                                $decoded = json_decode($repaired, true);
                            }
                            if ($decoded !== null) {
                                $events[] = $decoded;
                            } else {
                                // fallback: store raw string
                                $events[] = ['raw' => $cell];
                            }
                        }
                    }
                } else {
                    // header names: treat each row as an event mapping header->value
                    $fieldNames = $headers;
                    for ($i = 1; $i < count($lines); $i++) {
                        $line = trim($lines[$i]);
                        if ($line === '') continue;
                        $vals = str_getcsv($line);
                        $event = [];
                        for ($j = 0; $j < count($fieldNames); $j++) {
                            $key = $fieldNames[$j] ?? null;
                            if ($key === null) continue;
                            $val = $vals[$j] ?? '';
                            if ($val === '') continue;
                            // try json decode if value looks like object/array
                            $v = null;
                            if (is_string($val) && (strpos($val, '{') === 0 || strpos($val, '[') === 0)) {
                                $v = json_decode($val, true);
                                if ($v === null) $v = $val; // keep raw if decode fails
                            } else {
                                $v = $val;
                            }
                            $event[$key] = $v;
                        }
                        if (!empty($event)) $events[] = $event;
                    }
                }

                // Build canonical headered CSV from $events
                // Preferred ordering for common fields
                $preferred = ['t','type','layer','x','y','pressure','color','size','tool','watercolorHardness','watercolorOpacity'];
                $allKeys = [];
                foreach ($events as $ev) {
                    if (!is_array($ev)) continue;
                    foreach ($ev as $k => $v) {
                        if (!in_array($k, $allKeys, true)) $allKeys[] = $k;
                    }
                }
                // order keys with preferred first
                $remaining = array_values(array_diff($allKeys, $preferred));
                $orderedKeys = array_values(array_intersect($preferred, $allKeys));
                $orderedKeys = array_merge($orderedKeys, $remaining);

                // Use fputcsv into memory to produce correctly escaped CSV
                $fp = fopen('php://temp', 'r+');
                if ($fp === false) {
                    return [ 'success' => false, 'error' => 'Failed to create temp stream' ];
                }
                // write header
                fputcsv($fp, $orderedKeys);
                // write rows
                foreach ($events as $ev) {
                    $row = [];
                    foreach ($orderedKeys as $k) {
                        if (is_array($ev) && array_key_exists($k, $ev)) {
                            $val = $ev[$k];
                            if (is_array($val)) {
                                $row[] = json_encode($val, JSON_UNESCAPED_UNICODE);
                            } else {
                                $row[] = (string)$val;
                            }
                        } else {
                            $row[] = '';
                        }
                    }
                    fputcsv($fp, $row);
                }
                rewind($fp);
                $normalized = stream_get_contents($fp);
                fclose($fp);

                return [
                    'success' => true,
                    'format' => 'csv',
                    'csv' => $normalized
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
