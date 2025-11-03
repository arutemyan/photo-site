<?php

declare(strict_types=1);

/**
 * 統合設定ファイル (Default)
 *
 * アプリケーション全体のデフォルト設定
 * ローカル環境固有の設定は config.local.php で上書きしてください
 */

return [
    /**
     * 管理画面設定
     */
    'admin' => [
        // 管理画面のディレクトリ名
        // セキュリティ向上のため、推測されにくいランダムな名前に変更することを推奨
        // 例: 'fehihfnFG__', 'xK9mP2nQ7_admin', 'cp_8xYz4Hn2'
        // 変更後は public/ 内のディレクトリ名も同じ名前に変更してください
        'path' => 'admin',

        // 管理画面の完全なURL（使用されていない場合は自動生成）
        // 'url' => '/admin',
    ],

    /**
     * データベース設定
     */
    'database' => [
        // データベースタイプ: 'sqlite', 'mysql', 'postgresql'
        'driver' => 'sqlite',

        // SQLite設定（driver='sqlite'の場合）
        'sqlite' => [
            // メインデータベース（ギャラリーコンテンツ）
            'gallery' => [
                'path' => __DIR__ . '/../data/gallery.db',
                'description' => 'Main gallery content (posts, users, themes, settings)',
            ],
            // カウンターデータベース（閲覧数など）
            'counters' => [
                'path' => __DIR__ . '/../data/counters.db',
                'description' => 'View counts and other frequently updated counters',
            ],
            // アクセスログデータベース（オプション）
            'access_logs' => [
                'path' => __DIR__ . '/../data/access_logs.db',
                'description' => 'Access logs (IP, UserAgent, Referer, etc.)',
                'enabled' => false,
            ],
        ],

        // MySQL設定（driver='mysql'の場合）
        'mysql' => [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'photo_site',
            'username' => 'photo_user',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        // PostgreSQL設定（driver='postgresql'の場合）
        'postgresql' => [
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'photo_site',
            'username' => 'photo_user',
            'password' => '',
            'charset' => 'utf8',
            'schema' => 'public',
        ],

        // データベースディレクトリのパーミッション（SQLiteのみ）
        'directory_permission' => 0755,

    // 自動的に接続時にマイグレーションを実行するか
    // CIやテストで明示的にマイグレーションを実行したい場合は false に設定してください
    'run_migrations_on_connect' => false,

        // PDO接続オプション
        'pdo_options' => [
            'errmode' => 'exception',
            'fetch_mode' => 'assoc',
            'emulate_prepares' => false,
        ],
    ],

    /**
     * キャッシュ設定
     */
    'cache' => [
        // キャッシュディレクトリのパス
        'cache_dir' => __DIR__ . '/../cache',
        // キャッシュの有効期限（秒、0=無期限）
        'default_ttl' => 0,
        // キャッシュの有効化/無効化
        'enabled' => true,
        // ディレクトリのパーミッション
        'dir_permissions' => 0755,
        // ファイルのパーミッション
        'file_permissions' => 0644,
    ],

    /**
     * NSFW（センシティブコンテンツ）設定
     */
    'nsfw' => [
        // 設定バージョン（変更時にインクリメント）
        // この値が変わると、既存の年齢確認が無効化されます
        'config_version' => 1,

        // 年齢確認の有効期限（分単位）
        // デフォルト: 10080分 = 7日間
        'age_verification_minutes' => 10080,

        // 年齢確認が必要な最低年齢
        'minimum_age' => 18,

	// NSFWフィルター画像設定（すりガラス効果）
        // ガウシアンブラー + 明るい半透明オーバーレイで透明感のあるすりガラスを表現
        'filter_settings' => [
            'blur_strength' => 150,      // ぼかし強度
            'brightness' => -10,         // 明度調整（-100 ~ 100）
            'contrast' => -5,          // コントラスト調整（-100 ~ 100）
            'white_overlay' => 5,      // 白オーバーレイの不透明度（0-100）
            'quality' => 70,            // WebP品質（0-100）
        ],
    ],

    /**
     * セキュリティ設定
     */
    'security' => [
        // HTTPS設定
        'https' => [
            // HTTPSを強制するかどうか（本番環境ではtrueを推奨）
            'force' => false,
            // Strict-Transport-Securityヘッダーを送信するかどうか
            'hsts_enabled' => false,
            // HSTSの有効期間（秒）
            'hsts_max_age' => 31536000, // 1年
        ],

        // Content-Security-Policy設定
        'csp' => [
            // CSPを有効にするかどうか
            'enabled' => false,
            // CSPをレポートのみモードで実行
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
        ],

        // CSRF保護設定
        'csrf' => [
            'token_length' => 32,
            'token_name' => 'csrf_token',
        ],

        // パス設定
        'paths' => [
            // データベースディレクトリ
            'data_dir' => __DIR__ . '/../data',
            // ログディレクトリ
            'log_dir' => __DIR__ . '/../logs',
            // レート制限データディレクトリ
            'rate_limit_dir' => __DIR__ . '/../data/rate-limits',
        ],

        // セキュリティログ設定
        'logging' => [
            'enabled' => true,
            'log_file' => __DIR__ . '/../logs/security.log',
            // ログに機密情報（パスワード、トークンなど）を含めない
            'sanitize' => true,
        ],
    ],
    // サムネイル設定
    'thumbnail' => [
	'width' => 600,
	'height' => 600,
	'quality' => 70,
    ],
];
