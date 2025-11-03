<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';

use App\Database\Connection;
use App\Services\IllustService;

$db = Connection::getInstance();
$uploads = __DIR__ . '/../../uploads';
$service = new IllustService($db, $uploads);

// minimal .illust JSON
$illust = [
    'version' => '1.0',
    'metadata' => [
        'canvas_width' => 16,
        'canvas_height' => 16,
        'background_color' => '#FFFFFF'
    ],
    'layers' => [
        ['id' => 'layer_0', 'name' => 'bg', 'order' => 0, 'visible' => true, 'opacity' => 1.0, 'type' => 'raster', 'data' => '', 'width' => 16, 'height' => 16]
    ],
    'timelapse' => ['enabled' => false]
];

$illustJson = json_encode($illust);

// 1x1 PNG data URI
$imageData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==';

// tiny gz data
$timelapseBin = gzencode('');

try {
    $res = $service->save([
        'user_id' => 1,
        'title' => 'test save',
        'canvas_width' => 16,
        'canvas_height' => 16,
        'background_color' => '#FFFFFF',
        'illust_json' => $illustJson,
        'image_data' => $imageData,
        'timelapse_data' => $timelapseBin,
    ]);

    echo "Save result:\n";
    print_r($res);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
