<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Paint
{
    public int $id;
    public int $user_id;
    public string $title = '';
    public string $description = '';
    public string $tags = '';
    public int $canvas_width = 800;
    public int $canvas_height = 600;
    public string $background_color = '#FFFFFF';
    public ?string $data_path = null;
    public ?string $image_path = null;
    public ?string $thumbnail_path = null;
    public ?string $timelapse_path = null;
    public int $timelapse_size = 0;
    public int $file_size = 0;
    public string $status = 'draft';
    public string $created_at;
    public string $updated_at;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO paint (user_id, title, description, tags, canvas_width, canvas_height, background_color, data_path, image_path, thumbnail_path, timelapse_path, timelapse_size, file_size, status) VALUES (:user_id, :title, :description, :tags, :canvas_width, :canvas_height, :background_color, :data_path, :image_path, :thumbnail_path, :timelapse_path, :timelapse_size, :file_size, :status)'
        );
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':title' => $data['title'] ?? '',
            ':description' => $data['description'] ?? '',
            ':tags' => $data['tags'] ?? '',
            ':canvas_width' => $data['canvas_width'] ?? 800,
            ':canvas_height' => $data['canvas_height'] ?? 600,
            ':background_color' => $data['background_color'] ?? '#FFFFFF',
            ':data_path' => $data['data_path'] ?? null,
            ':image_path' => $data['image_path'] ?? null,
            ':thumbnail_path' => $data['thumbnail_path'] ?? null,
            ':timelapse_path' => $data['timelapse_path'] ?? null,
            ':timelapse_size' => $data['timelapse_size'] ?? 0,
            ':file_size' => $data['file_size'] ?? 0,
            ':status' => $data['status'] ?? 'draft',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM paint WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listByUser(int $user_id, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare('SELECT * FROM paint WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
