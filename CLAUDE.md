````markdown
この開発メモは `design/CLAUDE.md` に移動しました。設計・実装の詳細は `design/` を参照してください。

````
- カスタムHTML (header/footer)

### 3. NSFWフィルター機能
#### 設定場所: `config/nsfw.php`
- **年齢確認**: Cookie有効期限（デフォルト7日間）
- **フィルタータイプ**:
  - `blur` - 従来のぼかし効果
  - `frosted` - すりガラス効果（推奨）

#### フィルター設定
```php
'blur_settings' => [
    'blur_passes' => 20,    // ぼかし回数
    'brightness' => -30,    // 明度調整
    'quality' => 75,        // WebP品質
],

'frosted_settings' => [
    'blur_passes' => 25,        // ぼかし回数
    'contrast' => -10,          // コントラスト
    'brightness' => 15,         // 明度
    'overlay_opacity' => 115,   // 透明度 (0-127, 高=透明)
    'quality' => 80,            // WebP品質
],
```

#### 画像命名規則
- オリジナル: `filename.webp`
- サムネイル: `filename.webp`
- ぼかし版: `filename_blur.webp`
- すりガラス版: `filename_frosted.webp`

#### フィルター画像の生成
```bash
# 設定に応じた画像生成
php generate_blur_thumbnails.php

# フィルタータイプ指定
php generate_blur_thumbnails.php --type=blur
php generate_blur_thumbnails.php --type=frosted

# 既存画像を強制再生成
php generate_blur_thumbnails.php --force
```

### 4. タグ機能
- カンマ区切りでタグ登録
- タグクラウド表示
- タグでフィルタリング

### 5. キャッシュ機能
- 投稿一覧・詳細ページのキャッシュ
- 更新時に自動無効化

## 重要な実装詳細

### ImageUploader クラス
- アップロードとバルクアップロードで共通化
- NSFW画像用フィルター版の自動生成
- WebP形式への変換
- サムネイル生成 (600x600)

### すりガラス効果アルゴリズム
1. ガウシアンブラーを複数回適用 (40回)
2. コントラスト調整 (-10)
3. 明度調整 (+15)
4. 半透明白オーバーレイ (alpha=115)
   - ※alpha値: 0=不透明, 127=完全透明
   - 115で色を保ちつつすりガラス感を実現

## マイグレーション履歴
- `migration_tags.php` - タグ機能追加
- `migration_view_count.php` - 閲覧数カウント追加
- `migration_add_visibility.php` - 表示/非表示機能
- `migration_db_separation.php` - DB分離
- `migration_theme_enhancement.php` - テーマ機能拡張

## セキュリティ
- CSRF保護 (CSRFProtection クラス)
- セッション管理 (secure, httponly, samesite)
- 画像アップロード検証 (MIME type, ファイルサイズ)
- 管理画面の認証チェック

## 開発メモ

### 2024年実装内容
1. テーマカスタマイズ機能（カラー、ロゴ、ヘッダー画像）
2. 投稿編集機能の修正（GET by ID 実装）
3. 一括アップロード機能の修正（CSRF対応）
4. ImageUploaderクラスでコード重複削除（~110行削減）
5. NSFWフィルター設定をconfig/nsfw.phpに統合
6. すりガラス効果の改善（灰色問題解決）
7. フィルター強度設定の追加

### トラブルシューティング
- **フィルター画像が灰色**: `overlay_opacity`を90→115に変更済み
- **API 404エラー**: 全てのAPIは`.php`拡張子必須
- **編集時エラー**: posts.php に GET by ID を実装済み

## 今後の拡張可能性
- 画像の並び替え機能
- カテゴリ機能
- コメント機能
- SNSシェア機能
- 画像の一括編集
- より高度なフィルター効果
