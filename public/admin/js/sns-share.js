/**
 * SNS共有機能
 * X (Twitter), Misskey への投稿共有
 */

/**
 * SNS共有モーダルを表示
 */
function shareToSNS(postId, title, isSensitive) {
    // 詳細ページのURLを構築
    const protocol = window.location.protocol;
    const host = window.location.host;
    const detailUrl = `${protocol}//${host}/detail.php?id=${postId}`;

    // エンコードされたURL
    const encodedUrl = encodeURIComponent(detailUrl);
    const encodedTitle = encodeURIComponent(title);
    const hashtags = 'イラスト,artwork';
    const encodedHashtags = encodeURIComponent(hashtags);

    // センシティブな場合はハッシュタグに追加
    const nsfwHashtag = isSensitive ? ',NSFW' : '';
    const fullHashtags = encodeURIComponent(hashtags + nsfwHashtag);

    // 各SNSの共有URL
    const shareUrls = {
        twitter: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}&hashtags=${fullHashtags}`,
        misskey: `https://misskey.io/share?text=${encodedTitle}%0A${encodedUrl}`
    };

    // モーダルHTML
    const modalHtml = `
        <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="shareModalLabel">
                            <i class="bi bi-share me-2"></i>SNSで共有
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3"><strong>${escapeHtml(title)}</strong></p>
                        <p class="text-muted small mb-3">
                            共有URL: <a href="${detailUrl}" target="_blank">${detailUrl}</a>
                        </p>
                        ${isSensitive ? '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>この投稿はNSFWです。SNS共有時はぼかし画像が使用されます。</div>' : ''}

                        <div class="d-grid gap-2">
                            <a href="${shareUrls.twitter}" target="_blank" class="btn btn-primary" style="background-color: #1DA1F2; border-color: #1DA1F2;">
                                <i class="bi bi-twitter me-2"></i>X (Twitter) で共有
                            </a>
                            <a href="${shareUrls.misskey}" target="_blank" class="btn btn-primary" style="background-color: #86b300; border-color: #86b300;">
                                <i class="bi bi-mastodon me-2"></i>Misskey で共有
                            </a>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">共有URL（コピー用）</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="shareUrlInput" value="${detailUrl}" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyShareUrl()">
                                    <i class="bi bi-clipboard"></i> コピー
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // 既存のモーダルを削除
    $('#shareModal').remove();

    // 新しいモーダルを追加して表示
    $('body').append(modalHtml);
    const shareModal = new bootstrap.Modal(document.getElementById('shareModal'));
    shareModal.show();

    // モーダルが閉じられたらDOMから削除
    $('#shareModal').on('hidden.bs.modal', function() {
        $(this).remove();
        // バックドロップも確実に削除
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('overflow', '').css('padding-right', '');
    });
}

/**
 * 共有URLをクリップボードにコピー
 */
function copyShareUrl() {
    const input = document.getElementById('shareUrlInput');
    input.select();
    document.execCommand('copy');

    // コピー成功のフィードバック
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check"></i> コピーしました';
    btn.classList.add('btn-success');
    btn.classList.remove('btn-outline-secondary');

    setTimeout(function() {
        btn.innerHTML = originalHtml;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline-secondary');
    }, 2000);
}
