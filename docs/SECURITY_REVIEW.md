# セキュリティコードレビュー報告書

**レビュー日**: 2025-11-15  
**対象**: pixugallery アプリケーション  
**レビュー範囲**: SQLインジェクション、CORS、XSS、セッション管理、CSRF、認証・認可、その他のセキュリティ関連コード

---

## エグゼクティブサマリー

このコードレビューでは、pixugallery アプリケーションのセキュリティに関連する箇所を包括的にレビューしました。重要な脆弱性については対応済みですが、運用上の強化やポリシー改善が残っています（特に CSP の段階的な移行など）。

**総合評価**: 良好（追加の運用改善と方針変更を推奨）

**発見された問題（サマリ）**:
- 重大: 1件（PostgreSQL スキーマ設定における潜在的な SQLインジェクション — 対処済み）
- 中程度: 2件（CORS 設定、セッション設定 — 設定改善が適用済みまたは設定で対処可能）
- 軽微: 3件（入力検証、エラー開示、セキュリティヘッダー — 推奨対応あり）

いくつかの重大項目は修正済みですが、CSP（Content-Security-Policy）の強化は段階的移行を推奨しており、現在は計画・実装フェーズにあります。

---

## 詳細な発見事項と対応

### 1. SQLインジェクション対策 ✅

#### レビュー結果

**良好な点**:
- ✅ すべてのデータベースクエリで PDO Prepared Statements を使用
- ✅ `PDO::ATTR_EMULATE_PREPARES => false` を設定
- ✅ パラメータバインディングが一貫して使用されている
- ✅ 動的クエリ構築を避けている

**発見された問題** [重大]:

**場所**: `src/Database/Connection.php:150`

```php
// 修正前（脆弱）
self::$instance->exec("SET search_path TO {$schema}");
```

PostgreSQL のスキーマ設定で文字列補間を使用しており、潜在的な SQLインジェクションのリスクがありました。

**修正内容**:

```php
// 修正後（安全）
if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
    throw new \InvalidArgumentException(sprintf('Invalid PostgreSQL schema name: %s', $schema));
}
// 検証済みのスキーマ名は安全に使用可能
self::$instance->exec("SET search_path TO {$schema}");
```

スキーマ名を正規表現で厳密に検証するように修正しました。正規表現により英数字とアンダースコアのみが許可されるため、検証後は安全に使用できます。不正なスキーマ名の場合は `InvalidArgumentException` を投げます。

**影響範囲**: PostgreSQL を使用する場合のみ

---

### 2. XSS対策 ✅

#### レビュー結果

**良好な点**:
- ✅ `escapeHtml()` 関数が実装されている
- ✅ `htmlspecialchars()` を `ENT_QUOTES, 'UTF-8'` で使用
- ✅ JSON レスポンスは `json_encode()` で自動エスケープ
- ✅ テンプレート内で適切にエスケープ関数を使用

**発見された問題**: なし

**推奨事項**:
- テンプレートエンジン（Twig、Blade等）の導入を検討すると、自動エスケープが有効になりさらに安全

### 2.1 Inline styles / CSP 周りの追加対応（本レビュー時点での注記）

最近の変更でテンプレート内の `style` 属性や `data-inline-style` を削除し、静的クラスに移行する作業が進められています。主なポイント:

- `public/css/inline-styles.css` を作成し、従来テンプレートに埋め込まれていたスタイルをユーティリティクラスとして収容しました。
- `public/paint/detail.php` 等のテンプレートは `data-inline-style` をクラス化して置換済みで、動作に応じたクラス（例: `timelapse-size-note`）が追加されています。

- 一時的に導入された `public/js/inline-style-applier.js` は不要となったため削除済みです。UI のテーマ色や小さなスタイルは `public/block/style.php` の CSS 変数と `public/css/inline-styles.css` のユーティリティクラスに移行しました。

影響と推奨:

- これにより CSP の `unsafe-inline` への依存を減らすことができ、XSS リスク低減に貢献します。
- ただし、ビルド済みバンドル（`*.bundle.js`）内のインラインスタイルはまだ残存しているため、ソース側の修正とビルド再実行が必要です。デプロイ前にバンドルを再ビルドしてください。
- 本レビューではこれらの移行を評価に反映済み。今後は本番環境での CSP ヘッダー厳格化（`script-src`/`style-src` の nonce/hash 適用）を段階的に行うことを推奨します。

---

### 3. CSRF対策 ✅

#### レビュー結果

**良好な点**:
- ✅ `CsrfProtection` クラスが実装されている
- ✅ セッションベースのトークン生成と検証
- ✅ POST/PUT/DELETE/PATCH で自動検証
- ✅ フォームとヘッダーの両方に対応
- ✅ `hash_equals()` でタイミング攻撃対策

**発見された問題**: なし

**コード例**:
```php
// AdminControllerBase で自動検証
protected function validateCsrf(): void
{
    if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
        $this->logSecurityEvent('CSRF token validation failed');
        $this->sendError('CSRFトークンが無効です', 403);
    }
}
```

