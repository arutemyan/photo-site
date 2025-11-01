<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Security/SecurityUtil.php';

use App\Models\Theme;
use App\Security\CsrfProtection;

// セッション開始
initSecureSession();

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// GETリクエスト: テーマ設定取得
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $themeModel = new Theme();
        $theme = $themeModel->getCurrent();

        echo json_encode([
            'success' => true,
            'theme' => $theme
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'サーバーエラーが発生しました'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// PUTリクエストまたはPOSTリクエスト（_methodパラメータ）
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT') {
    $method = 'PUT';
}

if ($method !== 'PUT' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'PUTまたはPOSTメソッドのみ許可されています'], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRFトークン検証
if (!CsrfProtection::validatePost() && !CsrfProtection::validateHeader()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRFトークンが無効です'], JSON_UNESCAPED_UNICODE);
    logSecurityEvent('CSRF token validation failed on theme update', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    exit;
}

try {
    // パラメータ取得と入力長検証
    $data = [];

    // サイト情報
    if (isset($_POST['site_title'])) {
        if (mb_strlen($_POST['site_title']) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'サイトタイトルは100文字以内で入力してください'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $data['site_title'] = $_POST['site_title'];
    }
    if (isset($_POST['site_subtitle'])) {
        if (mb_strlen($_POST['site_subtitle']) > 200) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'サイトサブタイトルは200文字以内で入力してください'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $data['site_subtitle'] = $_POST['site_subtitle'];
    }
    if (isset($_POST['site_description'])) {
        if (mb_strlen($_POST['site_description']) > 500) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'サイト説明は500文字以内で入力してください'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $data['site_description'] = $_POST['site_description'];
    }

    // カラーテーマ
    if (isset($_POST['primary_color'])) {
        $data['primary_color'] = $_POST['primary_color'];
    }
    if (isset($_POST['secondary_color'])) {
        $data['secondary_color'] = $_POST['secondary_color'];
    }
    if (isset($_POST['accent_color'])) {
        $data['accent_color'] = $_POST['accent_color'];
    }
    if (isset($_POST['background_color'])) {
        $data['background_color'] = $_POST['background_color'];
    }
    if (isset($_POST['text_color'])) {
        $data['text_color'] = $_POST['text_color'];
    }
    if (isset($_POST['heading_color'])) {
        $data['heading_color'] = $_POST['heading_color'];
    }
    if (isset($_POST['footer_bg_color'])) {
        $data['footer_bg_color'] = $_POST['footer_bg_color'];
    }
    if (isset($_POST['footer_text_color'])) {
        $data['footer_text_color'] = $_POST['footer_text_color'];
    }
    if (isset($_POST['card_border_color'])) {
        $data['card_border_color'] = $_POST['card_border_color'];
    }
    if (isset($_POST['card_bg_color'])) {
        $data['card_bg_color'] = $_POST['card_bg_color'];
    }
    if (isset($_POST['card_shadow_opacity'])) {
        $data['card_shadow_opacity'] = $_POST['card_shadow_opacity'];
    }
    if (isset($_POST['link_color'])) {
        $data['link_color'] = $_POST['link_color'];
    }
    if (isset($_POST['link_hover_color'])) {
        $data['link_hover_color'] = $_POST['link_hover_color'];
    }
    if (isset($_POST['tag_bg_color'])) {
        $data['tag_bg_color'] = $_POST['tag_bg_color'];
    }
    if (isset($_POST['tag_text_color'])) {
        $data['tag_text_color'] = $_POST['tag_text_color'];
    }
    if (isset($_POST['filter_active_bg_color'])) {
        $data['filter_active_bg_color'] = $_POST['filter_active_bg_color'];
    }
    if (isset($_POST['filter_active_text_color'])) {
        $data['filter_active_text_color'] = $_POST['filter_active_text_color'];
    }

    // ナビゲーション設定（一覧に戻るボタン）
    if (isset($_POST['back_button_text'])) {
        if (mb_strlen($_POST['back_button_text']) > 20) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ボタンテキストは20文字以内で入力してください'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $data['back_button_text'] = $_POST['back_button_text'];
    }
    if (isset($_POST['back_button_bg_color'])) {
        $data['back_button_bg_color'] = $_POST['back_button_bg_color'];
    }
    if (isset($_POST['back_button_text_color'])) {
        $data['back_button_text_color'] = $_POST['back_button_text_color'];
    }

    // カスタムHTML（XSS対策: HTMLタグを許可しない）
    // セキュリティ上の理由から、カスタムHTMLは無効化されています
    // 必要な場合は、HTMLPurifierなどのホワイトリスト型サニタイザーを実装してください
    if (isset($_POST['header_html'])) {
        if (mb_strlen($_POST['header_html']) > 5000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ヘッダーHTMLは5000文字以内で入力してください'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // HTMLタグを全て削除（XSS対策）
        $data['header_html'] = strip_tags($_POST['header_html']);
    }
    if (isset($_POST['footer_html'])) {
        if (mb_strlen($_POST['footer_html']) > 5000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'フッターHTMLは5000文字以内で入力してください'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // HTMLタグを全て削除（XSS対策）
        $data['footer_html'] = strip_tags($_POST['footer_html']);
    }

    // データベースを更新
    $themeModel = new Theme();
    $themeModel->update($data);

    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => 'テーマが更新されました'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ], JSON_UNESCAPED_UNICODE);

    error_log('Theme Update Error: ' . $e->getMessage());
}
