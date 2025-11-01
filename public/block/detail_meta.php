<?php
/**
 * 詳細ページのメタ情報セクション
 *
 * 必要な変数:
 * @var array $data 投稿データ
 * @var bool $isGroupPost グループ投稿かどうか
 * @var bool $showViewCount 閲覧数を表示するか
 */
?>
<div class="detail-meta">
    <?php if ($isGroupPost && isset($data['image_count'])): ?>
        <span class="meta-item">
            <i class="bi bi-images me-1"></i><?= $data['image_count'] ?>枚
        </span>
    <?php endif; ?>

    <span class="meta-item">
        📅 投稿: <?= date('Y年m月d日', strtotime($data['created_at'])) ?>
    </span>

    <?php
    // 最終更新日の表示（2000年以下の場合は作成日と同じとして扱う）
    $updatedAt = $data['updated_at'] ?? $data['created_at'];
    $updatedYear = (int)date('Y', strtotime($updatedAt));
    if ($updatedYear <= 2000) {
        $updatedAt = $data['created_at'];
    }
    // 作成日と更新日が異なる場合のみ表示
    if ($updatedAt !== $data['created_at']):
    ?>
        <span class="meta-item">
            🔄 更新: <?= date('Y年m月d日', strtotime($updatedAt)) ?>
        </span>
    <?php endif; ?>

    <?php if ($showViewCount && isset($data['view_count'])): ?>
        <span class="meta-item view-count">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: -2px;">
                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
            </svg>
            <?= number_format($data['view_count']) ?> 回閲覧
        </span>
    <?php endif; ?>
</div>

<?php if (!empty($data['tags'])): ?>
    <div class="detail-tags">
        <?php
        $tags = explode(',', $data['tags']);
        foreach ($tags as $tag):
            $tag = trim($tag);
            if (!empty($tag)):
        ?>
            <span class="tag"><?= escapeHtml($tag) ?></span>
        <?php
            endif;
        endforeach;
        ?>
    </div>
<?php endif; ?>

<?php if (!empty($data['detail'])): ?>
    <div class="detail-description"><?= nl2br(escapeHtml($data['detail'])) ?></div>
<?php endif; ?>
