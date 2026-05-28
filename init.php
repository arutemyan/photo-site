<?php

declare(strict_types=1);

/**
 * データベース初期化スクリプト（CLI専用）
 *
 * 使用方法:
 *   php init.php
 *
 * 注意:
 *   このスクリプトはCLI専用です。
 *   ブラウザからのセットアップは public/setup.php を使用してください。
 */

// CLIからの実行かチェック
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "このスクリプトはコマンドラインからのみ実行できます。\n";
    echo "ブラウザからのセットアップは /setup.php をご利用ください。\n";
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Connection;

echo "\n";
echo "========================================\n";
echo "  データベース初期化ツール (CLI版)\n";
echo "========================================\n\n";

try {
    require_once __DIR__."/scripts/run_migrations.php";

    // データベース接続を取得（自動的にスキーマが初期化される）
    $db = Connection::getInstance();

    // 既に管理者が存在するかチェック
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        echo "⚠️  既に管理者ユーザーが存在します。\n";
        echo "登録ユーザー数: {$result['count']}人\n\n";
    } else {
        echo "管理者アカウントを作成します。\n\n";

        // ユーザー名の入力
        echo "ユーザー名 (3〜50文字、英数字・ハイフン・アンダースコアのみ): ";
        $username = trim(fgets(STDIN));

        // バリデーション
        if (empty($username)) {
            throw new Exception("ユーザー名を入力してください。");
        }

        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new Exception("ユーザー名は3〜50文字で入力してください。");
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new Exception("ユーザー名は英数字、ハイフン、アンダースコアのみ使用できます。");
        }

        // パスワードの入力（非表示）
        echo "\nパスワード (8文字以上、小文字・大文字・数字を含む): ";

        // パスワード入力を非表示にする
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $password = stream_get_line(STDIN, 1024, PHP_EOL);
        } else {
            // Unix/Linux/Mac
            system('stty -echo');
            $password = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
        }

        // パスワード確認
        echo "パスワード（確認）: ";

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $passwordConfirm = stream_get_line(STDIN, 1024, PHP_EOL);
        } else {
            system('stty -echo');
            $passwordConfirm = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
        }

        // バリデーション
        if (empty($password)) {
            throw new Exception("パスワードを入力してください。");
        }

        if (strlen($password) < 8) {
            throw new Exception("パスワードは8文字以上で入力してください。");
        }

        // パスワード強度チェック
        $hasLower = preg_match('/[a-z]/', $password);
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);

        if (!$hasLower || !$hasUpper || !$hasNumber) {
            throw new Exception("パスワードは小文字、大文字、数字をそれぞれ1文字以上含む必要があります。");
        }

        if ($password !== $passwordConfirm) {
            throw new Exception("パスワードが一致しません。");
        }

        // パスワードをハッシュ化
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // ユーザーを挿入
        $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $passwordHash]);

        echo "\n✓ 管理者ユーザー '{$username}' を作成しました。\n";
    }

    // 投稿数を確認
    $stmt = $db->query("SELECT COUNT(*) as count FROM posts");
    $result = $stmt->fetch();
    echo "\n現在の投稿数: {$result['count']}件\n";

    // ユーザー数を確認
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "登録ユーザー数: {$result['count']}人\n\n";

    echo "========================================\n";
    echo "  セットアップ完了！\n";
    echo "========================================\n\n";
    echo "次のステップ:\n";
    echo "1. 開発サーバーを起動: php -S localhost:8000 -t public/\n";
    echo "2. ブラウザでアクセス: http://localhost:8000\n";
    echo "3. 管理画面にログイン: http://localhost:8000/admin/login.php\n\n";

} catch (Exception $e) {
    echo "\nエラー: " . $e->getMessage() . "\n\n";
    exit(1);
}
