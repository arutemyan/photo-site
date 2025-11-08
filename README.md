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

````markdown
# Photo Site

簡潔なプロジェクト概要とリンクをこの README に残し、詳細なユーザー向けドキュメントは `docs/` に、設計・実装メモは `design/` に分離しました。

このリポジトリの主要なエントリ:

- docs (エンドユーザー向け)
  - `docs/README.md` - ユーザー向けドキュメントの起点（設定、クイックスタート、運用注意）
- design (設計・実装メモ、アーキテクチャ資料)
  - `design/README.md` - 設計資料の起点

クイックスタート（開発・テスト用）:

```bash
# Photo Site

Photo Site はシンプルなイラスト投稿・閲覧サイトです。管理画面にはブラウザ上で描けるペイント（タイムラプス記録付き）エディタがあり、作品はメタデータを SQLite に、描画データを専用ファイル（.illust）として保存します。

主な特徴
- 管理用ペイントエディタ（public/admin/paint/） — レイヤー、履歴、タイムラプス
- 公開ギャラリーと作品詳細（public/paint/） — サムネイル・タイムラプス再生
- API（管理・公開） — 保存/読み込み/一覧/タイムラプス配信
- ファイルベースの作品データ（.illust）と DB のハイブリッド設計

技術スタック
- PHP 8.x, SQLite
- フロントエンド: Vanilla ES6 JavaScript (モジュール), HTML5 Canvas
- 画像処理: GD または Imagick (WebP 出力を推奨)

すばやい開始方法（開発用）
```bash
composer install
php -S localhost:8000 -t public/
```

ドキュメント
- ユーザー向けドキュメント: docs/README.md
- 設計・内部資料: design/README.md

管理画面（開発時）: http://localhost:8000/admin

ライセンス: MIT

