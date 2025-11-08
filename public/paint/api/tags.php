<?php
/**
 * イラストタグ一覧取得API
 * public/paint/ 専用
 */

require_once(__DIR__ . '/../../../vendor/autoload.php');
$config = \App\Config\ConfigManager::getInstance()->getConfig();

header('Content-Type: application/json');

try {
    $db = \App\Database\Connection::getInstance();
    
    // 現在はillust_tagsテーブルがないため、空の配列を返す
    $tags = [];
    
    /*
    // イラストに関連付けられているタグのみを取得（使用頻度順）
    $sql = "SELECT 
                t.id,
                t.name,
                COUNT(it.paint_id) as count
            FROM tags t
            INNER JOIN illust_tags it ON t.id = it.tag_id
            GROUP BY t.id, t.name
            ORDER BY count DESC, t.name ASC";
    
    $stmt = $db->query($sql);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    */
    
    echo json_encode([
        'success' => true,
        'tags' => $tags
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    \App\Utils\Logger::getInstance()->error('Tags API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ], JSON_UNESCAPED_UNICODE);
}
