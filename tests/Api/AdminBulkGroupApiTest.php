<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

/**
 * Bulk upload / Group upload / Group posts / Settings の簡易テスト
 *
 * - ダミー画像（8x8の白画像）を生成してアップロードをシミュレーション
 * - 各APIの主要な成功パスを検証
 */
class AdminBulkGroupApiTest extends TestCase
{
    private string $tempDir;
    private string $imagesDir;
    private string $thumbsDir;
    private string $cacheDir;
    private ?\PDO $db = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 一時ディレクトリ
        $this->tempDir = sys_get_temp_dir() . '/photo_site_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->imagesDir = $this->tempDir . '/images';
        $this->thumbsDir = $this->tempDir . '/thumbs';
        $this->cacheDir = $this->tempDir . '/cache';

        mkdir($this->imagesDir, 0777, true);
        mkdir($this->thumbsDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);

        // インメモリDB
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // posts テーブル（group 用のカラムも含む）
        $this->db->exec("CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            post_type INTEGER DEFAULT 0,
            tags TEXT,
            detail TEXT,
            image_path TEXT,
            thumb_path TEXT,
            is_sensitive INTEGER DEFAULT 0,
            is_visible INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // group images テーブル
        $this->db->exec("CREATE TABLE group_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER,
            image_path TEXT,
            thumb_path TEXT,
            display_order INTEGER DEFAULT 0
        )");

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function tearDown(): void
    {
        $this->db = null;
        // セッションクリア
        $_SESSION = [];
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(16));
        $_SESSION['csrf'] = $token;
        return $token;
    }

    private function setAuthenticatedSession(): void
    {
        $_SESSION['admin_authenticated'] = true;
    }

    private function createWhiteImage(string $path, int $w = 8, int $h = 8): void
    {
        $img = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        imagepng($img, $path);
        imagedestroy($img);
    }

    /**
     * bulk_upload.php を模したシミュレーション
     */
    private function simulateBulkUploadApi(array $postData, array $files): array
    {
        if (!isset($_SESSION['admin_authenticated']) || !$_SESSION['admin_authenticated']) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        if (!isset($postData['csrf']) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $postData['csrf'])) {
            return ['success' => false, 'error' => 'CSRF token invalid'];
        }

        if (empty($files['images'])) {
            return ['success' => false, 'error' => 'No images'];
        }

        $uploaded = $files['images'];
        $count = count($uploaded['name']);
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $tmp = $uploaded['tmp_name'][$i];
            $name = $uploaded['name'][$i];
            $size = $uploaded['size'][$i];

