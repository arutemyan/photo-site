<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Security\CsrfProtection;
use App\Database\Connection;

/**
 * Color Palette API
 * カラーパレットの取得・更新
 */
class PaletteController extends AdminControllerBase
{
    private bool $isAdmin = false;

    protected function onProcess(string $method): void
    {
        switch ($method) {
            case 'GET':
                $this->handleGet();
                break;
            case 'POST':
                $this->handlePost();
                break;
            default:
                $this->sendError('Method not allowed', 405);
        }
    }

    private function handleGet(): void
    {
        $db = Connection::getInstance();
        
        $stmt = $db->prepare("
            SELECT slot_index, color 
            FROM color_palettes 
            WHERE user_id IS NULL 
            ORDER BY slot_index ASC
        ");
        $stmt->execute();
        $palette = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to simple array
        $colors = array_fill(0, 16, '#000000');
        foreach ($palette as $row) {
            $index = (int)$row['slot_index'];
            if ($index >= 0 && $index < 16) {
                $colors[$index] = $row['color'];
            }
        }
        
        $this->sendSuccess(['colors' => $colors]);
    }

    private function handlePost(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $slotIndex = (int)($input['slot_index'] ?? -1);
        $color = strtoupper($input['color'] ?? '');
        
        if ($slotIndex < 0 || $slotIndex >= 16) {
            $this->sendError('Invalid slot index', 400);
        }
        
        if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
            $this->sendError('Invalid color format', 400);
        }
        
        $db = Connection::getInstance();
        
        // Get driver type
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $stmt = $db->prepare("
                INSERT INTO color_palettes (user_id, slot_index, color, updated_at)
                VALUES (NULL, ?, ?, datetime('now'))
                ON CONFLICT(user_id, slot_index) 
                DO UPDATE SET color = ?, updated_at = datetime('now')
            ");
            $stmt->execute([$slotIndex, $color, $color]);
        } else {
            // MySQL
            $stmt = $db->prepare("
                INSERT INTO color_palettes (user_id, slot_index, color, updated_at)
                VALUES (NULL, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE color = ?, updated_at = NOW()
            ");
            $stmt->execute([$slotIndex, $color, $color]);
        }
        
        $this->sendSuccess([]);
    }
}

// コントローラーを実行
$controller = new PaletteController();
$controller->execute();