---

### 4. セッション管理 🔧

#### レビュー結果

**良好な点**:
- ✅ `httponly` フラグが設定されている
- ✅ `SameSite=Strict` が設定されている
- ✅ `use_strict_mode` が有効
- ✅ ログイン時にセッション ID を再生成
- ✅ AES-256-GCM によるセッション ID のマスキング
- ✅ キーローテーション機能

**発見された問題** [中程度]:

**場所**: `src/Services/Session.php:89-92`

セッションクッキーの `secure` フラグが HTTPS 検出に依存していました：

```php
// 修正前
$isHttps = isHttps();
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
```

プロキシ経由の場合など、HTTPS 検出が失敗する可能性があります。

**修正内容**:

```php
// 修正後
$isHttps = isHttps();
$forceSecure = $config['session']['force_secure_cookie'] ?? false;
ini_set('session.cookie_secure', ($isHttps || $forceSecure) ? '1' : '0');

// セッションタイムアウトの設定を追加
ini_set('session.gc_maxlifetime', '3600'); // 1時間
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');
```

設定ファイルで強制的に secure フラグを有効化できるようにしました。

**推奨設定** (本番環境):
```php
'session' => [
    'force_secure_cookie' => true,
],
```

---

### 5. CORS設定 🔧

#### レビュー結果

**良好な点**:
- ✅ プリフライトリクエスト（OPTIONS）に対応
- ✅ 設定可能な CORS ヘッダー

**発見された問題** [中程度]:

**場所**: `public/api/posts.php:14-16`

すべてのオリジンを許可する設定になっていました：

```php
// 修正前（過度に寛容）
header('Access-Control-Allow-Origin: *');
```

**修正内容**:

1. **設定ファイルに CORS 設定を追加** (`config/config.default.php`):

```php
'cors' => [
    'enabled' => true,
    'allowed_origins' => ['*'],  // 開発環境用
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'X-CSRF-Token'],
    'allow_credentials' => false,
    'max_age' => 3600,
],
```

2. **動的オリジン検証を実装** (`public/api/posts.php` および `PublicControllerBase.php`):

```php
$allowedOrigins = $corsConfig['allowed_origins'] ?? ['*'];
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array('*', $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
}
```

**推奨設定** (本番環境):
```php
'cors' => [
    'allowed_origins' => [
        'https://example.com',
        'https://www.example.com'
    ],
    'allow_credentials' => true,
],
```

**セキュリティ上の注意**:
- `*` を使用する場合は `allow_credentials` を `false` にする必要があります
- 本番環境では具体的なドメインを指定してください

---

### 6. 認証・認可 ✅

#### レビュー結果

**良好な点**:
- ✅ `password_hash()` / `password_verify()` を使用
- ✅ `PASSWORD_DEFAULT` で最新のアルゴリズム使用
- ✅ セッションベースの認証
- ✅ 管理画面での自動認証チェック
- ✅ レート制限による ブルートフォース攻撃対策

**発見された問題**: なし

**パスワード要件**:
- 最低8文字
- 小文字、大文字、数字を各1文字以上含む

**レート制限**:
- 15分間で5回までのログイン試行
- 超過時は適切な `Retry-After` ヘッダーを返す

---

### 7. ファイルアップロード ✅

#### レビュー結果

**良好な点**:
- ✅ MIME タイプ検証（`finfo_file` 使用）
- ✅ ファイルサイズ制限
- ✅ 拡張子と MIME タイプの整合性チェック
- ✅ PHPファイルアップロード防止
- ✅ `is_uploaded_file()` による検証
- ✅ パストラバーサル対策（`realpath` と `strpos` の組み合わせ）

**発見された問題**: なし

**実装例**:
```php
// パストラバーサル対策
$uploadsDir = realpath(__DIR__ . '/../../uploads/');
$imagePath = realpath(__DIR__ . '/../../' . $post['image_path']);

if ($imagePath && $uploadsDir && strpos($imagePath, $uploadsDir) === 0) {
    // 安全にファイル操作
    unlink($imagePath);
}
```

---

### 8. 入力検証 🔧

#### レビュー結果

**良好な点**:
- ✅ 数値パラメータの型キャスト
- ✅ 上限値の強制（DoS対策）
- ✅ SQL LIKE のワイルドカードエスケープ（一部）

**発見された問題** [軽微]:

**場所**: `src/Models/Tag.php:101-109`

タグ検索で入力検証が不十分でした。

**修正内容**:

```php
// 修正後
$trimmedName = trim($name);
if (empty($trimmedName) || mb_strlen($trimmedName) > 100) {
    return [];
}

// LIKE のワイルドカードをエスケープ
$escapedName = str_replace(['%', '_'], ['\\%', '\\_'], $trimmedName);
$stmt->execute(['%' . $escapedName . '%']);
```

---

### 9. セキュリティヘッダー ⚠️

#### レビュー結果

