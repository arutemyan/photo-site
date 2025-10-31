/**
 * Main JavaScript for public pages
 * NSFW age verification system
 *
 * 依存するグローバル変数（HTML側でdata属性から読み込まれます）:
 * - AGE_VERIFICATION_MINUTES: 年齢確認の有効期限（分）
 * - NSFW_CONFIG_VERSION: NSFW設定のバージョン
 */

// 現在クリックされた投稿ID（モーダル用）
let currentSensitivePostId = null;

// フィルタ状態
let currentNSFWFilter = 'all';  // all, safe, nsfw
let currentTagFilter = null;

// 無限スクロール状態
let currentOffset = 0;
let isLoading = false;
let hasMorePosts = true;
const POSTS_PER_PAGE = 18;

// オーバーレイナビゲーション用
let allPostElements = [];  // 全投稿要素の配列
let currentOverlayIndex = -1;  // 現在表示中の投稿のインデックス
let pendingNsfwPostId = null;  // NSFW警告待ちの投稿ID

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
    const expiryMs = (AGE_VERIFICATION_MINUTES) * 60 * 1000;
    const timeSince = now - verifiedTime;
    const isValid = timeSince < expiryMs;

    return isValid;
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
 * センシティブ画像クリック処理
 * @param {number} postId 投稿ID
 * @param {boolean} isSensitive センシティブフラグ
 */
function handleSensitiveClick(event, postId, isSensitive) {
    // センシティブでない場合は通常遷移
    if (!isSensitive) {
        return true;
    }

    // イベントをキャンセル
    event.preventDefault();

    // 年齢確認済みなら直接遷移
    if (checkAgeVerification()) {
        window.location.href = '/detail.php?id=' + postId;
        return false;
    }

    // 未確認なら年齢確認モーダルを表示
    currentSensitivePostId = postId;
    showAgeVerificationModal();
    return false;
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
    currentSensitivePostId = null;
}

/**
 * 年齢確認「はい」ボタン処理
 */
function confirmAge() {
    // 年齢確認を記録
    setAgeVerification();

    // 詳細ページへ遷移
    if (currentSensitivePostId) {
        window.location.href = '/detail.php?id=' + currentSensitivePostId;
    }
}

/**
 * 年齢確認「いいえ」ボタン処理
 */
function denyAge() {
    hideAgeVerificationModal();
}

/**
 * タグ一覧を読み込み
 */
function loadTags() {
    const tagList = document.getElementById('tagList');
    if (!tagList) {
        console.warn('[loadTags] tagList element not found');
        return;
    }

    // TAGS_DATAグローバル変数からタグ一覧を取得（index.phpで設定）
    const tags = TAGS_DATA || [];

    // 既存のタグボタンをすべて削除（動的に作成されたもの）
    const dynamicTags = tagList.querySelectorAll('.tag-btn-dynamic');
    dynamicTags.forEach(btn => btn.remove());

    // タグボタンを作成
    tags.forEach(tag => {
        if (tag.post_count === 0) {
            return; // 投稿数0のタグはスキップ
        }

        const btn = document.createElement('button');
        btn.className = 'tag-btn tag-btn-compact tag-btn-dynamic';
        btn.dataset.tagId = tag.id;           // タグIDを保存
        btn.dataset.tagName = tag.name;       // タグ名も保存（表示用）
        btn.textContent = `${tag.name} (${tag.post_count})`;
        btn.onclick = () => {
            filterByTag(tag.id);             // タグIDで検索
            setActiveTagButton(btn);
        };
        tagList.appendChild(btn);
    });
}

/**
 * アクティブなタグボタンを設定
 */
function setActiveTagButton(activeBtn) {
    const allButtons = document.querySelectorAll('.tag-btn');
    allButtons.forEach(btn => btn.classList.remove('active'));
    activeBtn.classList.add('active');
}

/**
 * NSFWフィルタを設定
 * @param {string} filter フィルタ値（all, safe, nsfw）
 */
