# Phase 1 テストガイド

**目的**: CSP Phase 1 実装の動作確認

---

## 前提条件

Phase 1 実装により、以下が変更されました：
- eval() の削除
- inline script の削除
- nonce-based CSP の導入

---

## テスト環境のセットアップ

### 1. CSP を Report-Only モードで有効化

`config/config.local.php` を作成または編集:

```php
<?php
return [
    'csp' => [
        'enabled' => true,
        'report_only' => true,  // まずはレポートのみ
    ],
];
```

### 2. ブラウザの開発者ツールを開く

- Chrome/Edge: F12 または Ctrl+Shift+I
- Firefox: F12 または Ctrl+Shift+I
- Safari: Cmd+Option+I

**Console タブを開いて CSP violation を監視します。**

---

## テストケース

### ケース 1: 管理画面ログイン ✅

**手順**:
1. `/admin/login.php` にアクセス
2. ユーザー名とパスワードを入力
3. ログインボタンをクリック

**期待結果**:
- ✅ ログインが成功する
- ✅ `/admin/index.php` にリダイレクトされる
- ✅ Console に CSP violation エラーがない

**確認ポイント**:
- CSRF トークンが meta tag から正しく読み込まれている
- ADMIN_PATH が data 属性から正しく読み込まれている

---

### ケース 2: 投稿一覧の表示 ✅

**手順**:
1. 管理画面にログイン
2. 「投稿（シングル）」タブを開く

**期待結果**:
- ✅ 投稿一覧が表示される
- ✅ サムネイル画像が表示される
- ✅ Console に CSP violation エラーがない

**確認ポイント**:
- `loadPosts()` 関数が正しく動作している（eval() 削除の確認）
- API リクエストが正常に完了している

---

### ケース 3: 投稿の編集 ✅

**手順**:
1. 投稿一覧から任意の投稿を選択
2. 「編集」ボタンをクリック
3. タイトルまたは説明を変更
4. 「保存」ボタンをクリック

**期待結果**:
- ✅ 編集モーダルが開く
- ✅ 変更が保存される
- ✅ 投稿一覧が更新される
- ✅ Console に CSP violation エラーがない

**確認ポイント**:
- `editPost()`, `savePost()` 関数が動作している（function map の確認）
- CSRF トークンが正しく送信されている

---

### ケース 4: 新規投稿の作成 ✅

**手順**:
1. 「新規投稿」セクションに移動
2. タイトル、説明を入力
3. 画像をアップロード
4. タグを入力（カンマ区切り）
5. 「投稿」ボタンをクリック

**期待結果**:
- ✅ 画像プレビューが表示される
- ✅ 投稿が作成される
- ✅ 投稿一覧に新しい投稿が表示される
- ✅ Console に CSP violation エラーがない

---

### ケース 5: 一括アップロード ✅

**手順**:
1. 「一括アップロード」セクションに移動
2. 複数の画像ファイルを選択
3. 「一括アップロード開始」をクリック

**期待結果**:
- ✅ アップロード進行状況が表示される
- ✅ すべての画像がアップロードされる
- ✅ Console に CSP violation エラーがない

---

### ケース 6: テーマ設定 ✅

**手順**:
1. 「テーマ設定」タブを開く
2. プライマリーカラーを変更
3. 「保存」ボタンをクリック

**期待結果**:
- ✅ カラーピッカーが正しく動作する
- ✅ プレビューが更新される
- ✅ 設定が保存される
- ✅ Console に CSP violation エラーがない

**確認ポイント**:
- `loadThemeSettings()` 関数が動作している

---

### ケース 7: ペイント機能 ✅

**手順**:
1. `/admin/paint/index.php` にアクセス
2. ブラシツールで描画
3. レイヤーを追加
4. 保存ボタンをクリック

**期待結果**:
- ✅ ペイント UI が正しく表示される
- ✅ 描画が正常に動作する
- ✅ Worker (タイムラプス記録) が動作する
- ✅ 保存が成功する
- ✅ Console に CSP violation エラーがない

**確認ポイント**:
- `paint-init.js` が正しくロードされている
- `CSRF_TOKEN` と `PAINT_BASE_URL` が meta tag / data 属性から読み込まれている
- Worker constructor shim が動作している
- Fetch wrapper が動作している（API パス解決）

