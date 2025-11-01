<?php

declare(strict_types=1);

/**
 * セットアップスクリプト
require_once __DIR__ . '/../src/Utils/path_helpers.php';
 *
 * 初回セットアップ用のブラウザベース設定画面
 * セキュリティのため、このファイルはランダムな名前にリネームできます
 * セットアップ完了後、このファイルは自動的に削除されます
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Security/SecurityUtil.php';

use App\Database\Connection;

session_start();

// エラーメッセージ
$error = null;
$success = null;

// セットアップ済みかチェック
try {
    $db = Connection::getInstance();

    // 既に管理者ユーザーが存在するかチェック
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        // 既にセットアップ済み

        // マイグレーション実行リクエストの処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'migrate') {
            try {
                // CSRF検証
                if (!isset($_POST['csrf_token']) || !isset($_SESSION['migrate_csrf_token'])) {
                    throw new Exception('不正なリクエストです。');
                }

                if (!hash_equals($_SESSION['migrate_csrf_token'], $_POST['csrf_token'])) {
                    throw new Exception('不正なリクエストです。');
                }

                // マイグレーションを実行
                $runner = Connection::getMigrationRunner();
                $results = $runner->run();

                if (empty($results)) {
                    $success = 'すべてのマイグレーションは既に実行済みです。';
                } else {
                    $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
                    $success = "{$successCount}件のマイグレーションが完了しました。";
                }

                unset($_SESSION['migrate_csrf_token']);

                // マイグレーション完了後に自動削除オプションが有効な場合
                if (isset($_POST['auto_delete']) && $_POST['auto_delete'] === '1') {
                    $setupFile = __FILE__;
                    if (@unlink($setupFile)) {
                        // 削除成功、リダイレクト
                        header('Location: ' . admin_url('login.php?setup_deleted=1&migration_completed=1'));
                        exit;
                    } else {
                        $success .= ' セットアップファイルの自動削除に失敗しました。手動で削除してください。';
                    }
                }

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        // 削除リクエストの処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
            try {
                // CSRF検証
                if (!isset($_POST['csrf_token']) || !isset($_SESSION['delete_csrf_token'])) {
                    throw new Exception('不正なリクエストです。');
                }

                if (!hash_equals($_SESSION['delete_csrf_token'], $_POST['csrf_token'])) {
                    throw new Exception('不正なリクエストです。');
                }

                // 管理者が存在することを再確認
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
                $stmt->execute();
                $result = $stmt->fetch();

                if ($result['count'] == 0) {
                    throw new Exception('管理者ユーザーが見つかりません。');
                }

                // このファイルを削除
                $setupFile = __FILE__;
                if (@unlink($setupFile)) {
                    // 削除成功、リダイレクト
                    header('Location: ' . admin_url('login.php?setup_deleted=1'));
                    exit;
                } else {
                    throw new Exception('ファイルの削除に失敗しました。権限を確認してください。');
                }

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        // CSRFトークン生成
        if (!isset($_SESSION['delete_csrf_token'])) {
            $_SESSION['delete_csrf_token'] = bin2hex(random_bytes(32));
        }
        if (!isset($_SESSION['migrate_csrf_token'])) {
            $_SESSION['migrate_csrf_token'] = bin2hex(random_bytes(32));
        }

        // マイグレーション状態を取得
        $executedMigrations = Connection::getExecutedMigrations();

        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>セットアップ完了済み</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    padding: 20px;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                    max-width: 500px;
                    width: 100%;
                }
                h1 {
                    color: #333;
                    margin-top: 0;
                    font-size: 1.8em;
                }
                .alert {
                    background: #fff3cd;
                    border: 1px solid #ffc107;
                    border-radius: 5px;
                    padding: 15px;
                    margin: 20px 0;
                    color: #856404;
                }
                .alert-danger {
                    background: #f8d7da;
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border: none;
                    border-radius: 5px;
                    transition: background 0.3s;
                    cursor: pointer;
                    font-size: 1em;
                }
                .btn:hover {
                    background: #764ba2;
                }
                .btn-danger {
                    background: #dc3545;
                }
                .btn-danger:hover {
                    background: #c82333;
                }
                .button-group {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                    margin-top: 20px;
                }
                .delete-section {
                    border-top: 1px solid #ddd;
                    margin-top: 30px;
                    padding-top: 20px;
                }
                .delete-section h2 {
                    font-size: 1.2em;
                    color: #dc3545;
                    margin-bottom: 10px;
                }
                .migration-section {
                    border-top: 1px solid #ddd;
                    margin-top: 30px;
                    padding-top: 20px;
                }
                .migration-section h2 {
                    font-size: 1.2em;
                    color: #667eea;
                    margin-bottom: 10px;
                }
                .migration-list {
                    background: #f8f9fa;
                    border-radius: 5px;
                    padding: 15px;
                    margin: 15px 0;
                    max-height: 200px;
                    overflow-y: auto;
                }
                .migration-item {
                    padding: 8px 0;
                    border-bottom: 1px solid #dee2e6;
                }
                .migration-item:last-child {
                    border-bottom: none;
                }
                .migration-version {
                    font-weight: bold;
                    color: #667eea;
                }
                .migration-date {
                    font-size: 0.85em;
                    color: #666;
                }
                .alert-success {
                    background: #d4edda;
                    border: 1px solid #c3e6cb;
                    color: #155724;
                    border-radius: 5px;
                    padding: 15px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🔒 セットアップ完了済み</h1>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        ❌ <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="alert-success">
                        ✅ <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <div class="alert">
                    このサイトは既にセットアップが完了しています。<br>
                    セキュリティのため、このファイルを削除することを推奨します。
                </div>

                <div class="button-group">
                    <a href="/" class="btn">トップページへ</a>
                    <a href="<?= admin_url('login.php') ?>" class="btn" style="background: #8B5AFA;">ログイン</a>
                </div>

                <div class="migration-section">
                    <h2>🔄 データベースマイグレーション</h2>
                    <p style="color: #666; margin-bottom: 15px;">
                        データベース構造の更新を管理します。
                    </p>

                    <?php if (empty($executedMigrations)): ?>
                        <div style="color: #856404; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin-bottom: 15px;">
                            ⚠️ マイグレーションが実行されていません。
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="migrate">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['migrate_csrf_token']) ?>">
                            <button type="submit" class="btn" style="background: #667eea;">マイグレーションを実行</button>
                        </form>
                    <?php else: ?>
                        <div style="color: #155724; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin-bottom: 15px;">
                            ✅ マイグレーション: <?= count($executedMigrations) ?>件実行済み
                        </div>

                        <details>
                            <summary style="cursor: pointer; color: #667eea; font-weight: 500; margin-bottom: 10px;">
                                実行済みマイグレーション一覧を表示
                            </summary>
                            <div class="migration-list">
                                <?php foreach ($executedMigrations as $migration): ?>
                                    <div class="migration-item">
                                        <span class="migration-version">バージョン <?= $migration['version'] ?>:</span>
                                        <?= htmlspecialchars($migration['name']) ?>
                                        <div class="migration-date">実行日時: <?= htmlspecialchars($migration['executed_at']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>

                        <form method="POST" style="margin-top: 15px;" id="migrationForm">
                            <input type="hidden" name="action" value="migrate">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['migrate_csrf_token']) ?>">

                            <div style="margin-bottom: 15px;">
                                <label style="display: flex; align-items: center; cursor: pointer; color: #666;">
                                    <input type="checkbox" name="auto_delete" value="1" style="margin-right: 8px;">
                                    <span>マイグレーション完了後に自動的にこのファイルを削除</span>
                                </label>
                                <div style="font-size: 0.85em; color: #999; margin-top: 5px; margin-left: 24px;">
                                    ⚠️ 削除後は元に戻せません。必要に応じてバックアップを取ってください。
                                </div>
                            </div>

                            <button type="submit" class="btn" style="background: #667eea;" onclick="return confirm('マイグレーションを実行しますか？\n既に実行済みのマイグレーションはスキップされます。');">
                                マイグレーションを確認・実行
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="delete-section">
                    <h2>⚠️ このファイルを削除</h2>
                    <p style="color: #666; margin-bottom: 15px;">
                        セキュリティリスクを避けるため、このセットアップファイルを削除してください。
                    </p>
                    <form method="POST" onsubmit="return confirm('本当にこのファイルを削除しますか？');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['delete_csrf_token']) ?>">
                        <button type="submit" class="btn btn-danger">このファイルを削除する</button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

} catch (Exception $e) {
    error_log('Setup Error: ' . $e->getMessage());
    $error = 'データベースエラーが発生しました。';
}

// POSTリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF検証
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['setup_csrf_token'])) {
            throw new Exception('不正なリクエストです。');
        }

        if (!hash_equals($_SESSION['setup_csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('不正なリクエストです。');
        }

        // 入力値取得
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // バリデーション
        if (empty($username)) {
            throw new Exception('ユーザー名を入力してください。');
        }

        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new Exception('ユーザー名は3〜50文字で入力してください。');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new Exception('ユーザー名は英数字、ハイフン、アンダースコアのみ使用できます。');
        }

        if (empty($password)) {
            throw new Exception('パスワードを入力してください。');
        }

        if (strlen($password) < 8) {
            throw new Exception('パスワードは8文字以上で入力してください。');
        }

        // パスワード強度チェック
        $hasLower = preg_match('/[a-z]/', $password);
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);

        if (!$hasLower || !$hasUpper || !$hasNumber) {
            throw new Exception('パスワードは小文字、大文字、数字をそれぞれ1文字以上含む必要があります。');
        }

        if ($password !== $passwordConfirm) {
            throw new Exception('パスワードが一致しません。');
        }

        // 管理者ユーザーを作成
        $db = Connection::getInstance();

        // 念のため再度確認
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            throw new Exception('既に管理者ユーザーが存在します。');
        }

        // パスワードをハッシュ化
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // ユーザーを挿入
        $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $passwordHash]);

        // CSRFトークンをクリア
        unset($_SESSION['setup_csrf_token']);

        // 成功メッセージ
        $success = true;

        // このファイルを削除
        $setupFile = __FILE__;
        $deleted = @unlink($setupFile);

        if (!$deleted) {
            error_log("Warning: Failed to delete setup file: {$setupFile}");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// CSRFトークン生成
if (!isset($_SESSION['setup_csrf_token'])) {
    $_SESSION['setup_csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初回セットアップ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2em;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .help-text {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #764ba2;
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .success-container {
            text-align: center;
        }

        .success-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }

        .success-container h2 {
            color: #155724;
            margin-bottom: 15px;
        }

        .success-container p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .security-note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9em;
        }

        .security-note strong {
            color: #856404;
        }

        .password-requirements {
            background: #f8f9fa;
            border-left: 3px solid #667eea;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.85em;
        }

        .password-requirements ul {
            margin-left: 20px;
            margin-top: 8px;
        }

        .password-requirements li {
            margin: 4px 0;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="success-container">
                <div class="success-icon">✅</div>
                <h2>セットアップ完了！</h2>
                <p>
                    管理者アカウントの作成が完了しました。<br>
                    これでログインできます。
                </p>
                <a href="<?= admin_url('login.php') ?>" class="btn">ログインページへ</a>

                <?php if (!@unlink(__FILE__)): ?>
                <div class="security-note">
                    <strong>⚠️ セキュリティ通知</strong><br>
                    セットアップファイルの自動削除に失敗しました。<br>
                    セキュリティのため、手動で以下のファイルを削除してください：<br>
                    <code><?= htmlspecialchars(basename(__FILE__)) ?></code>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <h1>🎨 初回セットアップ</h1>
            <p class="subtitle">管理者アカウントを作成してください</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['setup_csrf_token']) ?>">

                <div class="form-group">
                    <label for="username">ユーザー名</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autofocus
                        pattern="[a-zA-Z0-9_-]+"
                        minlength="3"
                        maxlength="50"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    >
                    <div class="help-text">3〜50文字、英数字・ハイフン・アンダースコアのみ</div>
                </div>

                <div class="form-group">
                    <label for="password">パスワード</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        minlength="8"
                    >
                    <div class="password-requirements">
                        <strong>パスワード要件：</strong>
                        <ul>
                            <li>8文字以上</li>
                            <li>小文字を1文字以上含む</li>
                            <li>大文字を1文字以上含む</li>
                            <li>数字を1文字以上含む</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">パスワード（確認）</label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        required
                        minlength="8"
                    >
                </div>

                <button type="submit" class="btn">管理者アカウントを作成</button>
            </form>

            <div class="security-note" style="margin-top: 30px;">
                <strong>🔒 セキュリティに関する注意</strong><br>
                このセットアップページは、完了後に自動的に削除されます。<br>
                セキュリティのため、このファイル名をランダムな名前に変更することもできます。
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
