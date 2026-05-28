# 実装状況サマリ

最終更新: 2025-11-13

このファイルはリポジトリ内の現状を元に「実装済 / 部分実装 / 未実装」をまとめた一元的なステータスです。

---

## 使い方（短く）
- 各項目はチェックボックスで状態を示します。根拠となるファイルパスを併記しています。変更や不整合があれば該当ファイルを開いて差分を確認し、ここを更新してください。
- 大カテゴリは「バックエンド」「フロントエンド（管理）」「フロントエンド（公開）」「データモデル/マイグレーション」「テスト/CI」です。

---

## バックエンド
- [x] Paint（イラスト）モデル（永続化） — src/Models/Paint.php
  - 根拠: `src/Models/Paint.php`（create/find/update/delete 実装あり）
- [x] .illust ファイル検証・シリアライズ — src/Models/IllustFile.php
  - 根拠: `src/Models/IllustFile.php`（validate() / toJson() 実装）
- [x] 保存 API `/admin/paint/api/save.php` — public/admin/paint/api/save.php
  - 根拠: `public/admin/paint/api/save.php`（IllustSaveController 実装）
- [x] ロード API `/admin/paint/api/load.php` — public/admin/paint/api/load.php
  - 根拠: `public/admin/paint/api/load.php`
- [x] 一覧取得 API（管理） — public/admin/paint/api/list.php
  - 根拠: `public/admin/paint/api/list.php`
- [x] 公開一覧 API `/paint/api/paint.php` — public/paint/api/paint.php
  - 根拠: `public/paint/api/paint.php`
- [x] タイムラプス保存/配信 — src/Services/TimelapseService.php, public/admin/paint/api/timelapse.php, public/paint/api/timelapse.php
  - 根拠: `src/Services/TimelapseService.php`, 管理／公開 API のタイムラプス用エンドポイント（存在確認）
- [x] 画像・サムネイル生成ワークフロー — src/Services/IllustService.php
  - 根拠: `src/Services/IllustService.php`（画像処理／サムネ生成のロジック）
- [x] パレット / 設定 API — public/admin/paint/api/palette.php, public/admin/paint/api/data.php
  - 根拠: それぞれの API ファイルの存在
- [x] CSRF / 認可チェック（API レイヤ） — 対応コードと統合テストあり
  - 根拠: `src/Security`、各コントローラでのチェック、tests/ の統合テスト

## フロントエンド（管理）
- [x] 管理用ペイント UI（エントリ） — public/admin/paint/index.php
  - 根拠: `public/admin/paint/index.php`
- [x] メイン JS とモジュール（ツール・レイヤー・履歴・タイムラプス記録・ストレージ 等） — public/admin/paint/js/paint.js, public/admin/paint/js/modules/*
  - 根拠: `public/admin/paint/js/` 以下のモジュール群（timelapse_recorder.js, history.js, layers.js, tools.js, storage.js, state.js, colors.js, canvas_transform.js 等）
- [x] タイムラプス記録 Worker — public/admin/paint/js/timelapse_worker.js
- [x] パレット UI 統合 — paint.js の palette fetch と modules/colors.js

## フロントエンド（公開）
- [x] ギャラリー一覧 — public/paint/index.php, public/paint/js/gallery.js
- [x] 詳細ページ + タイムラプス再生 — public/paint/detail.php, public/paint/js/detail.js

## データモデル / マイグレーション
- [x] paint テーブル作成マイグレーション — migrations/008_add_paint_table.php
- [x] 追加カラム用マイグレーション — migrations/010_add_description_tags_to_paint.php

## テスト / CI
- [x] 統合テスト（ペイント関連）の存在 — `tests/Integration` や `tests/Api` 配下に関連テストあり
- [x] CI 用ワークフロー（基本） — `.github/workflows/ci.yml` に CI 設定の痕跡

---

## 部分実装 / 要確認
- [~] 高度なブラシ/ツール・プラグイン的機能 — フロントエンドで基礎機能は揃っているが、仕様書レベルの高度機能は未実装または限定的（要: 要件一覧と UI 比較）
- [~] ドキュメントに記載されている公開 API の一部拡張 — docs 側で想定されている拡張と実装の突合せが必要（要: `docs/ILLUST_BOARD_API.md` と実装の比較）

---

## 追加メモ / 参考コマンド
- 実装の存在チェック（例）:

```bash
ls src/Models | sed -n '1,200p'
ls public/admin/paint/js | sed -n '1,200p'
vendor/bin/phpunit --colors=always
```