**重要**: ペイント機能は最も複雑な変更があった箇所なので、重点的にテストしてください。

---

### ケース 8: グループ投稿 ✅

**手順**:
1. 「投稿（グループ）」タブを開く
2. グループ投稿一覧を確認
3. 任意のグループを編集

**期待結果**:
- ✅ グループ一覧が表示される
- ✅ 編集が正常に動作する
- ✅ Console に CSP violation エラーがない

---

## CSP Violation の確認方法

### Console での確認

CSP violation が発生すると、以下のようなエラーが表示されます：

```
Refused to execute inline script because it violates the following 
Content Security Policy directive: "script-src 'self' 'nonce-XXXXX' ...". 
Either the 'unsafe-inline' keyword, a hash ('sha256-...'), or a nonce 
('nonce-...') is required to enable inline execution.
```

**もし CSP violation が出た場合**:
1. エラーメッセージをコピー
2. どのページで発生したかメモ
3. 再現手順をメモ
4. Issue で報告

---

## Network タブでの確認

### API リクエストの確認

1. Network タブを開く
2. 管理画面で操作を行う
3. API リクエストを確認

**確認ポイント**:
- `/admin/api/config.php` - 設定値取得 API（新規）
- `/admin/api/posts.php` - 投稿関連 API
- `/admin/paint/api/*.php` - ペイント API

**期待結果**:
- ✅ すべての API リクエストが 200 OK
- ✅ CSRF トークンが正しく送信されている
- ✅ レスポンスが正常に受信されている

---

## 本番環境への適用前の最終チェック

### ステップ 1: Report-Only モードで 1週間運用

```php
'csp' => [
    'enabled' => true,
    'report_only' => true,
],
```

- Console を定期的に確認
- CSP violation が発生していないことを確認
- ユーザーからの問題報告がないことを確認

### ステップ 2: Enforce モードへ移行

問題がなければ、Enforce モードに変更:

```php
'csp' => [
    'enabled' => true,
    'report_only' => false,
],
```

### ステップ 3: 監視

- 最初の数日間は Console を確認
- ユーザーからの問題報告に注意
- 問題があれば即座に Report-Only モードに戻す

---

## ロールバック手順

問題が発生した場合:

### 1. CSP を無効化

```php
'csp' => [
    'enabled' => false,
],
```

### 2. サーバーをリロード

```bash
# Apache の場合
sudo systemctl reload apache2

# Nginx + PHP-FPM の場合
sudo systemctl reload nginx
sudo systemctl reload php-fpm
```

### 3. 問題を報告

- どのページで問題が発生したか
- どの操作で問題が発生したか
- Console のエラーメッセージ
- ブラウザの種類とバージョン

---

## テスト完了チェックリスト

- [ ] ログイン・ログアウト
- [ ] 投稿一覧の表示
- [ ] 投稿の作成・編集・削除
- [ ] 画像のアップロード
- [ ] 一括アップロード
- [ ] テーマ設定の変更
- [ ] サイト設定の変更
- [ ] ペイント機能（描画、レイヤー、保存）
- [ ] グループ投稿の表示・編集
- [ ] Console に CSP violation エラーがないことを確認
- [ ] Network タブで API リクエストが正常に完了していることを確認

---

## トラブルシューティング

### Q: Console に CSP violation が出る

**A**: 以下を確認:
1. `config.local.php` で CSP が有効化されているか
2. Report-Only モードになっているか
3. エラーメッセージの内容を確認
4. Issue で報告

### Q: 管理画面が真っ白になる

**A**: 
1. Console のエラーを確認
2. PHP のエラーログを確認
3. CSP を無効化してみる
4. ブラウザのキャッシュをクリア

### Q: ペイント機能が動かない

**A**:
1. Console で JavaScript エラーを確認
2. `paint-init.js` がロードされているか確認
3. Worker が正しく動作しているか確認
4. API リクエストが成功しているか確認

---

## まとめ

Phase 1 実装により、以下が達成されました：

- ✅ eval() の削除（unsafe-eval 不要）
- ✅ inline script の削除（unsafe-inline 不要）
- ✅ nonce-based CSP の導入
- ✅ XSS 攻撃リスクの大幅な軽減

**次のステップ**: 本ガイドに従ってテストを実施し、問題がなければ本番環境に適用してください。