function setNSFWFilter(filter) {
    currentNSFWFilter = filter;

    // ボタンのアクティブ状態を更新
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(btn => {
        if (btn.dataset.filter === filter) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // フィルタを適用
    applyFilters();
}

/**
 * タグで絞り込み（NSFWフィルタと組み合わせ）
 * @param {number|null} tagId タグID
 */
function filterByTag(tagId) {
    currentTagFilter = tagId || null;

    // タグボタンのアクティブ状態を更新
    const allTagButtons = document.querySelectorAll('.tag-btn');
    allTagButtons.forEach(btn => {
        const btnTagId = btn.dataset.tagId ? parseInt(btn.dataset.tagId) : null;
        if (btnTagId === tagId || (!tagId && btn.classList.contains('tag-btn-all'))) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    applyFilters();
}

/**
 * フィルタを適用（NSFWフィルタとタグフィルタのAND条件）
 * @param {boolean} reset フィルタ変更時はtrueでリセット
 */
function applyFilters(reset = true) {
    if (reset) {
        currentOffset = 0;
        hasMorePosts = true;
    }

    let url = '/api/posts?';

    // NSFWフィルタをクエリに追加
    url += `nsfw_filter=${encodeURIComponent(currentNSFWFilter)}`;

    // タグフィルタをクエリに追加
    if (currentTagFilter) {
        url += `&tagId=${encodeURIComponent(currentTagFilter)}`;
    }

    // ページネーションパラメータを追加
    url += `&limit=${POSTS_PER_PAGE}&offset=${currentOffset}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Filter failed:', data.error);
                return;
            }

            if (reset) {
                renderPosts(data.posts);
            } else {
                appendPosts(data.posts);
            }

            // フィルター情報を表示
            showFilterInfo(data.count);

            // これ以上投稿がない場合
            if (data.posts.length < POSTS_PER_PAGE) {
                hasMorePosts = false;
            }
        })
        .catch(error => {
            console.error('Error applying filters:', error);
        });
}

/**
 * フィルターをクリア（すべて表示）
 */
function clearTagFilter() {
    currentTagFilter = null;
    currentNSFWFilter = 'all';

    // NSFWフィルタボタンのアクティブ状態をリセット
    document.querySelectorAll('.filter-btn').forEach(btn => {
        if (btn.dataset.filter === 'all') {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // タグボタンのアクティブ状態をリセット
    document.querySelectorAll('.tag-btn').forEach(btn => {
        if (btn.classList.contains('tag-btn-all')) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    applyFilters();
}

/**
 * タグの表示/非表示を切り替え
 */
function toggleTagsVisibility() {
    const toggleBtn = document.getElementById('toggleTags');
    const isActive = toggleBtn.classList.contains('active');

    if (isActive) {
        // 非表示にする
        toggleBtn.classList.remove('active');
        document.body.classList.add('hide-tags');
        localStorage.setItem('hideTags', 'true');
    } else {
        // 表示する
        toggleBtn.classList.add('active');
        document.body.classList.remove('hide-tags');
        localStorage.setItem('hideTags', 'false');
    }
}

/**
 * 表題の表示/非表示を切り替え
 */
function toggleTitlesVisibility() {
    const toggleBtn = document.getElementById('toggleTitles');
    const isActive = toggleBtn.classList.contains('active');

    if (isActive) {
        // 非表示にする
        toggleBtn.classList.remove('active');
        document.body.classList.add('hide-titles');
        localStorage.setItem('hideTitles', 'true');
    } else {
        // 表示する
        toggleBtn.classList.add('active');
        document.body.classList.remove('hide-titles');
        localStorage.setItem('hideTitles', 'false');
    }
}

/**
 * ページ読み込み時にトグル状態を復元
 */
function restoreToggleStates() {
    // タグの状態を復元
    const hideTags = localStorage.getItem('hideTags') === 'true';
    const toggleTagsBtn = document.getElementById('toggleTags');
    if (hideTags && toggleTagsBtn) {
        toggleTagsBtn.classList.remove('active');
        document.body.classList.add('hide-tags');
    }

    // 表題の状態を復元
    const hideTitles = localStorage.getItem('hideTitles') === 'true';
    const toggleTitlesBtn = document.getElementById('toggleTitles');
    if (hideTitles && toggleTitlesBtn) {
        toggleTitlesBtn.classList.remove('active');
        document.body.classList.add('hide-titles');
    }
}

/**
 * 投稿一覧を描画（リセット）
 */
function renderPosts(posts) {
    const grid = document.querySelector('.grid');
    if (!grid) {
        return;
    }

    // グリッドをクリア
    grid.innerHTML = '';

    if (posts.length === 0) {
        grid.innerHTML = `
            <div class="empty-state" style="grid-column: 1 / -1;">
                <span style="font-size: 4em;">🔍</span>
                <h2>該当する投稿が見つかりませんでした</h2>
                <p>別のタグで検索してみてください</p>
            </div>
        `;
        return;
    }

    // 投稿カードを作成
    posts.forEach(post => {
        appendPostCard(grid, post);
    });
}

/**
 * 投稿を追加で描画（無限スクロール用）
 */
function appendPosts(posts) {
    const grid = document.querySelector('.grid');
    if (!grid) {
        return;
    }

    // 投稿カードを追加
    posts.forEach(post => {
        appendPostCard(grid, post);
    });
}

/**
 * 投稿カードを作成してグリッドに追加
 */
function appendPostCard(grid, post) {
    const isSensitive = post.is_sensitive == 1;

    // NSFWサムネイルのパス生成
    let imagePath;
    if (isSensitive) {
        const thumbPath = post.thumb_path || post.image_path || '';
        const pathParts = thumbPath.split('.');
        if (pathParts.length > 1) {
            pathParts[pathParts.length - 2] += '_nsfw';
            imagePath = '/' + pathParts.join('.');
        } else {
            imagePath = '/res/images/nsfw-placeholder.svg';
        }
    } else {
        imagePath = '/' + (post.thumb_path || post.image_path || '');
    }

    const card = document.createElement('div');
    card.className = 'card';
    card.dataset.postId = post.id;

    let cardHTML = '';

    // 画像ラッパー
    cardHTML += `<div class="card-img-wrapper ${isSensitive ? 'nsfw-wrapper' : ''}">`;
    cardHTML += `
        <img
            src="${imagePath}"
            alt="${escapeHtml(post.title)}"
            class="card-image"
            loading="lazy"
            onerror="if(!this.dataset.errorHandled){this.dataset.errorHandled='1';this.src='/res/images/nsfw-placeholder.svg';}"
            data-full-image="${'/' + (post.image_path || post.thumb_path || '')}"
            data-is-sensitive="${isSensitive ? '1' : '0'}"
            onclick="openImageOverlay(${post.id}, ${isSensitive})"
            style="cursor: pointer;"
        >
    `;

    if (isSensitive) {
        cardHTML += `
            <div class="nsfw-overlay">
                <div class="nsfw-text">センシティブな内容を含む</div>
            </div>
        `;
    }

    if (post.tags) {
        const tags = post.tags.split(',');
        cardHTML += '<div class="card-tags">';
        tags.forEach(tag => {
            const trimmedTag = tag.trim();
            if (trimmedTag) {
                cardHTML += `<span class="tag">${escapeHtml(trimmedTag)}</span>`;
            }
        });
        cardHTML += '</div>';
    }

    cardHTML += '</div>';

    cardHTML += `<div class="card-content">
        <h2 class="card-title">${escapeHtml(post.title)}</h2>
    </div>`;

    card.innerHTML = cardHTML;
    grid.appendChild(card);
}

/**
 * フィルター情報を表示
 * @param {number} count 投稿件数
 */
function showFilterInfo(count) {
    // 絞り込み情報表示は不要なので何もしない
    // フィルタ状態はボタンのアクティブ状態で十分判断できる
}

/**
 * HTMLエスケープ
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * 画像オーバーレイを開く
 * @param {number} postId 投稿ID
 * @param {boolean} isSensitive センシティブフラグ
 */
function openImageOverlay(postId, isSensitive) {
    // センシティブ画像で年齢確認が必要な場合
    if (isSensitive && !checkAgeVerification()) {
        currentSensitivePostId = postId;
        showAgeVerificationModal();
        return;
    }

    // 全投稿要素を取得（初回のみまたは投稿数が変わった場合）
    const currentCards = document.querySelectorAll('.card[data-post-id]');
    if (allPostElements.length !== currentCards.length) {
        allPostElements = Array.from(currentCards);
    }

    // 現在の投稿のインデックスを取得
    currentOverlayIndex = allPostElements.findIndex(card =>
        parseInt(card.dataset.postId) === parseInt(postId)
    );

    if (currentOverlayIndex === -1) {
        console.error('[Overlay] Post not found in list:', postId);
        return;
    }

    // 画像を表示
    displayOverlayImage(postId);
}

/**
 * 画像オーバーレイを閉じる
 * @param {Event} event クリックイベント
 */
function closeImageOverlay(event) {
    const overlay = document.getElementById('imageOverlay');
    const overlayContent = document.querySelector('.image-overlay-content');

    // コンテンツ部分のクリックは無視
    if (event && overlayContent && overlayContent.contains(event.target) && !event.target.classList.contains('image-overlay-close')) {
        return;
    }

    if (overlay) {
        overlay.classList.remove('show');
        document.body.style.overflow = ''; // スクロール復元
    }
}

/**
 * オーバーレイに画像を表示
 * @param {number} postId 投稿ID
 */
function displayOverlayImage(postId) {
    const card = document.querySelector(`.card[data-post-id="${postId}"]`);
    if (!card) {
        console.error('[Overlay] Card not found:', postId);
        return;
    }

    const img = card.querySelector('.card-image');
    if (!img) {
        console.error('[Overlay] Image not found in card:', postId);
        return;
    }

    const fullImagePath = img.dataset.fullImage;
    const isSensitive = img.dataset.isSensitive === '1';

    if (!fullImagePath) {
        console.error('[Overlay] Full image path not found:', postId);
        return;
    }

    // オーバーレイに画像を設定
    const overlayImg = document.getElementById('overlayImage');
    const overlay = document.getElementById('imageOverlay');
    const detailButton = document.getElementById('overlayDetailButton');

    if (overlayImg && overlay) {
        // 画像パスを設定
        overlayImg.src = fullImagePath;
        overlayImg.dataset.postId = postId;
        overlayImg.dataset.isSensitive = isSensitive ? '1' : '0';

        overlay.classList.add('show');
        document.body.style.overflow = 'hidden'; // スクロール防止

        // 詳細ボタンのリンクを設定
        if (detailButton) {
            detailButton.href = '/detail.php?id=' + postId;
        }

        // ナビゲーションボタンの表示/非表示
        updateNavigationButtons();

        // 閲覧回数をインクリメント
        incrementViewCount(postId);
    }
}

/**
 * オーバーレイナビゲーション（前/次の画像に移動）
 * @param {Event} event クリックイベント
 * @param {number} direction -1: 前, 1: 次
 */
function navigateOverlay(event, direction) {
    // イベント伝播を停止
    if (event) {
        event.stopPropagation();
    }

    let newIndex = currentOverlayIndex + direction;

    // 範囲外チェック
    if (newIndex < 0 || newIndex >= allPostElements.length) {
        return;
    }

    // 年齢確認状態を取得
    const isAgeVerified = checkAgeVerification();

    // 年齢確認が必要な場合、NSFW画像をスキップして次の非NSFW画像を探す
    while (newIndex >= 0 && newIndex < allPostElements.length) {
        const nextCard = allPostElements[newIndex];
        const nextPostId = parseInt(nextCard.dataset.postId);
        const nextImg = nextCard.querySelector('.card-image');
        const nextIsSensitive = nextImg.dataset.isSensitive === '1';

        // 年齢確認が必要でNSFW画像の場合はスキップして次へ
        if (nextIsSensitive && !isAgeVerified) {
            newIndex += direction;
            continue;
        }

        // 表示可能な画像を見つけた
        currentOverlayIndex = newIndex;

        if (nextIsSensitive) {
            // NSFW画像で年齢確認済みの場合、警告モーダルを表示
            pendingNsfwPostId = nextPostId;
            showNsfwWarningModal(nextPostId);
        } else {
            // 通常画像の場合、そのまま表示
            displayOverlayImage(nextPostId);
        }
        return;
    }

    // 表示可能な画像が見つからなかった場合は何もしない（端に到達）
}

/**
 * NSFW警告モーダルを表示（オーバーレイナビゲーション用）
 * @param {number} postId 投稿ID
 */
function showNsfwWarningModal(postId) {
    // まずNSFWフィルター画像を表示
    const card = document.querySelector(`.card[data-post-id="${postId}"]`);
    if (!card) return;

    const img = card.querySelector('.card-image');
    if (!img) return;

    // NSFWフィルター画像のパスを構築
    const thumbPath = img.src;
    const nsfwPath = thumbPath.replace(/\.([^.]+)$/, '_nsfw.$1');

    const overlayImg = document.getElementById('overlayImage');
    if (overlayImg) {
        overlayImg.src = nsfwPath;
        overlayImg.dataset.postId = postId;
        overlayImg.dataset.isSensitive = '1';
        overlayImg.dataset.originalPath = img.dataset.fullImage;
    }

    // 警告モーダルを表示
    const modal = document.getElementById('nsfwWarningModal');
    if (modal) {
        modal.classList.add('show');
    }

    // 詳細ボタンのリンクを更新
    const detailButton = document.getElementById('overlayDetailButton');
    if (detailButton) {
        detailButton.href = '/detail.php?id=' + postId;
    }

    // ナビゲーションボタンの表示/非表示
    updateNavigationButtons();
}

/**
 * NSFW警告を承認（実画像を表示）
 */
function acceptNsfwWarning() {
    const modal = document.getElementById('nsfwWarningModal');
    if (modal) {
        modal.classList.remove('show');
    }

    if (pendingNsfwPostId) {
        const overlayImg = document.getElementById('overlayImage');
        if (overlayImg && overlayImg.dataset.originalPath) {
            // 実画像に切り替え
            overlayImg.src = overlayImg.dataset.originalPath;
        }
        pendingNsfwPostId = null;
    }
}

/**
 * NSFW警告をキャンセル（フィルター画像のまま）
 */
function cancelNsfwWarning() {
    const modal = document.getElementById('nsfwWarningModal');
    if (modal) {
        modal.classList.remove('show');
    }
    // フィルター画像のままにする（何もしない）
    pendingNsfwPostId = null;
}

/**
 * ナビゲーションボタンの表示/非表示を更新
 */
function updateNavigationButtons() {
    const prevBtn = document.querySelector('.image-overlay-prev');
    const nextBtn = document.querySelector('.image-overlay-next');

    if (prevBtn) {
        prevBtn.style.display = currentOverlayIndex > 0 ? 'block' : 'none';
    }

    if (nextBtn) {
        nextBtn.style.display = currentOverlayIndex < allPostElements.length - 1 ? 'block' : 'none';
    }
}

/**
 * ローディングインジケーターを表示
 */
function showLoadingIndicator() {
    const indicator = document.getElementById('loadingIndicator');
    if (indicator) {
        indicator.classList.add('show');
    }
}

/**
 * ローディングインジケーターを非表示
 */
function hideLoadingIndicator() {
    const indicator = document.getElementById('loadingIndicator');
    if (indicator) {
        indicator.classList.remove('show');
    }
}

/**
 * 無限スクロールのロード処理
 */
function loadMorePosts() {
    if (isLoading || !hasMorePosts) {
        return;
    }

    isLoading = true;
    showLoadingIndicator();

    currentOffset += POSTS_PER_PAGE;
    applyFilters(false); // reset=false で追加読み込み

    // ロード完了後にフラグをリセット
    setTimeout(() => {
        isLoading = false;
        hideLoadingIndicator();
    }, 500);
}

/**
 * スクロール位置を監視して自動ロード
 */
function handleScroll() {
    // ページ下部まで残り200pxになったらロード開始
    const scrollPosition = window.innerHeight + window.scrollY;
    const threshold = document.documentElement.scrollHeight - 200;

    if (scrollPosition >= threshold) {
        loadMorePosts();
    }
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
    // 古いlocalStorageキーをクリーンアップ
    // 以前のバージョンで使用していた可能性のあるキーをすべて削除
    const oldKeys = [
        'age_verified',
        'age_verified_version',
        'nsfw_age_verified',
        'nsfw_verified',
        'ageVerified',
        'age_verification'
    ];

    // 現在のバージョンをチェック
    const currentVersion = String(NSFW_CONFIG_VERSION);
    const storedVersion = localStorage.getItem('age_verified_version');

    // バージョンが異なる場合、または存在しない場合はすべてクリア
    if (!storedVersion || storedVersion !== currentVersion) {
        oldKeys.forEach(key => localStorage.removeItem(key));
        // 新しいバージョンも一旦クリア
        localStorage.removeItem('age_verified');
        localStorage.removeItem('age_verified_version');
    }

    // モーダルの背景クリックで閉じる
    const modal = document.getElementById('ageVerificationModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideAgeVerificationModal();
            }
        });
    }

    // キーボード操作
    document.addEventListener('keydown', function(e) {
        const overlay = document.getElementById('imageOverlay');
        const isOverlayOpen = overlay && overlay.classList.contains('show');

        if (!isOverlayOpen) return;

        // Escキーでオーバーレイを閉じる
        if (e.key === 'Escape') {
            closeImageOverlay(e);
        }
        // 左矢印キーで前の画像
        else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            navigateOverlay(null, -1);
        }
        // 右矢印キーで次の画像
        else if (e.key === 'ArrowRight') {
            e.preventDefault();
            navigateOverlay(null, 1);
        }
    });

    // タグ一覧を読み込み
    loadTags();

    // トグル状態を復元
    restoreToggleStates();

    // 無限スクロールのイベントリスナー
    window.addEventListener('scroll', handleScroll);
});
