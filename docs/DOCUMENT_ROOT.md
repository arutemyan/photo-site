# DocumentRoot設定ガイド

本番環境では、セキュリティとベストプラクティスのため、DocumentRootを`public/`ディレクトリに設定することを強く推奨します。

## なぜDocumentRootをpublic/に設定するのか

### セキュリティ上の理由

1. **ソースコード保護**
   - `src/`, `vendor/`, `config/`などがWeb経由でアクセス不可能
   - 設定ファイルや機密情報の漏洩を防ぐ

2. **攻撃対象の削減**
   - 公開するファイルを最小限に限定
   - composerの依存関係が直接アクセスされない

3. **セキュリティヘッダー**
   - `public/.htaccess`でセキュリティヘッダーを一元管理

## Apache設定

### バーチャルホスト設定

```apache
<VirtualHost *:80>
    ServerName your-domain.com

    # DocumentRootをpublicディレクトリに設定
    DocumentRoot /var/www/photo-site/public

    <Directory /var/www/photo-site/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # .htaccessを有効化
        <IfModule mod_rewrite.c>
            RewriteEngine On
        </IfModule>
    </Directory>

    # src/, vendor/, config/へのアクセスを拒否（念のため）
    <DirectoryMatch "^/var/www/photo-site/(src|vendor|config|data|cache|logs)">
        Require all denied
    </DirectoryMatch>

    ErrorLog ${APACHE_LOG_DIR}/photo-site-error.log
    CustomLog ${APACHE_LOG_DIR}/photo-site-access.log combined
</VirtualHost>
```

### HTTPS設定（推奨）

```apache
<VirtualHost *:443>
    ServerName your-domain.com

    DocumentRoot /var/www/photo-site/public

    # SSL証明書
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/your-domain.crt
    SSLCertificateKeyFile /etc/ssl/private/your-domain.key
    SSLCertificateChainFile /etc/ssl/certs/your-domain-chain.crt

    <Directory /var/www/photo-site/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <DirectoryMatch "^/var/www/photo-site/(src|vendor|config|data|cache|logs)">
        Require all denied
    </DirectoryMatch>

    # セキュリティヘッダー
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"

    ErrorLog ${APACHE_LOG_DIR}/photo-site-ssl-error.log
    CustomLog ${APACHE_LOG_DIR}/photo-site-ssl-access.log combined
</VirtualHost>
```

### 設定を適用

```bash
# 設定ファイルを作成
sudo nano /etc/apache2/sites-available/photo-site.conf

# サイトを有効化
sudo a2ensite photo-site.conf

# 必要なモジュールを有効化
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl

# Apache設定をテスト
sudo apache2ctl configtest

# Apache再起動
sudo systemctl restart apache2
```

## Nginx設定

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;

    # HTTPSへリダイレクト
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com;

    # DocumentRoot
    root /var/www/photo-site/public;
    index index.php index.html;

    # SSL証明書
    ssl_certificate /etc/ssl/certs/your-domain.crt;
    ssl_certificate_key /etc/ssl/private/your-domain.key;

    # SSL設定
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # セキュリティヘッダー
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # src/, vendor/, config/へのアクセスを拒否
    location ~ ^/(src|vendor|config|data|cache|logs)/ {
        deny all;
        return 404;
    }

    # PHPファイルの処理
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    # 静的ファイルのキャッシュ
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|webp|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # デフォルトのlocation
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ログ
    access_log /var/log/nginx/photo-site-access.log;
    error_log /var/log/nginx/photo-site-error.log;
}
```

### Nginx設定を適用

```bash
# 設定ファイルを作成
sudo nano /etc/nginx/sites-available/photo-site

# シンボリックリンクを作成
sudo ln -s /etc/nginx/sites-available/photo-site /etc/nginx/sites-enabled/

# 設定をテスト
sudo nginx -t

# Nginx再起動
sudo systemctl restart nginx
```

## 共有ホスティング環境

DocumentRoot変更ができない環境の場合：

### .htaccessでリダイレクト（代替案）

プロジェクトルートに`.htaccess`を作成：

```apache
# すべてのリクエストをpublic/へリダイレクト
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

**注意:** この方法は推奨されません。可能であればDocumentRootを変更してください。

## 確認方法

### 正しく設定されているか確認

1. **ブラウザでアクセス**
   ```
   https://your-domain.com/
   ```
   → トップページが表示される

2. **保護されているか確認**
   ```
   https://your-domain.com/../composer.json
   https://your-domain.com/../config/config.php
   ```
   → 404 または 403 エラーが返される

3. **管理画面へのアクセス**
   ```
   https://your-domain.com/admin/
   または
   https://your-domain.com/【カスタマイズした名前】/
   ```
   → ログインページが表示される

## トラブルシューティング

### 500 Internal Server Error

1. `.htaccess`の構文エラー
   ```bash
   sudo apache2ctl configtest
   ```

2. mod_rewriteが無効
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

3. AllowOverrideが正しく設定されていない
   ```apache
   AllowOverride All  # Noneではなく
   ```

### 403 Forbidden

1. ディレクトリパーミッション
   ```bash
   chmod 755 /var/www/photo-site/public
   ```

2. 所有者設定
   ```bash
   sudo chown -R www-data:www-data /var/www/photo-site
   ```

### 画像が表示されない

1. uploadsディレクトリのパーミッション
   ```bash
   chmod 777 /var/www/photo-site/public/uploads
   chmod 777 /var/www/photo-site/public/uploads/images
   chmod 777 /var/www/photo-site/public/uploads/thumbs
   ```

## 関連ドキュメント

- [ADMIN_PATH.md](./ADMIN_PATH.md) - 管理画面ディレクトリ名のカスタマイズ
- [SECURITY_AUDIT_ADMIN.md](../design/SECURITY_AUDIT_ADMIN.md) - セキュリティ監査（設計資料）
