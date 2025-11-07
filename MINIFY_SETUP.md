# JavaScript Minification Setup 完了 ✨

## 📦 セットアップ済み

pnpm + esbuildによるJavaScriptのbundle化とminificationが完了しました。

## 🚀 使い方

### 開発時

```bash
# 通常通り開発（ES6 modulesをそのまま使用）
# config/config.phpで use_bundled_assets = false（デフォルト）
```

### リリース作成

```bash
# ビルド + パッケージング
./create-release.sh [VERSION]
```

これで以下が自動実行されます：
1. `pnpm install` - 依存関係インストール
2. `pnpm build:prod` - JavaScriptをminify
3. `git archive` - リリースパッケージ作成

### 本番環境での使用

`config/config.local.php` で設定：

```php
<?php
return [
    'app' => [
    'use_bundled_assets' => true,  // bundle版のアセットを使用
    ],
];
```

## 📊 ファイルサイズ削減結果

| ファイル | 開発版 | 本番版 | 削減率 |
|---------|--------|--------|--------|
| Paint Application | 118KB | 63KB | 47% |
| Admin | 58KB | 38KB | 34% |
| Main Site | 15KB | 8KB | 48% |

## 📁 作成されたファイル

- `package.json` - pnpm設定
- `build.js` - ビルドスクリプト
- `src/Utils/AssetHelper.php` - 環境に応じたJS読み込み
- `docs/BUILD.md` - 詳細ドキュメント

## ⚙️ 動作原理

1. **開発時**: `public/admin/paint/js/paint.js` をES6 moduleとして読み込み
2. **本番時**: `public/admin/paint/js/paint.bundle.js` をIIFEとして読み込み

`AssetHelper::scriptTag()` が自動で切り替え。

## 🔧 ビルドコマンド

```bash
pnpm build        # 開発用（ソースマップ付き）
pnpm build:prod   # 本番用（minify）
pnpm watch        # ファイル監視
```

## 📚 詳細

詳しくは `docs/BUILD.md` を参照してください。
