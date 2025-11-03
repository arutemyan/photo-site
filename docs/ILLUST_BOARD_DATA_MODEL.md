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

### Illust (イラスト) テーブル
```sql
CREATE TABLE illusts (
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
CREATE INDEX idx_illusts_user_id ON illusts(user_id);
CREATE INDEX idx_illusts_status ON illusts(status);
CREATE INDEX idx_illusts_created_at ON illusts(created_at);
```

### 削除: IllustLayerテーブル
レイヤー情報は.illustファイル内に含めるため、DBテーブルは不要

### 削除: IllustTimelapseChunkテーブル
タイムラプスは単一ファイルまたはチャンク管理をファイル内で処理

## 独自ファイル形式 (.illust)

### ファイル構造
拡張子: `.illust`
形式: JSON + Base64エンコード画像データ
圧縮: オプション (gzip圧縮可能)

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
  "layers": [
    {
      "id": "layer_0",
      "name": "背景",
      "order": 0,
      "visible": true,
      "opacity": 1.0,
      "type": "raster",
      "data": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
      "width": 800,
      "height": 600
    },
    {
      "id": "layer_1", 
      "name": "下書き",
      "order": 1,
      "visible": true,
      "opacity": 1.0,
      "type": "raster",
      "data": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
      "width": 800,
      "height": 600
    }
  ],
  "timelapse": {
    "enabled": true,
    "max_size": 52428800,
    "current_size": 1048576,
    "data": "data:application/octet-stream;base64,..."
  }
}
```

### レイヤータイプ
- **raster**: ビットマップ画像データ
- **vector**: 将来的なベクター拡張用

### ファイルストレージ構造
```
uploads/paintfiles/
├── images/
│   ├── 001/
│   │   ├── illust_1.png
│   │   └── illust_1_thumb.webp
│   └── 002/
data/paintfiles/
├── data/
│   ├── 001/
│   │   ├── illust_1.illust
│   │   └── illust_1.illust.gz (圧縮版)
│   └── 002/
└── timelapse/
    ├── 001/
    │   └── timelapse_1.csv.gz
    └── 002/
data/tmp/
    ├── sessions/
    │   └── sess_abc123/
    │       ├── temp_canvas.png
    │       └── temp_timelapse.csv
    └── uploads/
