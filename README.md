## 機能フラグの管理と確認

このアプリケーションは `config/config.default.php` の設定で `paint.enabled` と `admin.enabled` により機能の ON/OFF を制御します。運用では `config/config.local.php` で上書きすることを推奨します。
 - Paint を無効にする例 (`config/config.local.php` を作成):

```php
<?php
 - 確認:

```bash
curl -I http://localhost:8000/paint/   # -> 404 when disabled
自動挿入ツール:

管理 API の中に存在する「手続き的（class を宣言していない）PHPファイル」に `_feature_check.php` を挿入する自動化スクリプトを `scripts/insert_admin_feature_check.php` に追加しました。
使い方:

```bash
# プレビュー（差し込むファイルとスニペットを表示するだけ）
# 実際に適用（各ファイルのバックアップを .bak.TIMESTAMP で作成）
php scripts/insert_admin_feature_check.php --apply

このスクリプトはファイル先頭付近の `require/include` ブロックの直後に
`require_once(__DIR__ . '/_feature_check.php');` を挿入します。まずはプレビューを確認してから `--apply` を実行してください。

![test workflow](https://github.com/arutemyan/photo-site/actions/workflows/tests.yml/badge.svg)

# Photo Site - イラストポートフォリオサイト

PHPベースのイラストレーター向けポートフォリオサイト

## 主要機能

- **画像ギャラリー** - グリッドレイアウトでの作品表示
- **NSFW/センシティブコンテンツ対応** - 年齢確認と自動ぼかし処理
- **管理画面** - 投稿管理、一括アップロード、編集機能
- **テーマカスタマイズ** - 色、フォント、ロゴ、ヘッダー画像のカスタマイズ
- **タグフィルタリング** - タグによる作品の絞り込み
- **閲覧数カウント** - 投稿ごとの閲覧数表示
- **レスポンシブデザイン** - スマホ・タブレット対応
- **WebP画像形式** - 高速で軽量な画像配信

## クイックスタート

### 1. 依存インストール

```bash
composer install
```

### 2. 開発サーバー起動

```bash
php -S localhost:8000 -t public/
```

ブラウザで `http://localhost:8000` にアクセス

### 3. 初期セットアップ

初回アクセス時に自動的にデータベースとテーブルが作成されます。

管理画面にアクセス: `http://localhost:8000/admin`

## プロジェクト構成

```
photo-site/
├── public/              # Webルート
│   ├── index.php        # トップページ（ギャラリー表示）
│   ├── detail.php       # 作品詳細ページ
│   ├── admin/           # 管理画面
│   ├── api/             # 公開API
│   ├── res/             # 静的リソース（CSS/JS）
│   └── uploads/         # アップロード画像保管
├── src/                 # アプリケーションコア
│   ├── Models/          # データモデル（Post, Theme, User等）
│   ├── Database/        # データベース接続
│   ├── Cache/           # キャッシュシステム
│   ├── Security/        # セキュリティ機能
│   └── Utils/           # ユーティリティ（画像処理等）
├── config/              # 設定ファイル
│   ├── config.php       # 統合設定ローダー
│   ├── config.default.php  # デフォルト設定
│   └── config.local.php    # ローカル設定（gitignore）
├── data/                # データベースファイル
│   ├── gallery.db       # メインデータ
│   └── counters.db      # 閲覧数カウンター
├── cache/               # キャッシュファイル
├── scripts/             # マイグレーション等
├── tests/               # テストコード
└── docs/                # ドキュメント
```

## 設定方法

詳細な設定方法は [docs/CONFIG.md](docs/CONFIG.md) を参照してください。

### 基本的な設定
cp config/config.local.php.example config/config.local.php
```

`config/config.local.php` を編集して環境に合わせて設定を変更できます：

## FeatureGate の使用例

共通化した `FeatureGate` ユーティリティは、機能フラグの確認と無効化時の 404 応答を簡単に行えます。

使用例（テンプレートやコントローラ内で）:

```php
use App\Utils\FeatureGate;

// ペイント機能が有効でなければ 404 を返して終了
FeatureGate::ensureEnabled('paint');

// フラグだけ確認して振る舞いを切り替えたい場合
if (FeatureGate::isEnabled('paint')) {
    // ペイント機能用のリンクや UI を表示
}
```

内部的には `src/Utils/FeatureGate.php` が `config/config.php` を読み込み、
`$config['paint']['enabled']` や `$config['admin']['enabled']` を参照します。


```php
<?php
return [
    'nsfw' => [
        'age_verification_minutes' => 1, // デバッグ用に1分
    ],
    'cache' => [
        'enabled' => false, // 開発時はキャッシュ無効
    ],
    'security' => [
        'https' => [
            'force' => false, // HTTPで開発
        ],
    ],
];
```

## 主な機能

### 画像アップロード

管理画面から画像をアップロードできます：
- 対応形式: JPEG, PNG, WebP
- 自動的にWebP形式に変換
- サムネイル自動生成
- NSFW画像の自動ぼかし処理

### NSFW対応

センシティブなコンテンツには：
- 年齢確認モーダル表示
- 自動ぼかし処理

### テーマカスタマイズ

管理画面でカスタマイズ可能：
- カラースキーム（プライマリ、セカンダリ、アクセント等）
- フォントカラー（見出し、本文、フッター等）
- ロゴ画像
- ヘッダー画像
- サイトタイトル・サブタイトル

## デプロイ

### 共有ホスティング

```bash
# 1. FTPで全ファイルをアップロード

# 2. 必要なディレクトリの権限設定
chmod 755 data/
chmod 755 cache/
chmod 755 public/uploads/

# 3. 初回アクセスでデータベース自動作成
```

### 注意事項

- `config/config.local.php` は本番環境用の設定を作成してください
- `data/` と `cache/` ディレクトリは書き込み可能にしてください
- `public/uploads/` は画像アップロード用に書き込み可能にしてください

## セキュリティ

実装済みのセキュリティ対策：
- SQLインジェクション対策（Prepared Statements）
- XSS対策（エスケープ処理）
- CSRF対策（トークン検証）
- ディレクトリトラバーサル対策
- ファイルアップロードバリデーション
- セキュアなセッション管理
- レート制限

## 技術スタック

- **PHP 8.x** - strict_types、型宣言
- **SQLite3** - 軽量データベース（3DB分離構成）
- **WebP** - 次世代画像フォーマット
- **PSR-4** - オートローディング
- **Composer** - 依存関係管理

## スクリプト

```bash
# NSFWフィルター画像の生成
php scripts/generate_blur_thumbnails.php

# データベースマイグレーション
php scripts/migration_*.php
```

## ドキュメント

- [CONFIG.md](docs/CONFIG.md) - 設定ファイルの詳細ガイド
- [CLAUDE.md](CLAUDE.md) - 開発仕様書

## ライセンス

MIT License
