````markdown
# リリースガイド

PixuGallery のリリースパッケージを作成する方法

## リリース作成

`.gitattributes`の設定に基づいて自動的にテストファイルなどを除外します。

```bash
# 最新コミットからリリース作成（日付ベースのバージョン）
./create-release.sh

# バージョン番号を指定
./create-release.sh v1.0.0

# 日付指定
./create-release.sh 2024-10-26
```

## リリース前のチェックリスト

1. **テストの実行**
   ```bash
   vendor/bin/phpunit
   ```

2. **変更をコミット**
   ```bash
   git add .
   git commit -m "Release v1.0.0"
   ```

3. **タグを作成（推奨）**
   ```bash
   git tag -a v1.0.0 -m "Release version 1.0.0"
   ```

4. **リリースパッケージ作成**
   ```bash
   ./create-release.sh v1.0.0
   ```

5. **パッケージの確認**
   ```bash
   tar -tzf releases/pixugallery-v1.0.0.tar.gz | less
   ```

## タグからリリースを作成

特定のGitタグからリリースを作成する場合：

```bash
# タグを指定してarchive
git archive --format=tar.gz \
    --prefix=pixugallery-v1.0.0/ \
    --output=releases/pixugallery-v1.0.0.tar.gz \
    v1.0.0
```

## 除外されるファイル/ディレクトリ

`.gitattributes`で以下が自動的に除外されます：

- `tests/` - テストコード
- `.github/` - GitHub Actions
- `.phpunit.cache/` - PHPUnitキャッシュ
- `phpunit.xml` - PHPUnit設定
- `create-release.sh` - リリーススクリプト
- `secure_directories.php` - セットアップスクリプト
- `.gitignore`, `.gitattributes` - Git設定

## デプロイ手順

1. **サーバーでパッケージを展開**
   ```bash
   tar -xzf pixugallery-v1.0.0.tar.gz
   cd pixugallery-v1.0.0
   ```

2. **依存関係をインストール**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **ディレクトリ保護を実行**
   ```bash
   php secure_directories.php
   ```

4. **パーミッション設定**
   ```bash
   chmod 755 public
   chmod 777 data cache logs
   chmod 644 public/uploads
   ```

5. **設定ファイルの調整**
   - `config/config.php` - データベースパス、セキュリティ設定
   - `config/nsfw.php` - NSFW設定
   - 管理者パスワードの変更

## バージョン管理のベストプラクティス

### セマンティックバージョニング推奨

- **v1.0.0** - メジャーバージョン（破壊的変更）
- **v1.1.0** - マイナーバージョン（新機能追加）
- **v1.1.1** - パッチバージョン（バグフィックス）

### タグの作成例

```bash
# 新機能リリース
git tag -a v1.1.0 -m "Add image overlay navigation feature"

# バグフィックス
git tag -a v1.0.1 -m "Fix tag click event propagation"

# タグをリモートにプッシュ
git push origin v1.1.0
```

## トラブルシューティング

### リリースパッケージが大きすぎる場合

1. `.gitattributes`の除外設定を確認
2. `vendor/`が含まれていないか確認（通常は除外される）
3. `uploads/`ディレクトリが含まれていないか確認

### 必要なファイルが含まれていない場合

`.gitattributes`の`export-ignore`設定を見直してください。

````
