# JavaScript Build & Minification

このプロジェクトでは、JavaScriptのモジュール化とminificationに **esbuild** を使用しています。

## 📦 セットアップ

### 1. Node.jsのインストール

Node.js 16以上が必要です。

```bash
# Ubuntuの場合
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# macOSの場合
brew install node
```

### 2. pnpmのインストール（推奨）

```bash
# npmでpnpmをインストール
npm install -g pnpm

# または corepack を使用（Node.js 16.13+）
corepack enable
corepack prepare pnpm@latest --activate
```

### 3. 依存関係のインストール

```bash
pnpm install
```

## 🔨 ビルドコマンド

### 開発用ビルド（ソースマップ付き）

```bash
pnpm build
```

### 本番用ビルド（minify + 最適化）

```bash
pnpm build:prod
```

### ファイル監視モード（自動ビルド）

```bash
pnpm watch
```

## 🚀 リリース作成

リリース用のパッケージを作成するには：

```bash
./create-release.sh [VERSION]
```

このスクリプトは以下を実行します：
1. JavaScriptを本番用にビルド（minify）
2. Git archiveでリリースパッケージを作成
3. `releases/` ディレクトリにtar.gzファイルを出力

## 📁 ビルド対象ファイル

以下のJavaScriptファイルがbundle化されます：

- `public/admin/paint/js/paint.js` → `paint.bundle.js`
- `public/admin/js/admin.js` → `admin.bundle.js`
- `public/res/js/main.js` → `main.bundle.js`
- `public/res/js/detail.js` → `detail.bundle.js`
- `public/paint/js/gallery.js` → `gallery.bundle.js`
- `public/paint/js/detail.js` → `detail.bundle.js`
- `public/paint/js/timelapse_player.js` → `timelapse_player.bundle.js`

また、以下のCSSがbundle/最小化対象になります（本番でminifyされます）：

- `public/res/css/main.css` → `public/res/css/main.bundle.css`
- `public/res/css/admin.css` → `public/res/css/admin.bundle.css`
- `public/admin/paint/css/style.css` → `public/admin/paint/css/style.bundle.css`
- `public/paint/css/gallery.css` → `public/paint/css/gallery.bundle.css`
- `public/paint/css/detail.css` → `public/paint/css/detail.bundle.css`

## ⚙️ 環境切り替え

`config/config.php` で環境を切り替えます：

```php
return [
    'app' => [
        'environment' => 'production', // または 'development'
        'use_bundled_assets' => true,  // bundle版のアセットを使用
    ],
    // ...
];
```

### 開発環境（デフォルト）
- ES6 modulesをそのまま使用
- ブラウザのdevtoolsでデバッグ可能
- ソースマップ不要

### 本番環境
- minifyされたbundle版を使用
- ファイルサイズ削減
- HTTP/2で1ファイルの方が効率的

## 🔍 動作確認

### ビルド後のファイルサイズ確認

```bash
ls -lh public/admin/paint/js/*.bundle.js
```

### 本番設定でのテスト

1. `config/config.local.php` を編集：
```php
<?php
return [
    'app' => [
        'use_bundled_assets' => true,
    ],
];
```

2. ブラウザで管理画面にアクセス
3. DevToolsで `paint.bundle.js` が読み込まれているか確認

## 📊 ビルドサイズの目安

| ファイル | 開発版 | 本番版 (minify) | 削減率 |
|---------|--------|----------------|--------|
| paint.js | ~150KB | ~60KB | 60% |
| admin.js | ~30KB | ~12KB | 60% |

## 🛠️ トラブルシューティング

### Node.jsがない環境でリリース作成

```bash
# ビルドをスキップしてリリース作成
SKIP_BUILD=true ./create-release.sh
```

### bundleファイルが古い

```bash
# キャッシュをクリアして再ビルド
rm -f public/**/*.bundle.js
pnpm build:prod
```

### モジュールが見つからないエラー

```bash
# 依存関係を再インストール
rm -rf node_modules pnpm-lock.yaml
pnpm install
```

## 📚 参考資料

- [esbuild Documentation](https://esbuild.github.io/)
- [ES6 Modules](https://developer.mozilla.org/ja/docs/Web/JavaScript/Guide/Modules)

## 🔄 CI/CD統合（将来的に）

GitHub Actionsなどで自動ビルド：

```yaml
# .github/workflows/build.yml
- name: Setup pnpm
  uses: pnpm/action-setup@v2
  with:
    version: 8

- name: Build JavaScript
  run: |
    pnpm install --frozen-lockfile
    pnpm build:prod
```