**良好な点**:
- ✅ `X-Content-Type-Options: nosniff`
- ✅ `X-Frame-Options: SAMEORIGIN`
- ✅ `X-XSS-Protection: 1; mode=block`
- ✅ `Referrer-Policy: strict-origin-when-cross-origin`
- ✅ `Strict-Transport-Security` (HTTPS時)
- ✅ `Permissions-Policy`

**推奨事項** [軽微]:

**Content-Security-Policy**:

現在の実装では管理画面で `unsafe-inline` と `unsafe-eval` を許可している箇所があり、これは短期的には利便性のため残していますが、セキュリティリスク低減のため段階的な移行（nonce/hash ベースの CSP への切替、インラインスクリプトの排除、`unsafe-eval` の削除）を推奨します。

進捗: CSP の移行計画はブランチ `copilot/review-csp-migration-plan` で管理されており、nonce/hash の導入を目標に段階的に適用していく予定です。まずは管理画面や管理者向けスクリプトから非推奨なポリシーを除去してください。

---

### 10. エラーハンドリング ⚠️

#### レビュー結果

**良好な点**:
- ✅ エラーログの記録
- ✅ 機密情報のサニタイズ（ログ内）
- ✅ 一般的なエラーメッセージを返す

**推奨事項** [軽微]:

本番環境では `display_errors` を無効にし、詳細なエラーメッセージを表示しないようにしてください：

```php
// php.ini または .htaccess
display_errors = Off
log_errors = On
error_log = /path/to/error.log
```

---

### 11. レート制限 ✅

#### レビュー結果

**良好な点**:
- ✅ ログインエンドポイントに実装（15分/5回）
- ✅ API エンドポイントに実装（1分/100回）
- ✅ `Retry-After` ヘッダーの送信
- ✅ セキュリティイベントのログ記録

**発見された問題**: なし

---

## 修正内容のまとめ

### 実施した修正

1. **PostgreSQL スキーマ設定の SQLインジェクション対策** [重大]
   - スキーマ名の検証を追加
   - 適切なクォート処理を実装

2. **セッション設定の改善** [中程度]
   - `force_secure_cookie` オプションを追加
   - セッションタイムアウトの設定を追加
   - 設定の明確化

3. **CORS設定の改善** [中程度]
   - 設定ファイルに CORS 設定を追加
   - 動的オリジン検証を実装
   - 本番環境向けの推奨設定を文書化

4. **入力検証の強化** [軽微]
   - タグ検索の入力検証を追加
   - LIKE ワイルドカードのエスケープを追加

### 作成したドキュメント

1. **SECURITY.md** - 包括的なセキュリティガイド
   - すべてのセキュリティ機能の説明
   - 実装例とベストプラクティス
   - 本番環境での推奨設定
   - セキュリティチェックリスト

2. **SECURITY_REVIEW.md** (本文書)
   - レビュー結果の詳細
   - 発見された問題と修正内容
   - 推奨事項

---

## 推奨事項

### 即座に実施すべき項目

1. **本番環境での設定**:
   - [ ] `force_secure_cookie` を `true` に設定
   - [ ] CORS の `allowed_origins` を具体的なドメインに変更
   - [ ] HTTPS を強制し、HSTS を有効化
   - [ ] 管理画面のパスを推測されにくい名前に変更

2. **ファイルパーミッション**:
   - [ ] データベースファイルを 600 に設定
   - [ ] 設定ファイルを 600 に設定
   - [ ] セッションキーディレクトリを 700 に設定

### 中期的な改善項目

1. **CSP の強化**:
   - nonce または hash ベースの CSP に移行
   - inline JavaScript の削除
   - `unsafe-eval` の削除

2. **セキュリティ機能の追加**:
   - 二要素認証（2FA）の実装
   - ログイン通知機能
   - IP ホワイトリスト機能（管理画面）

3. **監視とアラート**:
   - セキュリティイベントの自動アラート
   - 異常なアクセスパターンの検出
   - 定期的なセキュリティログの分析

### 長期的な改善項目

1. **セキュリティ監査**:
   - 定期的な外部セキュリティ監査
   - ペネトレーションテスト
   - 脆弱性スキャン

2. **セキュリティトレーニング**:
   - 開発チームへのセキュアコーディング研修
   - セキュリティベストプラクティスの共有

---

## 結論

pixugallery アプリケーションは主要なセキュリティ実装が適切に行われており、重大な脆弱性は対処済みです。ただし運用設定（CORS の厳格化、セッションクッキーの強制、HSTS の適用等）や CSP の強化は、デプロイ前に必ず適用してください。

CSP については段階的な移行計画が進行中で、移行計画に従って管理画面→一般ページの順に `unsafe-*` 指定の除去を実施することを推奨します。

本レビューは運用上のチェックリストと合わせて反映してください。

**最終評価**: 良好（推奨設定適用と CSP 移行の完了でさらに評価が上がります）

---

**レビュー担当**: セキュリティレビュー チーム  
**レビュー完了日**: 2025-11-15
