# Contributing to photo-site

ありがとうございます！このプロジェクトに貢献していただけるのは大変ありがたいです。
以下は、開発・テスト・PR の流れ、そしてプロジェクトが依存する OSS への支援（funding）についての簡単な案内です。

## 目次
- 開発環境の準備
- テストの実行
- CI / DB マトリクスについて
- マイグレーションの実行
- PR の作り方とルール
- Funding（寄付・支援）

---

## 開発環境の準備
1. リポジトリをクローンし、依存をインストールします。

```bash
git clone <repo-url>
cd photo-site
composer install
```

2. 開発時は PHP 8.1+ を推奨しています。ローカルに PHP がない場合は Docker を利用してください。

## テストの実行
ユニット/統合テストは PHPUnit で管理されています。簡単な実行方法:

- 全テスト（ローカル sqlite の場合）

```bash
vendor/bin/phpunit --configuration phpunit.xml
```

- CI と同じように環境変数で DB を切り替える例（MySQL）

```bash
export TEST_DB_DRIVER=mysql
export TEST_DB_HOST=127.0.0.1
export TEST_DB_PORT=3306
export TEST_DB_NAME=testdb
export TEST_DB_USER=root
export TEST_DB_PASS=root
php public/setup/run_migrations.php
vendor/bin/phpunit --configuration phpunit.xml.dist
```

（CI は `phpunit.xml.dist` を使い、`TEST_DB_*` 環境変数で DB を切り替えます。）

## CI / DB マトリクスについて
- GitHub Actions で sqlite / MySQL / PostgreSQL のマトリクスを回す設定があります。
- ローカルで CI 相当を動かすには `act` を使うか、Docker Compose を用いて DB を立ち上げ、上記の `TEST_DB_*` 環境変数を渡してください。

## マイグレーションの実行
- CI では `php public/setup/run_migrations.php` を実行して DB をセットアップします。
- ローカルでも同じコマンドを使ってください（`TEST_DB_*` を必要に応じて設定）。

## PR の作り方とルール
- ブランチ名: `feature/<短い説明>` または `fix/<短い説明>`
- 変更は小さく、1つの PR に 1 つの目的を含めてください。
- テストがある変更は必ずテストを追加してください（Unit または Integration）。
- 可能であれば自己レビューを行い、コミットメッセージは要点を含めてください。
- マージ前に CI がすべて通ること（必須）

## Funding（このプロジェクトが依存しているOSSへの支援）
このリポジトリは多数のオープンソースライブラリに依存しています。`composer fund` コマンドで依存パッケージが公開している支援ページが確認できます。プロジェクト内で `composer fund` を実行した結果、主に以下のパッケージが funding 情報を公開しています（抜粋）：

- sebastianbergmann (phpunit / sebastian/* 関連)
  - https://github.com/sponsors/sebastianbergmann
  - https://liberapay.com/sebastianbergmann
  - https://thanks.dev/u/gh/sebastianbergmann
- PHPUnit 関連
  - https://phpunit.de/sponsors.html
  - https://tidelift.com/funding/github/packagist/phpunit/phpunit
- theseer (phar-io / tokenizer)
  - https://github.com/sponsors/theseer
- myclabs/deep-copy
  - https://tidelift.com/funding/github/packagist/myclabs/deep-copy

もし関心があれば、`composer fund` を実行してみてください。依存パッケージの作者に感謝と支援を示す良い方法です。

---

### 追加の提案
- CONTRIBUTING に開発環境の Docker Compose の例を追加することもできます（要望があれば作成します）。
- CI が重くなってきたら、PR レベルは sqlite+unit のみ必須、merge 前に MySQL/Postgres を走らせる運用を提案します。

ご希望ならこの `CONTRIBUTING.md` をそのままコミットして push します（私は今からコミットして反映します）。
