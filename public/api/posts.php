<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Cache\CacheManager;
use App\Models\Post;
use App\Models\GroupPost;
use App\Security\RateLimiter;

// CORSヘッダー（必要に応じて）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Content-Type: application/json; charset=utf-8');

// レート制限（1分間に100リクエストまで）
$rateLimiter = new RateLimiter(__DIR__ . '/../../data/rate-limits', 100, 60);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!$rateLimiter->check($clientIp, 'api_posts')) {
    http_response_code(429);
    $retryAfter = $rateLimiter->getRetryAfter($clientIp, 'api_posts');
    if ($retryAfter) {
        header('Retry-After: ' . ($retryAfter - time()));
    }
    echo json_encode(['success' => false, 'error' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rateLimiter->record($clientIp, 'api_posts');

try {
    $postModel = new Post();

    // 単一の投稿を取得する場合
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $postId = (int)$_GET['id'];
        $post = $postModel->getById($postId);

        if ($post === null) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => '投稿が見つかりません'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // JSONレスポンスを生成
        $response = [
            'success' => true,
            'post' => $post
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // すべての投稿を取得する場合
    // フィルタパラメータを取得
    $nsfwFilter = $_GET['nsfw_filter'] ?? 'all'; // all, safe, nsfw
    $tagId = isset($_GET['tagId']) && is_numeric($_GET['tagId']) ? (int)$_GET['tagId'] : null;

    // ページネーションパラメータを取得（セキュリティ対策で上限を設定）
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 30) : 18;
    $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

    // キャッシュマネージャーを初期化
    $cache = new CacheManager();

    // フィルタやページネーションがある場合はキャッシュを使用しない
    $useCache = ($nsfwFilter === 'all' && $tagId === null && $offset === 0 && $limit === 18);

    // キャッシュが存在する場合は即返却（超高速）
    if ($useCache && $cache->has('posts_list')) {
        $cachedData = $cache->readRaw('posts_list');
        if ($cachedData !== null) {
            echo $cachedData;
            exit;
        }
    }

    // キャッシュが無い場合またはフィルタがある場合はDBから取得
    // シングル投稿とグループ投稿の両方を取得
    $singlePosts = $postModel->getAll($limit, $nsfwFilter, $tagId, 0);

    $groupPostModel = new GroupPost();
    $groupPosts = $groupPostModel->getAll($limit, $nsfwFilter, $tagId, 0);

    // 両方をマージしてsort_order、作成日時でソート
    $allPosts = array_merge($singlePosts, $groupPosts);
    usort($allPosts, function($a, $b) {
        // まずsort_orderで比較（降順：大きい方が先）
        $sortOrderDiff = ($b['sort_order'] ?? 0) - ($a['sort_order'] ?? 0);
        if ($sortOrderDiff !== 0) {
            return $sortOrderDiff;
        }
        // sort_orderが同じなら作成日時で比較（降順：新しい方が先）
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // 各投稿にタイプを追加
    foreach ($allPosts as &$post) {
        // image_countがあればグループ投稿
        if (isset($post['image_count'])) {
            $post['post_type'] = 'group';
        } else {
            $post['post_type'] = 'single';
        }
    }

    // オフセットと件数制限を適用
    $posts = array_slice($allPosts, $offset, $limit);

    // JSONレスポンスを生成
    $response = [
        'success' => true,
        'count' => count($posts),
        'posts' => $posts
    ];

    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // フィルタがない場合のみキャッシュに保存
    if ($useCache) {
        $cache->set('posts_list', $response);
    }

    // レスポンス出力
    echo $json;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ], JSON_UNESCAPED_UNICODE);

    // エラーログに記録
    error_log('API Error (posts.php): ' . $e->getMessage());
}
