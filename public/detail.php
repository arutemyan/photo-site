<?php

declare(strict_types=1);

/**
 * 単一投稿詳細ページ（リダイレクト用）
 *
 * このファイルは後方互換性のために残されています。
 * 実際の処理は post_detail.php で統一されています。
 */

// パラメータの検証
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /index.php');
    exit;
}

$id = (int)$_GET['id'];

// 統一された詳細ページにリダイレクト
header('Location: /post_detail.php?id=' . $id . '&type=single');
exit;
