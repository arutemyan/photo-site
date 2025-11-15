<?php
declare(strict_types=1);

namespace App\Services;

use App\Utils\Logger;

/**
 * タイムラプス処理専門クラス
 * - タイムラプスデータの解析（JSON/CSV）
 * - タイムラプスのマージ
 * - CSV/JSON変換
 */
class IllustTimelapseProcessor
{
    /**
     * タイムラプスデータを解析してイベント配列を返す
     *
     * @param string $gzippedData gzip圧縮されたタイムラプスデータ
     * @return array|null イベント配列、または解析失敗時null
     */
    public function parseTimelapse(string $gzippedData): ?array
    {
        $rawDecoded = @gzdecode($gzippedData);
        if ($rawDecoded === false) {
            $rawDecoded = $gzippedData;
        }

        // Try JSON first
        $maybe = @json_decode($rawDecoded, true);
        if (is_array($maybe)) {
            return $maybe;
        }

        // Try CSV parsing
        if (is_string($rawDecoded) || (is_scalar($rawDecoded) && !is_array($rawDecoded))) {
            $rawStr = (string)$rawDecoded;
            if (strpos($rawStr, "\n") !== false) {
                $lines = preg_split("/\r\n|\n|\r/", trim($rawStr));
                if ($lines && count($lines) > 0) {
                    $header = str_getcsv(array_shift($lines));
                    if ($header && count($header) > 0) {
                        $events = [];
                        foreach ($lines as $ln) {
                            if (trim($ln) === '') {
                                continue;
                            }
                            $vals = str_getcsv($ln);
                            if (count($vals) !== count($header)) {
                                continue;
                            }
                            $events[] = array_combine($header, $vals);
                        }
                        if (count($events) > 0) {
                            return $events;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * 既存タイムラプスと新規タイムラプスをマージ
     *
     * @param array $existingEvents 既存イベント配列
     * @param array $newEvents 新規イベント配列
     * @return array マージ後のイベント配列（重複除去済み）
     */
    public function mergeTimelapse(array $existingEvents, array $newEvents): array
    {
        $merged = array_merge($existingEvents, $newEvents);

        // Deduplicate
        $seen = [];
        $unique = [];
        foreach ($merged as $event) {
            $sig = md5(json_encode($event));
            if (isset($seen[$sig])) {
                continue;
            }
            $seen[$sig] = true;
            $unique[] = $event;
        }

        return $unique;
    }

    /**
     * イベント配列をCSV形式に変換してgzip圧縮
     *
     * @param array $events イベント配列
     * @return string gzip圧縮されたCSVデータ
     */
    public function convertToCSV(array $events): string
    {
        if (!is_array($events)) {
            Logger::getInstance()->error('IllustTimelapseProcessor: events is not an array');
            $events = [];
        }

        // Extract all headers
        $headers = [];
        foreach ($events as $ev) {
            if (!is_array($ev)) {
                Logger::getInstance()->warning('IllustTimelapseProcessor: skipping non-array event: ' . gettype($ev));
                continue;
            }
            foreach ($ev as $k => $_) {
                if (!in_array($k, $headers, true)) {
                    $headers[] = $k;
                }
            }
        }

        // Build CSV lines
        $csvLines = [];
        $csvLines[] = implode(',', $headers);
        foreach ($events as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $row = [];
            foreach ($headers as $h) {
                $v = $ev[$h] ?? '';
                if (is_array($v)) {
                    $v = json_encode($v);
                }
                $s = (string)$v;
                // CSV escaping
                if (strpos($s, ',') !== false || strpos($s, '"') !== false || strpos($s, "\n") !== false) {
                    $s = '"' . str_replace('"', '""', $s) . '"';
                }
                $row[] = $s;
            }
            $csvLines[] = implode(',', $row);
        }

        $csvText = implode("\n", $csvLines);
        $gz = gzencode($csvText);
        if ($gz === false) {
            throw new \RuntimeException('Failed to gzip CSV timelapse data');
        }

        return $gz;
    }

    /**
     * イベント配列をJSON形式に変換してgzip圧縮
     *
     * @param array $data イベント配列またはパッケージ（events/snapshots含む）
     * @return string gzip圧縮されたJSONデータ
     */
    public function convertToJSON(array $data): string
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode timelapse data to JSON');
        }

        $gz = gzencode($json);
        if ($gz === false) {
            throw new \RuntimeException('Failed to gzip JSON timelapse data');
        }

        return $gz;
    }

    /**
     * タイムラプスデータがJSONパッケージ（events/snapshots含む）かチェック
     */
    public function isJSONPackage(array $data): bool
    {
        return isset($data['events']) || isset($data['snapshots']);
    }
}
