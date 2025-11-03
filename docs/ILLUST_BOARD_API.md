# お絵描き機能API設計

## API概要

### ベースURL
- **管理API**: `/admin/paint/api/`
- **公開API**: `/api/paint/` (将来拡張用)

### 認証
- **方式**: セッション認証 (既存adminセッション継承)
- **権限**: admin権限必須
- **CSRF**: トークン必須 (POST/PUT/DELETE)

### レスポンス形式
- **成功**: `{"success": true, "data": {...}}`
- **エラー**: `{"success": false, "error": "message", "code": 400}`
- **Content-Type**: `application/json`

## エンドポイント仕様

### 1. 作品保存 API
**POST** `/admin/paint/api/save`

#### リクエスト
```json
{
    "title": "作品タイトル",
    "canvas_width": 800,
    "canvas_height": 600,
    "background_color": "#FFFFFF",
    "illust_data": "data:application/json;base64,...",  // .illustファイル内容のBase64
    "image_data": "data:image/png;base64,...",          // エクスポート画像のBase64
    "timelapse_data": "data:application/octet-stream;base64,..."  // msgpack.gzのBase64
}
```

#### レスポンス
```json
{
    "success": true,
    "data": {
        "illust_id": 123,
        "data_path": "/uploads/paintfiles/data/001/illust_123.illust",
        "image_path": "/uploads/paintfiles/images/001/illust_123.png",
        "thumbnail_path": "/uploads/paintfiles/images/001/illust_123_thumb.webp",
        "timelapse_path": "/uploads/paintfiles/timelapse/001/timelapse_123.msgpack.gz"
    }
}
```

#### エラーコード
- `400`: 無効なデータ
- `413`: ファイルサイズ超過
- `500`: サーバーエラー

### 2. 作品読み込み API
**GET** `/admin/paint/api/load/{id}`

#### パラメータ
- `id`: イラストID (必須)

#### レスポンス
```json
{
    "success": true,
    "data": {
        "id": 123,
        "title": "作品タイトル",
        "canvas_width": 800,
        "canvas_height": 600,
        "background_color": "#FFFFFF",
        "data_url": "/uploads/paintfiles/data/001/illust_123.illust",
        "image_url": "/uploads/paintfiles/images/001/illust_123.png",
        "thumbnail_url": "/uploads/paintfiles/images/001/illust_123_thumb.webp",
        "timelapse_url": "/uploads/paintfiles/timelapse/001/timelapse_123.msgpack.gz",
        "timelapse_size": 1048576,
        "file_size": 2097152,
        "created_at": "2024-01-01 12:00:00",
        "updated_at": "2024-01-02 15:30:00"
    }
}
```

### 3. タイムラプス取得 API
**GET** `/admin/paint/api/timelapse/{id}`

#### パラメータ
- `id`: イラストID (必須)
- `chunk`: チャンクインデックス (オプション、デフォルト: 0)

#### レスポンス
```json
{
    "success": true,
    "data": {
        "total_chunks": 3,
        "current_chunk": 0,
        "chunk_size": 1048576,
        "timelapse_data": "data:application/octet-stream;base64,..."
    }
}
```

### 4. 作品一覧 API
**GET** `/admin/paint/api/list`

#### パラメータ
- `page`: ページ番号 (デフォルト: 1)
- `limit`: 1ページの件数 (デフォルト: 20, 最大: 50)
- `status`: ステータスフィルタ (draft/published/all, デフォルト: all)

#### レスポンス
```json
{
    "success": true,
    "data": {
        "illusts": [
            {
                "id": 123,
                "title": "作品タイトル",
                "canvas_width": 800,
                "canvas_height": 600,
                "background_color": "#FFFFFF",
                "thumbnail_url": "/uploads/paintfiles/images/001/illust_123_thumb.webp",
                "status": "published",
                "file_size": 2097152,
                "created_at": "2024-01-01 12:00:00",
                "updated_at": "2024-01-02 15:30:00"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 5,
            "total_count": 100
        }
    }
}
```

### 5. 作品削除 API
**DELETE** `/admin/paint/api/delete/{id}`

#### パラメータ
- `id`: イラストID (必須)

#### レスポンス
```json
{
    "success": true,
    "message": "作品を削除しました"
}
```

### 6. 作品更新 API
**POST** `/admin/paint/api/update/{illust_id}`

#### 説明
作品データの更新（レイヤー変更、設定変更等）は.illustファイルの更新として扱います。

