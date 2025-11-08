<?php
/**
 * Paint一覧取得API
 * public/paint/ 専用
 */

require_once(__DIR__ . '/../../../vendor/autoload.php');
require_once(__DIR__ . '/../../../config/config.php');
// feature gate
require_once(__DIR__ . '/../_feature_check.php');

header('Content-Type: application/json');

try {
    $db = \App\Database\Connection::getInstance();
    
    // パラメータ取得
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $tag = isset($_GET['tag']) ? trim($_GET['tag']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    
    $limit = max(1, min($limit, 100)); // 1-100の範囲
    $offset = max(0, $offset);
    
    // ベースクエリ
    $sql = "SELECT 
                i.id,
                i.title,
                '' as detail,
                i.image_path,
                i.thumbnail_path as thumb_path,
                i.data_path,
                i.timelapse_path,
                i.canvas_width as width,
                i.canvas_height as height,
                i.created_at,
                i.updated_at,
                '' as tags
            FROM paint i";
    
    $where = [];
    $params = [];
    
    // タグフィルター（現在はタグテーブルがないのでスキップ）
    /*
    if ($tag) {
        $sql .= " LEFT JOIN illust_tags it2 ON i.id = it2.paint_id
                  LEFT JOIN tags t2 ON it2.tag_id = t2.id";
        $where[] = "t2.name = :tag";
        $params[':tag'] = $tag;
    }
    */
    
    // 検索フィルター
    if ($search) {
        $where[] = "i.title LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $sql .= " ORDER BY i.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $paints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総数を取得
    $countSql = "SELECT COUNT(i.id) as total FROM paint i";
    /*
    if ($tag) {
        $countSql .= " LEFT JOIN illust_tags it ON i.id = it.paint_id
                       LEFT JOIN tags t ON it.tag_id = t.id";
    }
    */
    if (!empty($where)) {
        $countSql .= " WHERE " . implode(' AND ', $where);
    }
    
    $countStmt = $db->prepare($countSql);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $countStmt->bindValue($key, $value);
        }
    }
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'paint' => $paints,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    \App\Utils\Logger::getInstance()->error('Paints API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ], JSON_UNESCAPED_UNICODE);
}
