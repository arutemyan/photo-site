# 管理画面ディレクトリ名のカスタマイズ

セキュリティ向上のため、管理画面のディレクトリ名を推測されにくい名前に変更できます。

## 設定方法

### 1. ディレクトリ名を決める

推測されにくいランダムな文字列を使用してください。

**例:**
- `fehihfnFG__`
- `xK9mP2nQ7_admin`
- `cp_8xYz4Hn2`
- `mng_x7Kp2Qw`

### 2. 設定ファイルを更新

`config/config.default.php` または `config/config.local.php` を編集：

```php
'admin' => [
    'path' => 'fehihfnFG__',  // ここを変更
],
```

### 3. ディレクトリ名を変更

```bash
cd public/
mv admin fehihfnFG__
```

### 4. .htaccess を更新（オプション）

Pretty URLを使用する場合は、`public/.htaccess`を更新：

```apache
# 変更前
RewriteRule ^admin$ admin/index.php [L]
RewriteRule ^admin/login$ admin/login.php [L]

# 変更後
RewriteRule ^fehihfnFG__$ fehihfnFG__/index.php [L]
RewriteRule ^fehihfnFG__/login$ fehihfnFG__/login.php [L]
```

**注意:** .htaccessを更新しない場合でも、直接URLでアクセスできます：
- `https://example.com/fehihfnFG__/index.php`
- `https://example.com/fehihfnFG__/login.php`

### 5. 動作確認

ブラウザで以下にアクセス：
- `https://your-domain.com/fehihfnFG__/` （Pretty URL）
- `https://your-domain.com/fehihfnFG__/index.php` （直接アクセス）
- `https://your-domain.com/fehihfnFG__/login.php` （ログインページ）

## セキュリティのベストプラクティス

### ✅ 推奨

1. **長さ:** 10文字以上
2. **文字種:** 大文字・小文字・数字・記号を混在
3. **予測不可能:** 辞書にない文字列
4. **定期変更:** 半年〜1年ごとに変更

### ❌ 避けるべき名前

- `admin` - デフォルト名
- `管理` - 日本語（問題が起きる可能性）
- `control` - 一般的な単語
- `dashboard` - 一般的な単語
- `manage` - 一般的な単語
- `backend` - 一般的な単語

### 生成方法

**Linux/Mac:**
```bash
# ランダムな12文字を生成
openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | head -c 12
```

**オンラインツール:**
- パスワード生成ツールを使用して長い文字列を生成
- 記号は`_`のみ使用を推奨（URLセーフ）

## トラブルシューティング

### 404エラーが出る

1. ディレクトリ名が正しいか確認
2. `config/config.php`の設定を確認
3. Webサーバーを再起動（必要な場合）

### ログインできない

1. ブラウザのキャッシュをクリア
2. 直接URLでアクセス: `/新しい名前/login.php`
3. セッションをクリア（ブラウザCookieを削除）

### JavaScriptエラーが出る

`public/admin/index.php`で`ADMIN_PATH`定数が正しく定義されているか確認：

```html
<script>
    const ADMIN_PATH = '<?= admin_path() ?>';
</script>
```

## 復元方法

元の`admin`に戻す場合：

```bash
# 1. ディレクトリ名を戻す
cd public/
mv fehihfnFG__ admin

# 2. 設定を戻す
# config/config.local.php を編集
'admin' => [
    'path' => 'admin',
],

# 3. .htaccess を元に戻す（変更していた場合）
```

## 関連ファイル

- `config/config.default.php` - デフォルト設定
- `config/config.local.php` - ローカル設定（推奨）
- `src/Utils/PathHelper.php` - パスヘルパークラス
- `src/Utils/path_helpers.php` - グローバル関数
- `public/.htaccess` - URL Rewriteルール
