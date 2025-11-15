/**
 * Paint Gallery JavaScript
 * ギャラリーページのフロントエンド機能
 */

let currentOffset = 0;
let currentTag = null;
let currentSearch = '';
let currentNSFWFilter = 'all'; // all, safe, nsfw
let isLoading = false;
let hasMore = true;
const LIMIT = 20;
let pendingNsfwIllustId = null; // 年齢確認待ちのイラストID

// ページ読み込み時
document.addEventListener('DOMContentLoaded', () => {
    loadTags();
    loadPaints();
    
    // 検索ボックス
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearch = e.target.value.trim();
                resetAndLoad();
            }, 500);
        });
    }
    
    // 無限スクロール
    window.addEventListener('scroll', () => {
        if (isLoading || !hasMore) return;
        
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight;
        const clientHeight = document.documentElement.clientHeight;
        
        if (scrollTop + clientHeight >= scrollHeight - 500) {
            loadIllusts();
        }
    });
});

/**
 * タグ一覧を読み込み
 */
async function loadTags() {
    try {
        const response = await fetch('/paint/api/tags.php');
        const data = await response.json();
        
        if (data.success && data.tags) {
            renderTags(data.tags);
        }
    } catch (error) {
        console.error('タグ読み込みエラー:', error);
    }
}

/**
 * タグをレンダリング
 */
function renderTags(tags) {
    const tagList = document.getElementById('tagList');
    if (!tagList) return;
    
    tagList.innerHTML = tags.map(tag => `
        <button class="tag-btn" data-tag="${escapeHtml(tag.name)}" onclick="filterByTag('${escapeHtml(tag.name)}')">
            ${escapeHtml(tag.name)} (${tag.count})
        </button>
    `).join('');
}

/**
 * タグフィルター
 */
function filterByTag(tagName) {
    if (currentTag === tagName) {
        // 同じタグをクリックしたら解除
        currentTag = null;
        document.querySelectorAll('.tag-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector('.tag-btn[data-tag=""]')?.classList.add('active');
    } else {
        currentTag = tagName;
        document.querySelectorAll('.tag-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tag === tagName);
        });
    }
    resetAndLoad();
}

/**
 * すべてのタグを表示
 */
function showAllPaints() {
    currentTag = null;
    document.querySelectorAll('.tag-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tag === '');
    });
    resetAndLoad();
}

/**
 * NSFWフィルター設定
 */
function setNSFWFilter(filter) {
    // NSFWフィルターを選択した場合、年齢確認をチェック
    if (filter === 'nsfw' && !checkAgeVerification()) {
        // 年齢確認モーダルを表示
        pendingNsfwIllustId = 'filter'; // フィルター変更のマーク
        showAgeVerificationModal();
        return;
    }

    currentNSFWFilter = filter;
    // ボタンのアクティブ状態を更新
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.nsfwFilter === filter);
    });
    resetAndLoad();
}

/**
 * リセットして再読み込み
 */
function resetAndLoad() {
    currentOffset = 0;
    hasMore = true;
    document.getElementById('galleryGrid').innerHTML = '';
    loadPaints();
}

/**
 * イラスト一覧を読み込み
 */
async function loadPaints() {
    if (isLoading || !hasMore) return;
    
    isLoading = true;
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            limit: LIMIT,
            offset: currentOffset
        });

        if (currentTag) params.append('tag', currentTag);
        if (currentSearch) params.append('search', currentSearch);
        if (currentNSFWFilter) params.append('nsfw_filter', currentNSFWFilter);
        
        const response = await fetch(`/paint/api/paint.php?${params}`);
        const data = await response.json();

        if (data.success && data.paint) {
            if (data.paint.length === 0) {
                hasMore = false;
                if (currentOffset === 0) {
                    showEmptyState();
                }
            } else {
                renderPaints(data.paint);
                currentOffset += data.paint.length;
                hasMore = data.paint.length === LIMIT;
            }
        }
    } catch (error) {
        console.error('イラスト読み込みエラー:', error);
        showError('イラストの読み込みに失敗しました');
    } finally {
        isLoading = false;
        showLoading(false);
    }
}

/**
 * イラストをレンダリング
 */
function renderPaints(paint) {
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;
    
    const fragment = document.createDocumentFragment();
    
    paint.forEach(illust => {
        const card = createIllustCard(illust);
        fragment.appendChild(card);
    });
    
    grid.appendChild(fragment);
}

/**
 * イラストカードを作成
 */
