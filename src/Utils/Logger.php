<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Loggerクラス
 *
 * アプリケーションのログを管理するSingletonクラス
 */

class Logger
{
    private static ?Logger $instance = null;
    private array $config;
    private array $logLevels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];

    /**
     * コンストラクタ（プライベート）
     */
    private function __construct()
    {
        // 設定をロード
        require_once __DIR__ . '/../../config/loader.php';
        $this->config = loadConfig('config')['app_logging'] ?? [];

        // デフォルト設定
        if (empty($this->config)) {
            $this->config = [
                'enabled' => true,
                'log_file' => __DIR__ . '/../../logs/app.log',
                'level' => 'error',
                'format' => '%timestamp [%level] %file:%line %message'
            ];
        }

        // ログディレクトリが存在しない場合は作成
        $logDir = dirname($this->config['log_file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * インスタンスを取得（Singleton）
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * デバッグログ
     */
    public function debug(string $message): void
    {
        $this->log('debug', $message);
    }

    /**
     * 情報ログ
     */
    public function info(string $message): void
    {
        $this->log('info', $message);
    }

    /**
     * 警告ログ
     */
    public function warning(string $message): void
    {
        $this->log('warning', $message);
    }

    /**
     * エラーログ
     */
    public function error(string $message): void
    {
        $this->log('error', $message);
    }

    /**
     * ログ出力
     */
    private function log(string $level, string $message): void
    {
        if (!$this->config['enabled'] || !$this->shouldLog($level)) {
            return;
        }

        // 呼び出し元のファイルと行を取得
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? [];
        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? 0;

        // 相対ファイルパスを取得
        $relativeFile = $this->getRelativeFile($file);

        // タイムスタンプ
        $timestamp = date('Y-m-d H:i:s');

        // フォーマット適用
        $logLine = str_replace(
            ['%timestamp', '%level', '%file', '%line', '%message'],
            [$timestamp, strtoupper($level), $relativeFile, $line, $message],
            $this->config['format']
        ) . "\n";

        // ファイルに書き込み
        file_put_contents($this->config['log_file'], $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * 指定レベルをログ出力すべきか判定
     */
    private function shouldLog(string $level): bool
    {
        $currentLevel = $this->config['level'] ?? 'error';
        return ($this->logLevels[$level] ?? 0) >= ($this->logLevels[$currentLevel] ?? 3);
    }

    /**
     * 相対ファイルパスを取得
     */
    private function getRelativeFile(string $file): string
    {
        $root = realpath(__DIR__ . '/../../');
        if ($root === false) {
            return $file;
        }
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}