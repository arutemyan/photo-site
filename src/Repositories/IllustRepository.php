<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * DB操作専門クラス（Illustデータ）
 */
class IllustRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * 新規イラストレコードを作成
     *
     * @return int 作成されたID
     */
    public function create(int $userId, string $title, int $nsfw, int $isVisible, ?string $artistName): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO paint (user_id, title, nsfw, is_visible, artist_name)
             VALUES (:user_id, :title, :nsfw, :is_visible, :artist_name)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':nsfw' => $nsfw,
            ':is_visible' => $isVisible,
            ':artist_name' => $artistName
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * イラストレコードを更新
     */
    public function update(
        int $id,
        string $title,
        string $description,
        string $tags,
        string $dataPath,
        ?string $imagePath,
        ?string $thumbnailPath,
        ?string $timelapsePath,
        int $timelapseSize,
        int $fileSize,
        int $nsfw,
        int $isVisible,
        ?string $artistName
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE paint SET
                title = :title,
                description = :description,
                tags = :tags,
                data_path = :data_path,
                image_path = :image_path,
                thumbnail_path = :thumbnail_path,
                timelapse_path = :timelapse_path,
                timelapse_size = :timelapse_size,
                file_size = :file_size,
                nsfw = :nsfw,
                is_visible = :is_visible,
                artist_name = :artist_name
             WHERE id = :id'
        );

        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':tags' => $tags,
            ':data_path' => $dataPath,
            ':image_path' => $imagePath,
            ':thumbnail_path' => $thumbnailPath,
            ':timelapse_path' => $timelapsePath,
            ':timelapse_size' => $timelapseSize,
            ':file_size' => $fileSize,
            ':nsfw' => $nsfw,
            ':is_visible' => $isVisible,
            ':artist_name' => $artistName,
            ':id' => $id,
        ]);
    }

    /**
     * IDでイラストを検索
     *
     * @return array|null レコード、または存在しない場合null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM paint WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * トランザクション開始
     */
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * コミット
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * ロールバック
     */
    public function rollback(): void
    {
        $this->db->rollBack();
    }
}