function createIllustCard(illust) {
    const card = document.createElement('div');
    const isNsfw = illust.nsfw == 1;
    card.className = 'card illust-card' + (isNsfw ? ' nsfw-card' : '');

    // NSFWの場合、年齢確認が必要
    if (isNsfw) {
        card.onclick = () => {
            if (!checkAgeVerification()) {
                pendingNsfwIllustId = illust.id;
                showAgeVerificationModal();
            } else {
                window.location.href = `/paint/detail.php?id=${illust.id}`;
            }
        };
    } else {
        card.onclick = () => window.location.href = `/paint/detail.php?id=${illust.id}`;
    }

    const thumbPath = illust.thumb_path || illust.image_path;
    const tags = illust.tags ? illust.tags.split(',') : [];
    const date = new Date(illust.created_at);
    const dateStr = date.toLocaleDateString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' });

    card.innerHTML = `
        <div class="illust-image-wrapper${isNsfw ? ' nsfw-wrapper' : ''}">
            <img src="${escapeHtml(thumbPath)}" alt="${escapeHtml(illust.title)}" class="illust-image" loading="lazy">
            ${isNsfw ? '<div class="nsfw-overlay"><div class="nsfw-text">センシティブな内容</div></div>' : ''}
        </div>
        <div class="illust-info">
            <h3 class="illust-title">${escapeHtml(illust.title)}</h3>
            <div class="illust-meta">
                <span class="illust-date">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    ${dateStr}
                </span>
                ${illust.width && illust.height ? `
                <span class="illust-size">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    </svg>
                    ${illust.width}×${illust.height}
                </span>
                ` : ''}
            </div>
            ${tags.length > 0 ? `
            <div class="illust-tags">
                ${tags.map(tag => `<span class="tag">${escapeHtml(tag.trim())}</span>`).join('')}
            </div>
            ` : ''}
        </div>
    `;

    return card;
}

/**
 * 空の状態を表示
 */
function showEmptyState() {
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;
    
    grid.innerHTML = `
        <div class="empty-state full-span">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <h2>イラストが見つかりません</h2>
            <p>検索条件を変更してみてください</p>
        </div>
    `;
}

/**
 * ローディング表示
 */
function showLoading(show) {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.classList.toggle('show', show);
    }
}

/**
 * エラー表示
 */
function showError(message) {
    alert(message);
}

/**
 * HTML エスケープ
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * 年齢確認済みかチェック
 */
function checkAgeVerification() {
    const verified = localStorage.getItem('age_verified');
    const storedVersion = localStorage.getItem('age_verified_version');
    const currentVersion = String(NSFW_CONFIG_VERSION);

    if (!storedVersion || storedVersion !== currentVersion) {
        localStorage.removeItem('age_verified');
        localStorage.removeItem('age_verified_version');
        return false;
    }

    if (!verified) return false;

    const verifiedTime = parseInt(verified);
    const now = Date.now();
    const expiryMs = AGE_VERIFICATION_MINUTES * 60 * 1000;
    return (now - verifiedTime) < expiryMs;
}

/**
 * 年齢確認を記録
 */
function setAgeVerification() {
    localStorage.setItem('age_verified', Date.now().toString());
    localStorage.setItem('age_verified_version', String(NSFW_CONFIG_VERSION));
}

/**
 * 年齢確認モーダルを表示
 */
function showAgeVerificationModal() {
    const modal = document.getElementById('ageVerificationModal');
    if (modal) modal.classList.add('show');
}

/**
 * 年齢確認モーダルを非表示
 */
function hideAgeVerificationModal() {
    const modal = document.getElementById('ageVerificationModal');
    if (modal) modal.classList.remove('show');
}

/**
 * 年齢確認「はい」ボタン処理
 */
function confirmAge() {
    setAgeVerification();
    hideAgeVerificationModal();

    if (pendingNsfwIllustId === 'filter') {
        // NSFWフィルター変更を続行
        pendingNsfwIllustId = null;
        currentNSFWFilter = 'nsfw';
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.nsfwFilter === 'nsfw');
        });
        resetAndLoad();
    } else if (pendingNsfwIllustId) {
        // 詳細ページへ遷移
        window.location.href = `/paint/detail.php?id=${pendingNsfwIllustId}`;
    }
}

/**
 * 年齢確認「いいえ」ボタン処理
 */
function denyAge() {
    hideAgeVerificationModal();
    pendingNsfwIllustId = null;
}

// Expose functions to global scope for pages that use inline onclick handlers.
// When scripts are loaded with type="module", function declarations are module-scoped
// so inline handlers (e.g. onclick="setNSFWFilter('nsfw')") would fail. Attach
// the handlers we expect the templates to call to window for backwards compatibility.
/* istanbul ignore next */
if (typeof window !== 'undefined') {
    window.setNSFWFilter = setNSFWFilter;
    window.showAllPaints = showAllPaints;
    window.denyAge = denyAge;
    window.confirmAge = confirmAge;
    window.showAgeVerificationModal = showAgeVerificationModal;
    window.hideAgeVerificationModal = hideAgeVerificationModal;
}
