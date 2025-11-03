# お絵描き機能実装計画

## 全体スケジュール

### フェーズ1: バックエンド基盤構築 (1-2週間)
データベース、API基盤、セキュリティの実装。

### フェーズ2: フロントエンド基盤構築 (1週間)
HTML/CSS/JSの基本レイアウトとキャンバス操作。

### フェーズ3: コア機能実装 (2-3週間)
描画ツール、レイヤー管理、保存・読み込み。

### フェーズ4: 高度機能実装 (2週間)
タイムラプス、選択ツール、画像編集。

### フェーズ5: テスト・統合・公開 (1-2週間)
テスト、UI調整、既存システム統合。

## フェーズ1: バックエンド基盤構築

### 1.1 データベース設計とマイグレーション
- [ ] `illusts` テーブル作成
- [ ] マイグレーションファイル作成 (`src/Database/MigrationHelper.php` 使用)

> 注: レイヤー情報は `.illust` ファイル内に格納するため、`illust_layers` の別テーブルは作成しません。タイムラプスもファイルシステムで管理するため、`illust_timelapse_chunks` の DB テーブルは不要とします。

### 1.2 モデルクラス実装
- [ ] `src/Models/Illust.php` 作成
  - メタデータのみのCRUD操作
  - バリデーション
- [ ] `src/Models/IllustFile.php` 作成
  - .illustファイルの読み書き
  - JSONスキーマバリデーション

### 1.3 サービスクラス実装
- [ ] `src/Services/IllustService.php` 作成
  - ビジネスロジック
  - ファイル操作
- [ ] `src/Services/TimelapseService.php` 作成
  - CSV(ヘッダ付き) 圧縮/解凍
  - チャンク管理

追加タスク（実装前に）:
- [ ] `src/Utils/EnvChecks.php` を作成して、zlib/画像変換ライブラリの有無チェック機能を実装
- [ ] サムネイル生成は WebP 出力を優先するため、`IllustService` に WebP 生成ロジック（gd/imagick を利用）を追加

### 1.4 APIエンドポイント実装
- [ ] `public/admin/paint/api/save.php` 作成
- [ ] `public/admin/paint/api/load.php` 作成
- [ ] `public/admin/paint/api/list.php` 作成
- [ ] `public/admin/paint/api/delete.php` 作成
- [ ] `public/admin/paint/api/image.php` 作成 (管理機能用画像アクセス)
- [ ] `public/admin/paint/api/timelapse.php` 作成 (タイムラプスストリーミング)
- [ ] `public/admin/paint/api/data.php` 作成 (.illustデータ取得)
- [ ] 共通API基盤 (`src/Http/ApiResponse.php`)

### 1.5 セキュリティ実装
- [ ] セッション認証確認
- [ ] CSRFトークン実装
- [ ] ファイルアップロードセキュリティ
- [ ] レート制限適用

### 1.6 ユーティリティ実装
- [ ] `src/Utils/FileCompressor.php` (zlib + csv)
- [ ] `src/Utils/ImageProcessor.php` (画像処理)
- [ ] `src/Utils/IllustFileManager.php` (.illustファイル管理)
- [ ] `src/Utils/PaintConfig.php` (お絵描き設定管理 - config.default.phpから読み込み)
- [ ] `src/Utils/TimelapseValidator.php` (データ検証)

## フェーズ2: フロントエンド基盤構築

### 2.1 HTML構造作成
- [ ] `public/admin/paint/index.php` 作成
  - 基本レイアウト
  - キャンバス要素
  - ツールバー構造
- [ ] `public/admin/paint/canvas.php` 作成 (別ページ用)

### 2.2 CSSスタイリング
- [ ] `public/res/css/paint.css` 作成
  - レスポンシブレイアウト
  - ツールバー/パネルスタイル
  - モバイル対応

### 2.3 JavaScript基盤
- [ ] `public/res/js/paint/canvas.js` 作成
  - Canvas初期化
  - 基本描画関数
- [ ] `public/res/js/paint/ui.js` 作成
  - UIイベントハンドリング
  - ツールバー制御

### 2.4 キャンバス操作基盤
- [ ] マウス/タッチイベント処理
- [ ] 座標変換
- [ ] ズーム/パン機能

## フェーズ3: コア機能実装

### 3.1 描画ツール実装
- [ ] ペンツール (`public/res/js/paint/tools/pen.js`)
  - 線描画
  - 設定管理 (太さ/色/アンチエイリアス)
- [ ] 消しゴムツール (`public/res/js/paint/tools/eraser.js`)
  - 消去処理
  - 設定管理
- [ ] 履歴管理統合 (各ツール操作で履歴保存)

### 3.2 レイヤーシステム
- [ ] レイヤー管理 (`public/res/js/paint/layers.js`)
  - 4レイヤー固定管理 (ファイル内データ)
  - 可視性/不透明度制御
  - 名前変更
- [ ] レイヤー描画統合
  - レイヤー合成
  - 順序管理
  - ファイル保存連携

