````markdown
# お絵描き機能データモデル設計

## ハイブリッドストレージアプローチ

### 設計方針
- **メタデータ**: SQLite DB (PDO) で管理 - 検索・一覧表示用
- **作品データ**: 独自ファイル形式で物理ファイル保存 - 描画データ・レイヤー情報・タイムラプス

### 利点
- DBアクセスを最小限に抑え、高速な一覧表示
- 作品データの一体性確保
- バックアップ・バージョン管理が容易
- ファイルシステムのスケーラビリティ

## データベースモデル

### Paint (イラスト) テーブル
```sql
CREATE TABLE paint (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    canvas_width INTEGER NOT NULL DEFAULT 800,
    canvas_height INTEGER NOT NULL DEFAULT 600,
    background_color TEXT DEFAULT '#FFFFFF',
    data_path TEXT,  -- .illustファイルのパス
    image_path TEXT, -- エクスポート画像のパス
    thumbnail_path TEXT, -- サムネイル画像のパス
    timelapse_path TEXT, -- タイムラプスファイルのパス
    timelapse_size INTEGER DEFAULT 0,  -- 圧縮後サイズ（バイト）
    file_size INTEGER DEFAULT 0,  -- .illustファイルサイズ
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_illusts_user_id ON paint(user_id);
CREATE INDEX idx_illusts_status ON paint(status);
CREATE INDEX idx_illusts_created_at ON paint(created_at);
```

## 独自ファイル形式 (.illust)

### ファイル構造
- 拡張子: `.illust`
- 形式: JSON + Base64エンコード画像データ
- 圧縮: オプション (gzip圧縮可能)

### JSONスキーマ
```json
{
  "version": "1.0",
  "metadata": {
    "canvas_width": 800,
    "canvas_height": 600,
    "background_color": "#FFFFFF",
    "created_at": "2024-01-01T12:00:00Z",
    "updated_at": "2024-01-01T12:30:00Z"
  },
  "layers": [ /* ... */ ],
  "timelapse": { /* ... */ }
}
```

## タイムラプスデータ形式
- **形式**: ヘッダ付き CSV テキスト を gzip 圧縮
- **拡張子**: `.csv.gz`
- **構造**: 各行が1イベントを表すヘッダ付き CSV（各列は event オブジェクトのキー）

````
