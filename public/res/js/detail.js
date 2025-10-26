/**
 * Detail page JavaScript
 * NSFW age verification for direct access
 *
 * 依存するグローバル変数（HTML側でdata属性から読み込まれます）:
 * - AGE_VERIFICATION_MINUTES: 年齢確認の有効期限（分）
 * - NSFW_CONFIG_VERSION: NSFW設定のバージョン
 */

/**
 * 年齢確認済みかチェック
 * @returns {boolean} 確認済みならtrue
 */
function checkAgeVerification() {
    const verified = localStorage.getItem('age_verified');
    const storedVersion = localStorage.getItem('age_verified_version');
    const currentVersion = String(NSFW_CONFIG_VERSION);

    // 設定バージョンが変わっていたら無効化（null や未設定も含む）
    if (!storedVersion || storedVersion !== currentVersion) {
        localStorage.removeItem('age_verified');
        localStorage.removeItem('age_verified_version');
        return false;
    }

    if (!verified) {
        return false;
    }

    const verifiedTime = parseInt(verified);
    const now = Date.now();

    // 設定された分数以内なら有効（グローバル変数 AGE_VERIFICATION_MINUTES を使用）
    const expiryMs = (AGE_VERIFICATION_MINUTES) * 60 * 1000;
    return (now - verifiedTime) < expiryMs;
}

/**
 * 年齢確認を記録
 */
function setAgeVerification() {
    const currentVersion = NSFW_CONFIG_VERSION;
    localStorage.setItem('age_verified', Date.now().toString());
    localStorage.setItem('age_verified_version', String(currentVersion));
}

/**
 * 年齢確認モーダルを表示
 */
function showAgeVerificationModal() {
    const modal = document.getElementById('ageVerificationModal');
    if (modal) {
        modal.classList.add('show');
    }
}

/**
 * 年齢確認モーダルを非表示
 */
function hideAgeVerificationModal() {
    const modal = document.getElementById('ageVerificationModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * 年齢確認「はい」ボタン処理
 */
function confirmAge() {
    // 年齢確認を記録
    setAgeVerification();

    // モーダルを閉じる
    hideAgeVerificationModal();

    // モザイク画像を通常画像に置き換え
    const img = document.getElementById('detailImage');
    if (img && img.dataset.original) {
        img.src = img.dataset.original;
    }
}

/**
 * 年齢確認「いいえ」ボタン処理
 */
function denyAge() {
    // トップページにリダイレクト
    window.location.href = '/';
}

/**
 * ページロード時の処理
 */
function initDetailPage(isSensitive) {
    // センシティブでない場合は何もしない
    if (!isSensitive) {
        return;
    }

    // 年齢確認済みなら画像を表示
    if (checkAgeVerification()) {
        const img = document.getElementById('detailImage');
        if (img && img.dataset.original) {
            img.src = img.dataset.original;
        }
        return;
    }

    // 未確認なら年齢確認モーダルを表示
    showAgeVerificationModal();
}

/**
 * 閲覧回数をインクリメント
 * @param {number} postId 投稿ID
 */
function incrementViewCount(postId) {
    fetch('/api/increment_view', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + postId
    }).catch(function(error) {
        console.error('View count increment failed:', error);
    });
}

// DOMロード後の初期化
document.addEventListener('DOMContentLoaded', function() {
    // モーダルの背景クリックで閉じる（トップページにリダイレクト）
    const modal = document.getElementById('ageVerificationModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                denyAge();
            }
        });
    }
});