#### リクエスト
```json
{
    "illust_data": "data:application/json;base64,...",  // 更新された.illustファイル内容
    "image_data": "data:image/png;base64,...",          // 更新された画像（オプション）
    "update_type": "layer_change"                       // 更新タイプ（記録用）
}
```

#### レスポンス
```json
{
    "success": true,
    "data": {
        "updated_at": "2024-01-02 15:30:00",
        "data_path": "/uploads/paintfiles/data/001/illust_123.illust"
    }
}
```

### 7. 一時保存 API
**POST** `/admin/paint/api/autosave`

#### リクエスト
```json
{
    "session_id": "sess_abc123",
    "illust_data": "data:application/json;base64,...",  // .illustファイル内容
    "timelapse_data": "data:application/octet-stream;base64,..."  // タイムラプスデータ
}
```

#### レスポンス
```json
{
    "success": true,
    "data": {
        "temp_id": "temp_456",
        "expires_at": "2024-01-01 13:00:00"
    }
}
```

### 8. 設定取得 API
**GET** `/admin/paint/api/settings`

#### レスポンス
```json
{
    "success": true,
    "data": {
        "canvas": {
            "max_width": 4096,
            "max_height": 4096,
            "default_width": 800,
            "default_height": 600,
            "background_color": "#FFFFFF"
        },
        "history": {
            "max_steps": 50
        },
        "timelapse": {
            "max_size": 52428800,
            "enabled": true
        },
        "files": {
            "max_image_size": 10485760,
            "max_illust_size": 20971520,
            "max_temp_size": 5242880
        },
        "palette": {
            "presets": {
                "default": ["#000000", "#FFFFFF", ...],
                "warm": ["#FF6B35", "#F7931E", ...]
            }
        },
        "layers": {
            "max_count": 4,
            "default_layers": [
                {"name": "背景", "visible": true, "opacity": 1.0}
            ]
        }
    }
}
```

### 9. 画像取得 API (管理機能用)
**GET** `/admin/paint/api/image/{illust_id}`

#### パラメータ
- `illust_id`: イラストID (必須)
- `type`: 画像タイプ (thumbnail/export, デフォルト: export)

#### 説明
管理機能での画像アクセス用。公開ビューでは直接URLアクセスを使用。

#### レスポンス
- 画像ファイルを直接ストリーミング返却
- Content-Type: image/png or image/jpeg
- キャッシュヘッダー付与

### 10. タイムラプス取得 API
**GET** `/admin/paint/api/timelapse/{illust_id}`

#### パラメータ
- `illust_id`: イラストID (必須)
- `chunk`: チャンクインデックス (オプション、デフォルト: 0)

#### レスポンス
- タイムラプスデータをストリーミング返却
- Content-Type: application/octet-stream
- Accept-Ranges: bytes (レンジリクエスト対応)

### 11. 作品データ取得 API
**GET** `/admin/paint/api/data/{illust_id}`

#### パラメータ
- `illust_id`: イラストID (必須)

#### レスポンス
- .illustファイルの内容をJSONで返却
- Content-Type: application/json

### 作品公開一覧 API
**GET** `/api/paint/list`

#### パラメータ
- `page`: ページ番号
- `limit`: 件数

#### レスポンス
```json
{
    "success": true,
    "data": {
        "illusts": [
            {
                "id": 123,
                "title": "作品タイトル",
                "thumbnail_url": "/uploads/paintfiles/images/001/illust_123_thumb.webp",
                "image_url": "/uploads/paintfiles/images/001/illust_123.png",
                "created_at": "2024-01-01 12:00:00"
            }
        ],
        "pagination": {...}
    }
}
```

### タイムラプス再生 API
**GET** `/api/paint/timelapse/{id}`

#### レスポンス
- タイムラプスデータをストリーミングで返す
- 認証不要 (公開作品のみ)

## エラーハンドリング

### 共通エラー
- `401`: 未認証
- `403`: 権限なし
- `404`: リソースなし
- `429`: レート制限超過
- `500`: 内部サーバーエラー

### バリデーションエラー
```json
{
    "success": false,
    "error": "バリデーションエラー",
    "code": 400,
    "details": {
        "title": "タイトルは必須です",
        "canvas_width": "キャンバス幅は1-4096の範囲で指定してください"
    }
}
```

## レート制限
- **保存API**: 1分間に5回
- **読み込みAPI**: 1分間に30回
- **一覧API**: 1分間に10回

## ファイルアップロード
- **最大サイズ**: 画像10MB, タイムラプス50MB
- **許可タイプ**: PNG, JPG, WEBP, msgpack.gz
- **保存先**: `uploads/paintfiles/`