### 3.3 カラーパレット
- [ ] パレットUI (`public/res/js/paint/palette.js`)
  - 16色管理
  - プリセット切り替え
  - カラーピッカー統合

### 3.3.5 Undo/Redo機能
- [ ] 履歴管理 (`public/res/js/paint/history.js`)
  - キャンバス状態の保存 (最大50履歴)
  - Undo/Redo操作
  - メモリ管理 (古い履歴の破棄)
- [ ] UI統合
  - Undo/Redoボタンの有効/無効制御
  - キーボードショートカット (Ctrl+Z, Ctrl+Y)
- [ ] タイムラプス連携
  - Undo/Redo操作もタイムラプスに記録

### 3.4 保存・読み込み機能
- [ ] 保存機能
  - キャンバス→画像変換
  - .illustファイル生成 (レイヤー+タイムラプス)
  - DBメタデータ + ファイル保存
- [ ] 読み込み機能
  - .illustファイル読み込み
  - レイヤーデータ復元
  - キャンバス再構築

### 3.5 基本編集機能
- [ ] キャンバス設定
  - サイズ変更
  - 背景色設定
- [ ] 視覚操作
  - ズーム
  - パン

## フェーズ4: 高度機能実装

### 4.1 タイムラプス機能
- [ ] 記録システム (`public/res/js/paint/timelapse.js`)
  - 操作ログ収集
  - 容量管理
- [ ] 再生機能
  - タイムラプス読み込み
  - ステップ再生
  - シーク機能

### 4.2 選択・編集ツール
- [ ] 矩形選択 (`public/res/js/paint/tools/select.js`)
  - 選択範囲管理
  - 切り取り/貼り付け
- [ ] スポイトツール
  - 色取得
  - パレット追加

### 4.3 画像編集機能
- [ ] 実際の編集処理
  - 回転 (Canvas操作ではなく画像データ変更)
  - 拡縮
- [ ] 複数レイヤー対応

### 4.4 高度なUI機能
- [ ] 設定パネル拡張
- [ ] キーボードショートカット
- [ ] コンテキストメニュー

## フェーズ5: テスト・統合・公開

### 5.1 単体テスト
- [ ] PHPユニットテスト (`tests/Api/AdminPaintApiTest.php`)
- [ ] JavaScriptテスト (Jest等導入検討)
- [ ] モデルテスト

### 5.2 統合テスト
- [ ] API統合テスト
- [ ] フロントエンド統合テスト
- [ ] エンドツーエンドテスト

### 5.3 UI/UX調整
- [ ] レスポンシブ対応確認
- [ ] モバイルテスト
- [ ] パフォーマンス最適化

### 5.4 既存システム統合
- [ ] 管理メニュー追加
- [ ] ナビゲーション統合
- [ ] 権限設定

### 5.5 公開ビュー実装
- [ ] `public/paintview.php` 作成
  - 作品一覧表示
  - オーバーレイ表示
  - タイムラプス再生

### 5.6 ドキュメント・デプロイ
- [ ] README更新
- [ ] ユーザーマニュアル作成
- [ ] 本番デプロイ

## 技術スタックと依存関係

### バックエンド
- PHP 8.1+
- SQLite 3.x (既存システム準拠)
- 既存ライブラリ: CsrfProtection, RateLimiter, ImageUploader

### フロントエンド
- Vanilla JavaScript (ES6+)
- HTML5 Canvas API
- CSS3 (Flexbox/Grid)
- 外部ライブラリ: 最小限（gzip 圧縮および CSV パーサ）

### 開発ツール
- Composer (PHP依存管理)
- PHPUnit (テスト)
- VS Code + 拡張機能

## リスクと対策

### 技術的リスク
- **Canvasパフォーマンス**: レイヤー合成の最適化
- **タイムラプス容量**: 圧縮アルゴリズムの調整
- **モバイル対応**: タッチイベントの調整

### プロジェクトリスク
- **スコープ拡大**: 要件を厳密に管理
- **依存関係**: 既存システムとの分離を維持
- **セキュリティ**: 継続的なセキュリティレビュー

## 品質基準

### コード品質
- PSR-12準拠
- ユニットテストカバレッジ 80%以上
- 静的解析ツール導入

### パフォーマンス基準
- ページ読み込み: 3秒以内
- キャンバス操作: 60FPS維持
- APIレスポンス: 500ms以内

### セキュリティ基準
- OWASP Top 10対応
- 脆弱性スキャン合格
- ログ監査体制

## マイルストーン

### M1: 基盤完成 (フェーズ1終了)
- DBスキーマ完成
- 基本API動作確認
- セキュリティ実装完了

### M2: 基本機能完成 (フェーズ2-3終了)
- キャンバス描画可能
- 保存・読み込み可能
- レイヤー操作可能

### M3: 高度機能完成 (フェーズ4終了)
- タイムラプス動作
- 編集機能動作
- UI/UX完成

### M4: リリース準備完了 (フェーズ5終了)
- テスト完了
- 統合完了
- ドキュメント完了