<?php
/**
 * イラストタグ一覧取得API
 * public/paint/ 専用
 */

require_once(__DIR__ . '/../../../vendor/autoload.php');
$config = \App\Config\ConfigManager::getInstance()->getConfig();

use App\Controllers\PublicControllerBase;

class TagsPublicController extends PublicControllerBase
{
    protected bool $startSession = false;
    protected bool $allowCors = false;

    protected function onProcess(string $method): void
    {
        try {
            $db = \App\Database\Connection::getInstance();

            // 現在はillust_tagsテーブルがないため、空の配列を返す
            $tags = [];

            $this->sendSuccess(['tags' => $tags]);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
}

try {
    $controller = new TagsPublicController();
    $controller->execute();
} catch (Exception $e) {
    PublicControllerBase::handleException($e);
}
