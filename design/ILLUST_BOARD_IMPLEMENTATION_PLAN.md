# お絵描き機能 実装状況（design 観点、現状反映済）

このファイルは実装チェックリストを「現状のリポジトリの状態」に合わせて更新したものです。
各項目は「実装済 / 部分実装 / 未実装」で分類し、リポジトリ内の根拠ファイルパスを添えています。

※ 参考: 管理向け UI は `public/admin/paint/`、公開向けビューは `public/paint/` にあります。

## 主要カテゴリ

- バックエンド（API・データ保存・タイムラプス等）
- フロントエンド（管理用ペイント UI、公開ギャラリー/詳細、タイムラプス再生）
- データモデル・マイグレーション
- テスト・CI

---

## 現在の実装チェックリスト

### バックエンド
- [x] Paint（イラスト）モデルと永続化: src/Models/Paint.php を実装
  - 根拠: src/Models/Paint.php (INSERT/SELECT/UPDATE/一覧取得など)
- [x] .illust ファイルの検証/シリアライズ: src/Models/IllustFile.php
  - 根拠: src/Models/IllustFile.php::validate(), toJson()
- [x] 保存API (`/admin/paint/api/save.php`) とロードAPI (`/admin/paint/api/load.php`) の実装
  - 根拠: public/admin/paint/api/save.php, public/admin/paint/api/load.php
- [x] 一覧取得API (`/admin/paint/api/list.php`) と公開向け一覧 API (`/paint/api/paint.php`) の実装
  - 根拠: public/admin/paint/api/list.php, public/paint/api/paint.php
- [x] タイムラプス保存/配信処理: TimelapseService, public/admin/paint/api/timelapse.php, public/paint/api/timelapse.php
  - 根拠: src/Services/TimelapseService.php, public/admin/paint/api/timelapse.php, public/paint/api/timelapse.php
- [x] 画像・サムネイル作成と保存フロー（IllustService）
  - 根拠: src/Services/IllustService.php (thumbnail generation, uploads path handling)
- [x] パレット / 設定等の API: public/admin/paint/api/palette.php, public/admin/paint/api/data.php
  - 根拠: public/admin/paint/api/palette.php, public/admin/paint/api/data.php
- [x] CSRF / 認可チェックが API レイヤに組み込まれている（テストあり）
  - 根拠: テスト群に AdminPaintIntegrationTest 等が存在 (.phpunit.cache と tests/ 配下)

### フロントエンド（管理用）
- [x] 管理用ペイント UI エントリおよびロード: public/admin/paint/index.php
  - 根拠: public/admin/paint/index.php
- [x] メインJS とモジュール群（ツール・レイヤー・履歴・タイムラプス記録・ストレージ）
  - 根拠: public/admin/paint/js/paint.js
    - public/admin/paint/js/modules/timelapse_recorder.js
    - public/admin/paint/js/modules/history.js
    - public/admin/paint/js/modules/layers.js
    - public/admin/paint/js/modules/tools.js
    - public/admin/paint/js/modules/storage.js
    - public/admin/paint/js/modules/state.js
    - public/admin/paint/js/modules/colors.js
    - public/admin/paint/js/modules/canvas_transform.js
    - public/admin/paint/js/timelapse_worker.js
- [x] パレット取得 & UI 統合
  - 根拠: public/admin/paint/js/paint.js の fetch('/admin/paint/api/palette.php') と modules/colors.js

### フロントエンド（公開）
- [x] ギャラリー一覧ページ: public/paint/index.php + public/paint/js/gallery.js
  - 根拠: public/paint/index.php, public/paint/js/gallery.js, public/paint/api/paint.php
- [x] 詳細ページとタイムラプス再生: public/paint/detail.php + public/paint/js/detail.js
  - 根拠: public/paint/detail.php, public/paint/js/detail.js

### データモデル・マイグレーション
- [x] paint テーブル作成用マイグレーション: public/setup/migrations/008_add_paint_table.php
  - 根拠: public/setup/migrations/008_add_paint_table.php
- [x] カラム追加等の後続マイグレーション: public/setup/migrations/010_add_description_tags_to_paint.php
  - 根拠: public/setup/migrations/010_add_description_tags_to_paint.php

### テスト・CI
- [x] 統合テスト（ペイント関連）の存在: Tests/Api/AdminPaintIntegrationTest 等
  - 根拠: tests/ 配下に AdminPaintIntegrationTest があり、.phpunit.cache に該当テスト名が見える
- [x] CI ワークフローで必要ディレクトリを作成する設定: .github/workflows/ci.yml
  - 根拠: .github/workflows/ci.yml に uploads/paintfiles/ などの作成がある

---

## 部分実装 / 未実装（要確認）

- [ ] 細かい UI 機能の未完成部分（例: 高度なブラシ設定・プラグイン的機能）
  - 理由: フロントエンドの基盤は揃っているが、仕様書に記載の "高度機能" はドキュメント側でタスク化されており、コード上で個別対応が必要か確認が必要。
- [ ] ドキュメントに記載された将来 API（/api/paint/ の拡張など）の一部は未実装
  - 理由: docs/ に将来向け API 記載があるが、現リポジトリに同等の管理・公開 API が揃っているか要確認。

---

## 参照と次のアクション提案

1. このチェックリストはファイルパスで実装済みの根拠を示しました。次はリンク整合性と具体的な "未実装" 項目の優先度付けです。
2. 提案アクション
   - リンク整合性チェック（docs ⇄ design 内の参照） — 優先度: 高
   - 未実装/部分実装の細分化（UI/機能ごとに issue 化） — 優先度: 中
   - テストの実行（該当する AdminPaintIntegrationTest 等をローカルで走らせてグリーンになるか確認） — 優先度: 高

---

更新履歴:
- 2025-11-08: 実装済項目をリポジトリの現物を参照して反映しました（API、フロントエンド管理 UI、マイグレーション、サービス、テスト）。
