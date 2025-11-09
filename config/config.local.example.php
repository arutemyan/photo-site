<?php
/**
 * ローカル環境設定ファイル（サンプル）
 * 
 * このファイルをコピーして config/config.local.php を作成してください。
 * config.local.php は .gitignore に含まれており、Git にコミットされません。
 * 
 * 本番環境では、このファイルの設定を必ず変更してください。
 */

return [
    /**
     * アプリケーション設定
     */
    'app' => [
        // 環境: 'development' または 'production'
        'environment' => 'production',
        
        // 本番環境ではbundle版のアセット（JS/CSS）を使用
        'use_bundled_assets' => true,
    ],

    /**
     * 管理画面設定
     */
    'admin' => [
        // ⚠️ 重要: 管理画面のパスを推測されにくいランダムな文字列に変更
        // 例: 'xK9mP2nQ7_admin', 'fehihfnFG__', 'cp_8xYz4Hn2'
        // 変更後は public/ 内のディレクトリ名も同じ名前に変更してください
        'path' => 'CHANGE_ME_' . bin2hex(random_bytes(8)),
        
        // 管理画面を有効化
        'enabled' => true,
    ],

    /**
     * データベース設定
     */
    'database' => [
        // ⚠️ 本番環境では MySQL または PostgreSQL を推奨
        'driver' => 'mysql',  // 'sqlite', 'mysql', 'postgresql'
        
        // MySQL設定（driver='mysql'の場合）
        'mysql' => [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => 3306,
            'database' => getenv('DB_NAME') ?: 'photo_site',
            'username' => getenv('DB_USER') ?: 'photo_user',
            // ⚠️ 重要: 強力なパスワードを設定
            'password' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        
        // PostgreSQL設定（driver='postgresql'の場合）
        'postgresql' => [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => 5432,
            'database' => getenv('DB_NAME') ?: 'photo_site',
            'username' => getenv('DB_USER') ?: 'photo_user',
            'password' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8',
            'schema' => 'public',
        ],
    ],

    /**
     * セキュリティ設定
     */
    'security' => [
        // HTTPS設定
        'https' => [
            // ⚠️ 重要: 本番環境では HTTPS を強制
            'force' => true,
            // Strict-Transport-Securityヘッダーを送信
            'hsts_enabled' => true,
            'hsts_max_age' => 31536000, // 1年
        ],

        // Content-Security-Policy設定
        'csp' => [
            // CSPを有効化（本番環境推奨）
            'enabled' => true,
            // 最初はレポートのみモードでテスト
            'report_only' => false,
        ],

        // セッション設定
        'session' => [
            'cookie_lifetime' => 0,
            'cookie_secure' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            // ⚠️ 重要: 本番環境では secure cookie を強制
            'force_secure_cookie' => true,
        ],

        // CORS設定（公開API用）
        'cors' => [
            'enabled' => true,
            // ⚠️ 重要: 本番環境では具体的なドメインを指定
            'allowed_origins' => [
                'https://yourdomain.com',
                'https://www.yourdomain.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'X-CSRF-Token'],
            // 認証情報（Cookie等）を含むリクエストを許可
            'allow_credentials' => true,
            'max_age' => 3600,
        ],

        // セキュリティログ設定
        'logging' => [
            'enabled' => true,
            'log_file' => __DIR__ . '/../logs/security.log',
            'sanitize' => true,
        ],
    ],

    /**
     * NSFWフィルター設定
     */
    'nsfw' => [
        // 年齢確認の有効期限（分単位）
        // デフォルト: 10080分 = 7日間
        'age_verification_minutes' => 10080,
        
        // 年齢確認が必要な最低年齢
        'minimum_age' => 18,
    ],
];
