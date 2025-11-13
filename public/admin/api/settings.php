<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Controllers\AdminControllerBase;
use App\Models\Setting;

class SettingsController extends AdminControllerBase
{
    private Setting $settingModel;

    public function __construct()
    {
        $this->settingModel = new Setting();
    }

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
                $this->sendError('許可されていないメソッドです', 405);
        }
    }

    private function handleGet(): void
    {
        $settings = $this->settingModel->getAll();
        // Model returns rows with 'setting_key' and 'setting_value'.
        // Convert to legacy API shape { key, value } expected by admin JS.
        $out = [];
        foreach ($settings as $row) {
            $out[] = [
                'key' => $row['setting_key'],
                'value' => $row['setting_value'],
            ];
        }
        $this->sendSuccess(['settings' => $out]);
    }

    private function handlePost(): void
    {
        // 表示設定
        $showViewCount = $_POST['show_view_count'] ?? '0';
        $this->settingModel->set('show_view_count', $showViewCount);

        // OGP設定
        if (isset($_POST['ogp_title'])) {
            $this->settingModel->set('ogp_title', trim($_POST['ogp_title']));
        }
        if (isset($_POST['ogp_description'])) {
            $this->settingModel->set('ogp_description', trim($_POST['ogp_description']));
        }
        if (isset($_POST['twitter_card'])) {
            $this->settingModel->set('twitter_card', $_POST['twitter_card']);
        }
        if (isset($_POST['twitter_site'])) {
            $this->settingModel->set('twitter_site', trim($_POST['twitter_site']));
        }

        $this->sendSuccess(['message' => '設定が保存されました']);
    }
}

// コントローラーを実行
$controller = new SettingsController();
$controller->execute();
