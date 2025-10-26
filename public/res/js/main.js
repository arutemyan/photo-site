/**
 * Main JavaScript for public pages
 * NSFW age verification system
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
    fetch('/api/tags?popular=20')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Failed to load tags:', data.error);
                return;
            }

            const tagList = document.getElementById('tagList');
            if (!tagList) {
                console.warn('tagList element not found');
                return;
            }

            // 既存のタグボタンをすべて削除（動的に作成されたもの）
            const dynamicTags = tagList.querySelectorAll('.tag-btn-dynamic');
            dynamicTags.forEach(btn => btn.remove());

            // タグボタンを作成
            data.tags.forEach(tag => {
                if (tag.post_count === 0) {
                    return; // 投稿数0のタグはスキップ
                }

                const btn = document.createElement('button');
                btn.className = 'tag-btn tag-btn-compact tag-btn-dynamic';
                btn.dataset.tag = tag.name;
                btn.textContent = `${tag.name} (${tag.post_count})`;
                btn.onclick = () => {
                    filterByTag(tag.name);
                    setActiveTagButton(btn);
                };
                tagList.appendChild(btn);
            });
        })
        .catch(error => {
            console.error('Error loading tags:', error);
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
 * @param {string} tagName タグ名（空文字列ですべて表示）
 */
function filterByTag(tagName) {
    currentTagFilter = tagName || null;

    // タグボタンのアクティブ状態を更新
    const allTagButtons = document.querySelectorAll('.tag-btn');
    allTagButtons.forEach(btn => {
        const btnTag = btn.dataset.tag || '';
        if (btnTag === tagName || (!tagName && btn.classList.contains('tag-btn-all'))) {
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
        url += `&tag=${encodeURIComponent(currentTagFilter)}`;
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

    // 画像要素を取得
    const card = document.querySelector(`.card[data-post-id="${postId}"]`);
    if (!card) {
        console.error('[NSFW] Card not found:', postId);
        return;
    }

    const img = card.querySelector('.card-image');
    if (!img) {
        console.error('[NSFW] Image not found in card:', postId);
        return;
    }

    const fullImagePath = img.dataset.fullImage;
    if (!fullImagePath) {
        console.error('[NSFW] Full image path not found:', postId);
        return;
    }

    // オーバーレイに画像を設定
    const overlayImg = document.getElementById('overlayImage');
    const overlay = document.getElementById('imageOverlay');
    const detailButton = document.getElementById('overlayDetailButton');

    if (overlayImg && overlay) {
        overlayImg.src = fullImagePath;
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden'; // スクロール防止

        // 詳細ボタンのリンクを設定
        if (detailButton) {
            detailButton.href = '/detail.php?id=' + postId;
        }
    }
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

    // Escキーでオーバーレイを閉じる
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageOverlay(e);
        }
    });

    // タグ一覧を読み込み
    loadTags();

    // 無限スクロールのイベントリスナー
    window.addEventListener('scroll', handleScroll);
});
