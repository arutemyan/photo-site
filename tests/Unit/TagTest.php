<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Tag;
use App\Database\Connection;

/**
 * Tagモデルのテストクラス
 * 
 * タグ検索の入力検証とLIKEワイルドカードエスケープをテスト
 */
final class TagTest extends TestCase
{
    private \PDO $pdo;
    private Tag $tagModel;
    private string $testDbPath;

    /**
     * 各テストメソッドの前に実行
     * テスト用のSQLiteデータベースを作成し、マイグレーションを実行
     */
    protected function setUp(): void
    {
        parent::setUp();

        // テスト用の一時DBファイル
        $this->testDbPath = sys_get_temp_dir() . '/test_tag_' . uniqid() . '.db';

        // テスト用データベースの設定
        Connection::setDatabasePath($this->testDbPath);
        $this->pdo = Connection::getInstance();

        // マイグレーションを明示的に実行してスキーマを作成
        $migrationRunner = Connection::getMigrationRunner();
        $migrationRunner->run();

        // Tagモデルのインスタンスを作成
        $this->tagModel = new Tag();
    }

    /**
     * 各テストメソッドの後に実行
     * テスト用のデータベースをクリーンアップ
     */
    protected function tearDown(): void
    {
        Connection::close();
        if (file_exists($this->testDbPath)) {
            @unlink($this->testDbPath);
        }
        parent::tearDown();
    }

    /**
     * 空文字列の入力を拒否することをテスト
     */
    public function testSearchByNameRejectsEmptyString(): void
    {
        // テストデータの投入
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('test'), ('example')");

        // 空文字列で検索
        $results = $this->tagModel->searchByName('', false);

        // 空配列が返されることを確認
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * 空白文字のみの入力を拒否することをテスト
     */
    public function testSearchByNameRejectsWhitespaceOnly(): void
    {
        // テストデータの投入
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('test'), ('example')");

        // 空白文字のみで検索
        $results = $this->tagModel->searchByName('   ', false);

        // 空配列が返されることを確認
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * 100文字を超える入力を拒否することをテスト
     */
    public function testSearchByNameRejectsTooLongInput(): void
    {
        // テストデータの投入
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('test'), ('example')");

        // 101文字の文字列で検索
        $longString = str_repeat('a', 101);
        $results = $this->tagModel->searchByName($longString, false);

        // 空配列が返されることを確認
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * 100文字ちょうどの入力は受け入れることをテスト
     */
    public function testSearchByNameAccepts100Characters(): void
    {
        // 100文字のタグ名を作成
        $exactlyHundred = str_repeat('a', 100);
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('" . $exactlyHundred . "')");

        // 100文字の文字列で検索
        $results = $this->tagModel->searchByName($exactlyHundred, false);

        // 結果が返されることを確認
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals($exactlyHundred, $results[0]['name']);
    }

    /**
     * %を含むタグ名が正しく検索されることをテスト
     * LIKEワイルドカードがエスケープされていることを確認
     */
    public function testSearchEscapesPercentWildcard(): void
    {
        // テストデータの投入：%を含むタグと通常のタグ
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('100%cool'), ('percent_test'), ('coolthings')");

        // %を含むタグ名で検索
        $results = $this->tagModel->searchByName('100%cool', false);

        // 1件のみヒットすることを確認（ワイルドカードとして機能していない）
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertStringContainsString('100%cool', $results[0]['name']);
    }

    /**
     * _を含むタグ名が正しく検索されることをテスト
     * LIKEワイルドカードがエスケープされていることを確認
     */
    public function testSearchEscapesUnderscoreWildcard(): void
    {
        // テストデータの投入：_を含むタグと類似のタグ
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('under_score'), ('under-score'), ('underscore')");

        // _を含むタグ名で検索
        $results = $this->tagModel->searchByName('under_score', false);

        // 1件のみヒットすることを確認（_がワイルドカードとして機能していない）
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('under_score', $results[0]['name']);
    }

    /**
     * 複数のワイルドカードを含むタグ名が正しく検索されることをテスト
     */
    public function testSearchEscapesMultipleWildcards(): void
    {
        // テストデータの投入：複数のワイルドカードを含むタグ
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('100%_test'), ('100test'), ('100-_test')");

        // 複数のワイルドカードを含むタグ名で検索
        $results = $this->tagModel->searchByName('100%_test', false);

        // 1件のみヒットすることを確認
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('100%_test', $results[0]['name']);
    }

    /**
     * 通常の部分一致検索が正しく機能することをテスト
     */
    public function testSearchByNamePartialMatch(): void
    {
        // テストデータの投入
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('test'), ('testing'), ('contest')");

        // 部分一致検索
        $results = $this->tagModel->searchByName('test', false);

        // 3件ヒットすることを確認（test, testing, contest）
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
    }

    /**
     * trim処理が正しく機能することをテスト
     */
    public function testSearchByNameTrimsInput(): void
    {
        // テストデータの投入
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('trimtest')");

        // 前後に空白がある検索文字列
        $results = $this->tagModel->searchByName('  trimtest  ', false);

        // 正しくヒットすることを確認
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('trimtest', $results[0]['name']);
    }

    /**
     * 大文字小文字を区別せずに検索できることをテスト（SQLiteの場合）
     */
    public function testSearchByNameCaseInsensitive(): void
    {
        // テストデータの投入
        $this->pdo->exec("INSERT INTO tags (name) VALUES ('TestTag')");

        // 小文字で検索
        $results = $this->tagModel->searchByName('testtag', false);

        // SQLiteのデフォルトはCASE INSENSITIVEなのでヒットする
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }
}