```

## タイムラプスデータ形式

### 全体構造
- **形式**: ヘッダ付き CSV テキスト を gzip 圧縮
- **拡張子**: `.csv.gz`
- **構造**: 各行が1イベントを表すヘッダ付き CSV（各列は event オブジェクトのキー）
- **履歴考慮**: Undo/Redo操作も記録（完全再現のため）

### Action Types と Data 構造

#### 1: ペン描画 (PEN_DRAW)
```javascript
{
    "action_type": 1,
    "timestamp": 1640995200,  // Unix timestamp
    "data": {
        "layer_id": 2,        // 対象レイヤーID
        "start_x": 100,       // 始点X座標
        "start_y": 200,       // 始点Y座標
        "end_x": 150,         // 終点X座標
        "end_y": 250,         // 終点Y座標
        "color": "#000000",   // 色 (HEX)
        "width": 5,           // 太さ
        "antialias": true     // アンチエイリアス
    }
}
```

#### 2: 消しゴム (ERASER)
```javascript
{
    "action_type": 2,
    "timestamp": 1640995201,
    "data": {
        "layer_id": 2,
        "start_x": 100,
        "start_y": 200,
        "end_x": 150,
        "end_y": 250,
        "width": 10,
        "antialias": false
    }
}
```

#### 3: レイヤー操作 (LAYER_OPERATION)
```javascript
{
    "action_type": 3,
    "timestamp": 1640995202,
    "data": {
        "operation": "rename",  // rename, visibility, order, opacity
        "layer_id": 1,
        "new_name": "背景レイヤー",  // rename時
        "is_visible": true,     // visibility時
        "new_order": 2,         // order時
        "opacity": 0.8          // opacity時
    }
}
```

#### 4: ツール設定変更 (TOOL_SETTING)
```javascript
{
    "action_type": 4,
    "timestamp": 1640995203,
    "data": {
        "tool_type": "pen",     // pen, eraser
        "setting": "width",     // width, antialias, color
        "value": 8              // 設定値
    }
}
```

#### 5: 選択操作 (SELECTION_OPERATION)
```javascript
{
    "action_type": 5,
    "timestamp": 1640995204,
    "data": {
        "operation": "cut",     // cut, copy, paste
        "layer_id": 2,
        "selection": {
            "x": 50,
            "y": 50,
            "width": 200,
            "height": 150
        },
        "paste_x": 300,         // paste時の座標
        "paste_y": 100
    }
}
```

#### 6: キャンバス操作 (CANVAS_OPERATION) - 視覚的
```javascript
{
    "action_type": 6,
    "timestamp": 1640995205,
    "data": {
        "operation": "zoom",    // zoom, rotate, pan
        "scale": 1.5,           // zoom時
        "rotation": 45,         // rotate時 (度)
        "pan_x": 100,           // pan時
        "pan_y": 50
    }
}
```

#### 7: 画像編集 (IMAGE_EDIT) - 実際のデータ変更
```javascript
{
    "action_type": 7,
    "timestamp": 1640995206,
    "data": {
        "operation": "rotate",  // rotate, scale, flip
        "layer_id": 2,
        "angle": 90,            // rotate時
        "scale_x": 1.2,         // scale時
        "scale_y": 1.2,
        "flip_horizontal": false, // flip時
        "flip_vertical": true
    }
}
```

#### 8: カラーパレット操作 (PALETTE_OPERATION)
```javascript
{
    "action_type": 8,
    "timestamp": 1640995207,
    "data": {
        "operation": "add_color", // add_color, remove_color, set_preset
        "color": "#FF5733",
        "index": 5,             // パレット位置
        "preset_name": "warm"   // set_preset時
    }
}
```

#### 9: Undo操作 (UNDO)
```javascript
{
    "action_type": 9,
    "timestamp": 1640995208,
    "data": {
        "steps": 1,             // 戻るステップ数
        "previous_state": {     // 戻った状態の情報（記録用）
            "layer_id": "layer_1",
            "operation_type": "pen_draw"
        }
    }
}
```

#### 10: Redo操作 (REDO)
```javascript
{
    "action_type": 10,
    "timestamp": 1640995209,
    "data": {
        "steps": 1,             // 進むステップ数
        "next_state": {         // 進んだ状態の情報（記録用）
            "layer_id": "layer_1",
            "operation_type": "pen_draw"
        }
    }
}
```

## ファイルストレージ構造

### ディレクトリ構造
```
uploads/
├── illusts/
│   ├── images/
│   │   ├── 001/
│   │   │   ├── illust_1.png
│   │   │   └── illust_1_thumb.webp
│   │   └── 002/
│   └── timelapse/
│       ├── 001/
│       │   ├── timelapse_1.csv.gz
│       │   └── chunks/
│       │       ├── chunk_0.csv.gz
│       │       └── chunk_1.csv.gz
│       └── 002/
└── tmp/
    ├── sessions/
    │   └── sess_abc123/
    │       ├── temp_canvas.png
    │       └── temp_timelapse.csv
    └── uploads/
```

### 命名規則
- **画像**: `illust_{id}.png`
- **サムネイル**: `illust_{id}_thumb.webp`
- **タイムラプス**: `timelapse_{id}.csv.gz`
- **チャンク**: `chunk_{index}.csv.gz`

## データ整合性

### 制約
- レイヤーはillust_idごとに4つ固定
- タイムラプスサイズは設定上限以内
- 座標値はキャンバスサイズ以内

### クリーンアップ
- 未保存のtmpファイルは24時間後に削除
- 削除されたillustの関連ファイルはcascade削除
- 容量超過時の古いtimelapseチャンク削除

## パフォーマンス考慮

### インデックス
- illusts: user_id, status, created_at
- illust_layers: illust_id, layer_order
- illust_timelapse_chunks: illust_id, chunk_index

### 圧縮・分割
- タイムラプスは10MBごとにチャンク分割
- 各チャンクは個別にzlib圧縮
- 再生時は必要なチャンクのみ読み込み