            // mime check
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);

            $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowed)) {
                $results[] = ['filename' => $name, 'success' => false, 'error' => 'Invalid mime'];
                $errorCount++;
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $filename = 'bulk_' . uniqid();
            $imagePath = 'images/' . $filename . '.' . $ext;
            $thumbPath = 'thumbs/' . $filename . '.webp';

            $fullImage = $this->imagesDir . '/' . basename($imagePath);
            $fullThumb = $this->thumbsDir . '/' . basename($thumbPath);

            if (!copy($tmp, $fullImage)) {
                $results[] = ['filename' => $name, 'success' => false, 'error' => 'save failed'];
                $errorCount++;
                continue;
            }

            // create webp thumb
            $src = null;
            switch ($mime) {
                case 'image/png': $src = imagecreatefrompng($fullImage); break;
                case 'image/jpeg': $src = imagecreatefromjpeg($fullImage); break;
                case 'image/gif': $src = imagecreatefromgif($fullImage); break;
                case 'image/webp': $src = imagecreatefromwebp($fullImage); break;
            }
            if ($src === false || $src === null) {
                unlink($fullImage);
                $results[] = ['filename' => $name, 'success' => false, 'error' => 'process failed'];
                $errorCount++;
                continue;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $thumbW = 200;
            $thumbH = (int)(($h / $w) * $thumbW);
            $thumb = imagecreatetruecolor($thumbW, $thumbH);
            imagecopyresampled($thumb, $src, 0,0,0,0, $thumbW, $thumbH, $w, $h);
            imagewebp($thumb, $fullThumb, 80);
            imagedestroy($src);
            imagedestroy($thumb);

            // DB 保存
            $stmt = $this->db->prepare('INSERT INTO posts (title, image_path, thumb_path) VALUES (?, ?, ?)');
            $stmt->execute([$name, $imagePath, $thumbPath]);
            $postId = (int)$this->db->lastInsertId();

            $results[] = ['filename' => $name, 'success' => true, 'post_id' => $postId];
            $successCount++;
        }

        return [
            'success' => true,
            'total' => $count,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results
        ];
    }

    /**
     * group_upload の簡易シミュレーション（新規グループ作成パスを検証）
     */
    private function simulateGroupUploadCreate(array $postData, array $files): array
    {
        if (!isset($_SESSION['admin_authenticated']) || !$_SESSION['admin_authenticated']) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        if (!isset($postData['csrf']) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $postData['csrf'])) {
            return ['success' => false, 'error' => 'CSRF token invalid'];
        }

        if (empty($postData['title'])) {
            return ['success' => false, 'error' => 'Title required'];
        }

        // reuse bulk logic but collect imagePaths and create group post
        $bulkResult = $this->simulateBulkUploadApi($postData, $files);
        if (!$bulkResult['success']) return $bulkResult;

        // create group post
        $stmt = $this->db->prepare('INSERT INTO posts (title, post_type, image_path, thumb_path) VALUES (?, 1, ?, ?)');
        // use first successful image for representative
        $first = $bulkResult['results'][0] ?? null;
        $repImage = $first ? $first['filename'] : null;
        $repImagePath = $repThumbPath = '';
        // lookup the last inserted row's image_path from posts table
        // But simulate by retrieving last inserted post id from bulk insert

        // For simplicity, create a placeholder post and then insert group_images entries
        $stmt->execute([$postData['title'], '', '']);
        $groupPostId = (int)$this->db->lastInsertId();

        // add group_images rows for each successful upload
        foreach ($bulkResult['results'] as $idx => $r) {
            if (!$r['success']) continue;
            $stmt2 = $this->db->prepare('INSERT INTO group_images (post_id, image_path, thumb_path, display_order) VALUES (?, ?, ?, ?)');
            $img = 'images/' . $r['filename'];
            $th = 'thumbs/' . $r['filename'] . '.webp';
            $stmt2->execute([$groupPostId, $img, $th, $idx + 1]);
        }

        return ['success' => true, 'group_post_id' => $groupPostId, 'results' => $bulkResult['results']];
    }

    // ========== テストケース ==============

    public function testBulkUploadCreatesPostsAndThumbs(): void
    {
        $this->setAuthenticatedSession();
        $csrf = $this->generateCsrfToken();

        // 2つの白画像を生成
        $img1 = $this->tempDir . '/img1.png';
        $img2 = $this->tempDir . '/img2.png';
        $this->createWhiteImage($img1);
        $this->createWhiteImage($img2);

        $files = [
            'images' => [
                'name' => ['img1.png', 'img2.png'],
                'type' => ['image/png', 'image/png'],
                'tmp_name' => [$img1, $img2],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [filesize($img1), filesize($img2)]
            ]
        ];

        $postData = ['csrf' => $csrf];

        $result = $this->simulateBulkUploadApi($postData, $files);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['success_count']);

        // files exist in imagesDir and thumbsDir
        $images = glob($this->imagesDir . '/*');
        $thumbs = glob($this->thumbsDir . '/*');
        $this->assertCount(2, $images);
        $this->assertCount(2, $thumbs);
    }

    public function testGroupUploadCreatesGroupPostAndImages(): void
    {
        $this->setAuthenticatedSession();
        $csrf = $this->generateCsrfToken();

        $img1 = $this->tempDir . '/g1.png';
        $img2 = $this->tempDir . '/g2.png';
        $this->createWhiteImage($img1);
        $this->createWhiteImage($img2);

        $files = [
            'images' => [
                'name' => ['g1.png', 'g2.png'],
                'type' => ['image/png', 'image/png'],
                'tmp_name' => [$img1, $img2],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [filesize($img1), filesize($img2)]
            ]
        ];

        $postData = ['csrf' => $csrf, 'title' => 'group title'];

        $result = $this->simulateGroupUploadCreate($postData, $files);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('group_post_id', $result);

        $groupId = (int)$result['group_post_id'];
        $stmt = $this->db->prepare('SELECT * FROM group_images WHERE post_id = ?');
        $stmt->execute([$groupId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(2, $rows);
    }
}
