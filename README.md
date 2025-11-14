# PixuGallery

軽量なイラスト投稿・管理サイトのためのリポジトリです。

管理用のペイントエディタと公開ギャラリーを備え、PHP（サーバサイド）と最小限のフロントエンド資産で構成されています。

## 目次

- [概要](#概要)
- [主な機能](#主な機能)
- [要件](#要件)
- [クイックスタート（開発）](#クイックスタート開発)
- [設定](#設定)
- [本番前のセキュリティチェックリスト](#本番前のセキュリティチェックリスト)
- [テスト](#テスト)
- [ディレクトリ構成（抜粋）](#ディレクトリ構成抜粋)
- [ドキュメント（詳細）](#ドキュメント詳細)
- [貢献](#貢献)
- [ライセンス](#ライセンス)

## 概要

PixuGallery はブラウザ上で簡単にイラストを作成・投稿・閲覧できる小規模なウェブアプリケーションです。

管理画面にはペイントエディタ（レイヤ・タイムラプス等）を備え、作品はメタデータを DB に、描画データ／画像はファイルストレージに保存します。

## 主な機能

- ブラウザベースのペイントエディタ（レイヤ、タイムラプス記録）
- 作品の保存・一覧・詳細表示・削除（管理者）
- 公開 API（一覧取得、作品取得、タグ検索等）
- 管理 API（作品の編集／削除、メタデータ管理）

## 要件

- PHP 8.x 推奨
- Composer（PHP 依存管理）
- SQLite（開発用）または任意の対応 DB
- Node.js と pnpm / npm（フロントエンドビルドが必要な場合）

## クイックスタート（開発）

1. リポジトリをクローン

2. PHP 依存をインストール

```bash
composer install
```

3. フロントエンド依存（該当する場合）

```bash
pnpm install
# または
npm install
```

4. （任意）フロントをビルド

```bash
pnpm run build
```

5. 内蔵 PHP サーバーで起動（開発用）

```bash
php -S localhost:8000 -t public/
# ブラウザで http://localhost:8000 を開く
```

> 注: 本番運用では nginx / Apache と PHP-FPM 等を推奨します（設定例は追記できます）。

## 設定

- 設定雛形: `config/config.local.example.php`

```bash
cp config/config.local.example.php config/config.local.php
# 編集して DB、管理パス、HTTPS/CORS、セッションキー等を設定
```

重要: `config/config.local.php` は機密情報を含むため、リポジトリにコミットしないでください。

### 機能フラグ

機能の有効／無効は `config/config.default.php` の設定を `config/config.local.php` で上書きすることで切り替えます（例: `paint.enabled`, `admin.enabled`）。

## 本番前のセキュリティチェックリスト

最低限次を確認してください:

- `config/config.local.php` を作成し、管理パスを推測されにくくする
- HTTPS（TLS）を強制する設定を行う
- 不要なセットアップファイル（例: `public/setup/`）を削除
- `data/`, `config/`, `config/session_keys/` 等のパーミッションを適切に設定する
- 強力な管理者パスワードを設定する（推奨: 12文字以上・複雑）

### セッションセキュリティ設定（本番環境推奨）

本番環境では、プロキシやロードバランサ経由で HTTPS 判定が正しく機能しない場合があります。
セッションクッキーの secure フラグを確実に有効化するため、以下のいずれかの設定を推奨します:

**方法1: 環境変数で設定（推奨）**
```bash
# HTTPS 環境でセキュアクッキーを強制
export FORCE_SECURE_COOKIE=1

# または APP_ENV=production で自動有効化
export APP_ENV=production
```

**方法2: config.local.php で設定**
```php
return [
    'security' => [
        'session' => [
            'force_secure_cookie' => true,
        ],
    ],
];
```

詳細な手順や推奨設定は以下を参照してください:

- `docs/SECURITY.md`
- `docs/DEPLOYMENT_SECURITY.md`

## テスト

PHPUnit によるテストが用意されています。

```bash
vendor/bin/phpunit
```

一部のテストは SQLite を利用するため、実行前に `config` をローカル向けに調整してください。

## ディレクトリ構成（抜粋）

- `public/` — ドキュメントルート（エントリポイント）
- `src/` — アプリケーション PHP コード
- `tests/` — PHPUnit テスト
- `docs/` — エンドユーザー向けドキュメント
- `design/` — 設計・実装メモ（開発者向け）
- `scripts/` — 補助スクリプト（サムネ生成など）

## ドキュメント（詳細）

ユーザー向けドキュメント（`docs/`）と設計資料（`design/`）を README から参照できます。主な参照先:

- docs (ユーザー向け): `docs/README.md`
  - 設定: `docs/CONFIG.md`
  - ビルド / デプロイ: `docs/BUILD.md`
  - 管理画面パス: `docs/ADMIN_PATH.md`
  - ドキュメントルート設定: `docs/DOCUMENT_ROOT.md`
  - 公開 API: `docs/ILLUST_BOARD_API.md`

- design (設計/実装): `design/README.md`
  - アーキテクチャ: `design/ILLUST_BOARD_ARCHITECTURE.md`
  - データモデル: `design/ILLUST_BOARD_DATA_MODEL.md`
  - 実装計画: `design/ILLUST_BOARD_IMPLEMENTATION_PLAN.md`

（これらのファイルはリポジトリ内に存在します。詳しくは該当ファイルを参照してください）

## 開発メモ / 既知のユーティリティ

- `scripts/generate_thumbnail.php` — サムネイル生成スクリプト
- `update_timelapse_sizes.php` — タイムラプスのサイズ調整

これらのスクリプトは直接データを操作することがあるため、実行前にバックアップを取ることを推奨します。

## 貢献

貢献歓迎です。Issue や Pull Request をお送りください。PR の際は以下を心がけてください:

- 小さな単位で変更する
- 可能であればテストを追加する
- 変更点は README または docs に反映する

詳細は `CONTRIBUTING.md` を参照してください。

## ライセンス

MIT

