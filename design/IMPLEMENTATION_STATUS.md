# 実装状況サマリ

最終更新: 2025-11-08

このファイルはリポジトリ内の現状を元に「実装済 / 部分実装 / 未実装」をまとめた一元的なステータスです。
今後、私（アシスタント）が実装状況をチェック・更新するときはここを起点にします。

---

## 使い方（短く）
- 各項目はチェックボックスで状態を示します。根拠ファイルパスを付けてあるので、変更があったらそのファイルを確認して更新してください。
- 大カテゴリは「バックエンド」「フロントエンド（管理）」「フロントエンド（公開）」「データモデル/マイグレーション」「テスト/CI」です。

---

## バックエンド
- [x] Paint（イラスト）モデル（永続化） — src/Models/Paint.php
- [x] .illust ファイル検証・シリアライズ — src/Models/IllustFile.php
- [x] 保存 API `/admin/paint/api/save.php` — public/admin/paint/api/save.php
- [x] ロード API `/admin/paint/api/load.php` — public/admin/paint/api/load.php
- [x] 一覧取得 API（管理） — public/admin/paint/api/list.php
- [x] 公開一覧 API `/paint/api/paint.php` — public/paint/api/paint.php
- [x] タイムラプス保存/配信 — src/Services/TimelapseService.php, public/admin/paint/api/timelapse.php, public/paint/api/timelapse.php
- [x] 画像・サムネイル生成と保存ワークフロー — src/Services/IllustService.php
- [x] パレット / 設定 API — public/admin/paint/api/palette.php, public/admin/paint/api/data.php
- [x] CSRF / 認可チェック（API レイヤ） — テスト群に関連テストあり（AdminPaintIntegrationTest 等）

## フロントエンド（管理）
- [x] 管理用ペイント UI（エントリ） — public/admin/paint/index.php
- [x] メイン JS とモジュール（ツール・レイヤー・履歴・タイムラプス記録・ストレージ 等） — public/admin/paint/js/paint.js, public/admin/paint/js/modules/*
  - 例: public/admin/paint/js/modules/timelapse_recorder.js, history.js, layers.js, tools.js, storage.js, state.js, colors.js, canvas_transform.js
- [x] タイムラプス記録 Worker — public/admin/paint/js/timelapse_worker.js
- [x] パレット UI 統合 — paint.js の palette fetch と modules/colors.js

## フロントエンド（公開）
- [x] ギャラリー一覧 — public/paint/index.php, public/paint/js/gallery.js
- [x] 詳細ページ + タイムラプス再生 — public/paint/detail.php, public/paint/js/detail.js

## データモデル / マイグレーション
- [x] paint テーブル作成マイグレーション — public/setup/migrations/008_add_paint_table.php
- [x] 追加カラム用マイグレーション — public/setup/migrations/010_add_description_tags_to_paint.php

## テスト / CI
- [x] 統合テスト（ペイント関連）の存在 — tests/ 配下に AdminPaintIntegrationTest 等
- [x] CI 用ワークフローに必要ディレクトリ作成設定 — .github/workflows/ci.yml

---

## 部分実装・未実装（要確認）
- [ ] 高度なブラシ/ツール・プラグイン的機能（仕様書ベースで未完） — 要: フロントエンドの詳細確認
- [ ] 将来想定の公開 API 拡張（docs に記載だが未実装の可能性あり） — 要: API と docs の突合せ

---

## 運用ルール提案（短く）
1. 実装が変更されたら、コミットメッセージに `docs: update IMPLEMENTATION_STATUS` を追加してこのファイルを更新するワークフローを採用するとよいです。
2. 定期的（PR マージ直後）にこのファイルを自動で更新するスクリプト（簡易 grep + テンプレート）を作ると確認が楽になります。必要なら私が雛形を作ります。

---

## 次のアクション（私の提案）
- (優先) docs ⇄ design のリンク整合性チェックを実行して壊れた参照を修正しますか？
- (優先) ペイント関連テストをローカルで走らせてグリーンか確認しますか？（部分実装の洗い出しに有用）

---

このファイルを別名で分割したい、もっと細かく (feature 毎のサブファイル) にしたい、あるいは YAML など機械的にパースしやすい形式が良い、など希望があれば教えてください。