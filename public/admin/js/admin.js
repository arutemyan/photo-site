/**
 * 管理ダッシュボード JavaScript
 *
 * jQuery + AJAX で REST API を呼び出し
 */

// モーダルインスタンス
let editModal;

$(document).ready(function() {
    // Bootstrapモーダルを初期化
    const editModalElement = document.getElementById('editModal');
    if (editModalElement) {
        editModal = new bootstrap.Modal(editModalElement);
    }

    // 投稿一覧を読み込み
    loadPosts();

    // テーマ設定を読み込み
    loadThemeSettings();

    // サイト設定を読み込み
    loadSettings();

    // 画像プレビュー
    $('#image').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        } else {
            $('#imagePreview').hide();
        }
    });

    // アップロードフォーム送信
    $('#uploadForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        // ボタンを無効化
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
        $('#uploadAlert').addClass('d-none');
        $('#uploadError').addClass('d-none');

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // 成功メッセージ
                    $('#uploadAlert').text(response.message || '投稿が作成されました').removeClass('d-none');

                    // フォームをリセット
                    $('#uploadForm')[0].reset();
                    $('#imagePreview').hide();

                    // 投稿一覧を再読み込み
                    loadPosts();

                    // 3秒後にメッセージを消す
                    setTimeout(function() {
                        $('#uploadAlert').addClass('d-none');
                    }, 3000);
                } else {
                    $('#uploadError').text(response.error || 'アップロードに失敗しました').removeClass('d-none');
                }
            },
            error: function(xhr) {
                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#uploadError').text(errorMsg).removeClass('d-none');
            },
            complete: function() {
                // ボタンを有効化
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 一括アップロード: プレビュー表示
    $('#bulkImages').on('change', function(e) {
        const files = e.target.files;
        const $previewList = $('#bulkPreviewList');
        $previewList.empty();

        if (files.length > 0) {
            Array.from(files).forEach(function(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $previewList.append(`
                        <div class="col-4 col-md-3">
                            <img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            });
        }
    });

    // 一括アップロードフォーム送信
    $('#bulkUploadForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        // ボタンを無効化
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
        $('#bulkUploadAlert').addClass('d-none');
        $('#bulkUploadError').addClass('d-none');

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/bulk_upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // 成功メッセージ
                    const msg = `${response.success_count}件の画像をアップロードしました（非表示状態）`;
                    $('#bulkUploadAlert').html(msg).removeClass('d-none');

                    // エラーがあれば表示
                    if (response.error_count > 0) {
                        let errorHtml = `${response.error_count}件の画像が失敗しました:<br>`;
                        response.results.forEach(function(result) {
                            if (!result.success) {
                                errorHtml += `- ${result.filename}: ${result.error}<br>`;
                            }
                        });
                        $('#bulkUploadError').html(errorHtml).removeClass('d-none');
                    }

                    // フォームをリセット
                    $('#bulkUploadForm')[0].reset();
                    $('#bulkPreviewList').empty();

                    // 投稿一覧を再読み込み
                    loadPosts();

                    // 5秒後にメッセージを消す
                    setTimeout(function() {
                        $('#bulkUploadAlert').addClass('d-none');
                        $('#bulkUploadError').addClass('d-none');
                    }, 5000);
                } else {
                    $('#bulkUploadError').text(response.error || '一括アップロードに失敗しました').removeClass('d-none');
                }
            },
            error: function(xhr) {
                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#bulkUploadError').text(errorMsg).removeClass('d-none');
            },
            complete: function() {
                // ボタンを有効化
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // テーマフォーム送信
    $('#themeForm').on('submit', function(e) {
        e.preventDefault();

        const formData = $(this).serialize();
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        // ボタンを無効化
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>保存中...');
        $('#themeAlert').addClass('d-none');
        $('#themeError').addClass('d-none');

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/theme.php',
            type: 'POST',
            data: formData + '&_method=PUT',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // 成功メッセージ
                    $('#themeAlert').text(response.message || 'テーマ設定が保存されました').removeClass('d-none');

                    // プレビューを更新
                    updateThemePreview();

                    // 3秒後にメッセージを消す
                    setTimeout(function() {
                        $('#themeAlert').addClass('d-none');
                    }, 3000);
                } else {
                    $('#themeError').text(response.error || '保存に失敗しました').removeClass('d-none');
                }
            },
            error: function(xhr) {
                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#themeError').text(errorMsg).removeClass('d-none');
            },
            complete: function() {
                // ボタンを有効化
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });


    // リアルタイムプレビュー
    $('#siteTitle, #siteSubtitle, #headerText, #footerText, #primaryColor, #secondaryColor, #accentColor, #backgroundColor, #textColor, #headingColor, #footerBgColor, #footerTextColor, #cardBorderColor, #cardBgColor, #cardShadowOpacity, #linkColor, #linkHoverColor, #tagBgColor, #tagTextColor, #filterActiveBgColor, #filterActiveTextColor').on('input change', function() {
        updateThemePreview();

        // 文字色プレビューを更新
        const textColor = $('#textColor').val();
        $('#textColorPreview').css('color', textColor);

        // タグプレビューを更新
        const tagBgColor = $('#tagBgColor').val();
        const tagTextColor = $('#tagTextColor').val();
        $('#tagColorPreview').css({
            'background-color': tagBgColor,
            'color': tagTextColor
        });

        // フィルタアクティブプレビューを更新
        const filterActiveBgColor = $('#filterActiveBgColor').val();
        const filterActiveTextColor = $('#filterActiveTextColor').val();
        $('#filterActiveColorPreview').css({
            'background-color': filterActiveBgColor,
            'color': filterActiveTextColor
        });

        // カード影の濃さプレビューを更新
        const shadowValue = $('#cardShadowOpacity').val();
        $('#shadowValue').text(shadowValue);
    });

    // iframeロード時にプレビューを更新
    $('#sitePreview').on('load', function() {
        // iframeが完全に読み込まれてからプレビューを適用
        setTimeout(function() {
            updateThemePreview();
        }, 100);
    });

    // レスポンシブプレビュー切り替え
    $('[data-preview-size]').on('click', function() {
        const size = $(this).data('preview-size');
        $('[data-preview-size]').removeClass('active');
        $(this).addClass('active');

        $('#previewFrame').css('max-width', size);

        // アニメーション効果のためのクラス追加
        $('#previewFrame').addClass('preview-resizing');
        setTimeout(function() {
            $('#previewFrame').removeClass('preview-resizing');
        }, 300);
    });

    // ロゴ画像アップロード
    $('#uploadLogo').on('click', function() {
        uploadThemeImage('logo');
    });

    // ヘッダー画像アップロード
    $('#uploadHeader').on('click', function() {
        uploadThemeImage('header');
    });

    // ロゴ画像プレビュー
    $('#logoImage').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#logoPreviewImg').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

    // ヘッダー画像プレビュー
    $('#headerImage').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#headerPreviewImg').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

    // 編集モーダルの保存ボタン
    $('#saveEditBtn').on('click', function() {
        savePost();
    });

    // 前の投稿へ移動
    $('#prevPostBtn').on('click', function() {
        if (currentEditingIndex > 0) {
            const prevPost = allPosts[currentEditingIndex - 1];
            editPost(prevPost.id);
        }
    });

    // 次の投稿へ移動
    $('#nextPostBtn').on('click', function() {
        if (currentEditingIndex < allPosts.length - 1) {
            const nextPost = allPosts[currentEditingIndex + 1];
            editPost(nextPost.id);
        }
    });

    // 設定フォーム送信
    $('#settingsForm').on('submit', function(e) {
        e.preventDefault();
        saveSettings();
    });

    // 全選択ボタン
    $('#selectAllBtn').on('click', function() {
        const $checkboxes = $('.post-select-checkbox');
        const allChecked = $checkboxes.length > 0 && $checkboxes.filter(':checked').length === $checkboxes.length;

        $checkboxes.prop('checked', !allChecked);
        updateBulkActionButtons();

        // ボタンのテキストを切り替え
        if (!allChecked) {
            $(this).html('<i class="bi bi-square me-1"></i>全解除');
        } else {
            $(this).html('<i class="bi bi-check-square me-1"></i>全選択');
        }
    });

    // 一括公開ボタン
    $('#bulkPublishBtn').on('click', function() {
        bulkUpdateVisibility(1);
    });

    // 一括非公開ボタン
    $('#bulkUnpublishBtn').on('click', function() {
        bulkUpdateVisibility(0);
    });
});

// グローバル変数（ページネーション用）
let postsOffset = 0;
let postsLimit = 30;
let allPosts = [];
let totalPostsCount = 0;
let currentEditingIndex = -1; // 現在編集中の投稿のインデックス

/**
 * 投稿一覧を読み込み
 */
function loadPosts(append = false) {
    if (!append) {
        // 初回読み込みの場合はリセット
        postsOffset = 0;
        allPosts = [];
    }

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php',
        type: 'GET',
        data: {
            limit: postsLimit,
            offset: postsOffset
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.posts) {
                totalPostsCount = response.total || 0;

                if (append) {
                    // 追加読み込みの場合
                    allPosts = allPosts.concat(response.posts);
                } else {
                    // 初回読み込みの場合
                    allPosts = response.posts;
                }

                renderPosts(allPosts, response.hasMore);
                postsOffset += response.posts.length;
            } else {
                $('#postsList').html('<div class="text-center p-4 text-muted">投稿がありません</div>');
            }
        },
        error: function() {
            $('#postsList').html('<div class="text-center p-4 text-danger">投稿の読み込みに失敗しました</div>');
        }
    });
}

/**
 * さらに投稿を読み込み
 */
function loadMorePosts() {
    loadPosts(true);
}

/**
 * 投稿一覧をレンダリング（グリッドレイアウト）
 */
function renderPosts(posts, hasMore = false) {
    if (posts.length === 0) {
        $('#postsList').html('<div class="text-center p-4 text-muted">投稿がありません</div>');
        $('#bulkActionButtons').hide();
        return;
    }

    // 一括操作ボタンを表示
    $('#bulkActionButtons').show();

    let html = '<div class="posts-grid">';
    posts.forEach(function(post) {
        const thumbPath = post.thumb_path || post.image_path || '';
        const tags = post.tags || '';
        const detail = post.detail || '';
        const createdAt = new Date(post.created_at).toLocaleDateString('ja-JP');
        const isSensitive = post.is_sensitive == 1;
        const isVisible = post.is_visible == 1;

        html += `
            <div class="post-card ${!isVisible ? 'post-card-hidden' : ''}" data-id="${post.id}">
                <div class="post-card-checkbox">
                    <input type="checkbox" class="form-check-input post-select-checkbox" data-post-id="${post.id}" onchange="updateBulkActionButtons()">
                </div>
                <div class="post-card-image">
                    <img src="/${thumbPath}" alt="${escapeHtml(post.title)}" onerror="this.src='/uploads/thumbs/placeholder.webp'">
                    <div class="post-card-overlay">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary" onclick="editPost(${post.id})" title="編集">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-success" onclick="shareToSNS(${post.id}, '${escapeHtml(post.title)}', ${isSensitive})" title="SNS共有">
                                <i class="bi bi-share"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deletePost(${post.id})" title="削除">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="post-card-body">
                    <div class="post-card-title">${escapeHtml(post.title)}</div>
                    ${detail ? '<div class="post-card-description">' + escapeHtml(detail) + '</div>' : ''}
                    <div class="post-card-meta">
                        ${!isVisible ? '<span class="badge bg-warning text-dark me-1"><i class="bi bi-eye-slash"></i> 非表示</span>' : ''}
                        ${isSensitive ? '<span class="badge bg-danger me-1">NSFW</span>' : ''}
                        ${tags ? '<span class="badge bg-secondary me-1">' + escapeHtml(tags) + '</span>' : ''}
                    </div>
                    <div class="post-card-date text-muted">${createdAt}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    // 件数表示と「もっと見る」ボタン
    html += '<div class="posts-footer text-center mt-3 mb-3">';
    html += `<p class="text-muted mb-2">表示中: ${posts.length}件 / 全${totalPostsCount}件</p>`;
    if (hasMore) {
        html += '<button class="btn btn-outline-primary" onclick="loadMorePosts()"><i class="bi bi-arrow-down-circle me-2"></i>もっと見る</button>';
    }
    html += '</div>';

    $('#postsList').html(html);
}

/**
 * 投稿を削除
 */
function deletePost(postId) {
    if (!confirm('この投稿を削除してもよろしいですか?')) {
        return;
    }

    const csrfToken = $('input[name="csrf_token"]').val();

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php?id=' + postId,
        type: 'POST',
        data: {
            _method: 'DELETE',
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 投稿一覧を再読み込み
                loadPosts();

                // 成功メッセージ
                $('#uploadAlert').text(response.message || '投稿が削除されました').removeClass('d-none');
                setTimeout(function() {
                    $('#uploadAlert').addClass('d-none');
                }, 3000);
            } else {
                alert(response.error || '削除に失敗しました');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            alert(errorMsg);
        }
    });
}

/**
 * HTMLエスケープ
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

/**
 * テーマ設定を読み込み
 */
function loadThemeSettings() {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/theme.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.theme) {
                const theme = response.theme;

                // サイト情報
                $('#siteTitle').val(theme.site_title || '');
                $('#siteSubtitle').val(theme.site_subtitle || '');
                $('#siteDescription').val(theme.site_description || '');

                // ヘッダー色
                $('#primaryColor').val(theme.primary_color || '#8B5AFA');
                $('#secondaryColor').val(theme.secondary_color || '#667eea');
                $('#headingColor').val(theme.heading_color || '#ffffff');

                // コンテンツ色
                $('#backgroundColor').val(theme.background_color || '#1a1a1a');
                $('#textColor').val(theme.text_color || '#ffffff');
                $('#accentColor').val(theme.accent_color || '#FFD700');
                $('#linkColor').val(theme.link_color || '#8B5AFA');
                $('#linkHoverColor').val(theme.link_hover_color || '#a177ff');
                $('#tagBgColor').val(theme.tag_bg_color || '#8B5AFA');
                $('#tagTextColor').val(theme.tag_text_color || '#ffffff');
                $('#filterActiveBgColor').val(theme.filter_active_bg_color || '#8B5AFA');
                $('#filterActiveTextColor').val(theme.filter_active_text_color || '#ffffff');

                // カード設定
                $('#cardBgColor').val(theme.card_bg_color || '#252525');
                $('#cardBorderColor').val(theme.card_border_color || '#333333');
                $('#cardShadowOpacity').val(theme.card_shadow_opacity || '0.3');

                // フッター設定
                $('#footerBgColor').val(theme.footer_bg_color || '#2a2a2a');
                $('#footerTextColor').val(theme.footer_text_color || '#cccccc');

                // カスタムHTML
                $('#headerText').val(theme.header_html || '');
                $('#footerText').val(theme.footer_html || '');

                // 画像プレビュー
                if (theme.logo_image) {
                    $('#logoPreviewImg').attr('src', '/' + theme.logo_image).show();
                }
                if (theme.header_image) {
                    $('#headerPreviewImg').attr('src', '/' + theme.header_image).show();
                }

                // プレビューを更新
                updateThemePreview();
            }
        },
        error: function() {
            console.error('Failed to load theme settings');
        }
    });
}

/**
 * テーマプレビューを更新（リアルタイムiframeプレビュー）
 */
function updateThemePreview() {
    const siteTitle = $('#siteTitle').val() || 'イラストポートフォリオ';
    const siteSubtitle = $('#siteSubtitle').val() || 'Illustration Portfolio';
    const headerText = $('#headerText').val() || siteTitle;
    const footerText = $('#footerText').val() || '© 2025 Portfolio Site. All rights reserved.';
    const primaryColor = $('#primaryColor').val() || '#8B5AFA';
    const secondaryColor = $('#secondaryColor').val() || '#667eea';
    const accentColor = $('#accentColor').val() || '#FFD700';
    const backgroundColor = $('#backgroundColor').val() || '#1a1a1a';
    const textColor = $('#textColor').val() || '#ffffff';
    const headingColor = $('#headingColor').val() || '#ffffff';
    const footerBgColor = $('#footerBgColor').val() || '#2a2a2a';
    const footerTextColor = $('#footerTextColor').val() || '#cccccc';
    const cardBorderColor = $('#cardBorderColor').val() || '#333333';
    const cardBgColor = $('#cardBgColor').val() || '#252525';
    const linkColor = $('#linkColor').val() || '#8B5AFA';
    const linkHoverColor = $('#linkHoverColor').val() || '#a177ff';
    const tagBgColor = $('#tagBgColor').val() || '#8B5AFA';
    const tagTextColor = $('#tagTextColor').val() || '#ffffff';
    const filterActiveBgColor = $('#filterActiveBgColor').val() || '#8B5AFA';
    const filterActiveTextColor = $('#filterActiveTextColor').val() || '#ffffff';

    try {
        // iframeのドキュメントを取得
        const iframe = document.getElementById('sitePreview');
        if (!iframe || !iframe.contentWindow) {
            console.warn('Preview iframe not ready');
            return;
        }

        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        if (!iframeDoc) {
            console.warn('Cannot access iframe document');
            return;
        }

        // CSS変数を更新
        const root = iframeDoc.documentElement;
        if (root) {
            root.style.setProperty('--primary-color', primaryColor);
            root.style.setProperty('--secondary-color', secondaryColor);
            root.style.setProperty('--accent-color', accentColor);
            root.style.setProperty('--background-color', backgroundColor);
            root.style.setProperty('--text-color', textColor);
            root.style.setProperty('--heading-color', headingColor);
            root.style.setProperty('--footer-bg-color', footerBgColor);
            root.style.setProperty('--footer-text-color', footerTextColor);
            root.style.setProperty('--card-border-color', cardBorderColor);
            root.style.setProperty('--card-bg-color', cardBgColor);
            root.style.setProperty('--link-color', linkColor);
            root.style.setProperty('--link-hover-color', linkHoverColor);
            root.style.setProperty('--tag-bg-color', tagBgColor);
            root.style.setProperty('--tag-text-color', tagTextColor);
            root.style.setProperty('--filter-active-bg-color', filterActiveBgColor);
            root.style.setProperty('--filter-active-text-color', filterActiveTextColor);

            // ヘッダーテキストを更新
            const headerH1 = iframeDoc.querySelector('header h1');
            const headerP = iframeDoc.querySelector('header p');
            if (headerH1) {
                headerH1.textContent = headerText || siteTitle;
            }
            if (headerP) {
                headerP.textContent = siteSubtitle;
            }

            // フッターテキストを更新
            const footer = iframeDoc.querySelector('footer p');
            if (footer) {
                footer.innerHTML = footerText.replace(/\n/g, '<br>');
            }
        }
    } catch (error) {
        // クロスオリジン制約でエラーになる場合があるが、同一オリジンなので基本的には問題ない
        console.warn('Preview update error:', error);
    }
}

/**
 * 投稿を編集
 */
function editPost(postId) {
    // 現在の投稿のインデックスを保存
    currentEditingIndex = allPosts.findIndex(p => p.id == postId);

    // 投稿データを取得（管理画面用API）
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php?id=' + postId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.post) {
                const post = response.post;

                // フォームに値を設定
                $('#editPostId').val(post.id);
                $('#editTitle').val(post.title || '');
                $('#editTags').val(post.tags || '');
                $('#editDetail').val(post.detail || '');

                // センシティブフラグを設定
                $('#editIsSensitive').prop('checked', post.is_sensitive == 1);

                // 表示/非表示フラグを設定
                $('#editIsVisible').prop('checked', post.is_visible == 1);

                // 画像プレビュー
                const imagePath = post.image_path || post.thumb_path || '';
                if (imagePath) {
                    $('#editImagePreview').attr('src', '/' + imagePath).show();
                } else {
                    $('#editImagePreview').hide();
                }

                // アラートをリセット
                $('#editAlert').addClass('d-none');
                $('#editError').addClass('d-none');

                // ナビゲーションボタンの有効/無効を設定
                updateNavigationButtons();

                // モーダルを表示
                if (editModal) {
                    editModal.show();
                }
            } else {
                alert('投稿データの取得に失敗しました');
            }
        },
        error: function(xhr) {
            let errorMsg = '投稿データの取得に失敗しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            alert(errorMsg);
        }
    });
}

/**
 * ナビゲーションボタンの有効/無効を更新
 */
function updateNavigationButtons() {
    const hasPrev = currentEditingIndex > 0;
    const hasNext = currentEditingIndex < allPosts.length - 1;

    $('#prevPostBtn').prop('disabled', !hasPrev);
    $('#nextPostBtn').prop('disabled', !hasNext);
}

/**
 * 特定の投稿だけを更新（ページ位置を保持）
 */
function updateSinglePost(postId) {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php?id=' + postId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.post) {
                const updatedPost = response.post;

                // allPosts配列内の該当投稿を更新
                const index = allPosts.findIndex(p => p.id == postId);
                if (index !== -1) {
                    allPosts[index] = updatedPost;
                }

                // DOM内の該当カードを更新
                updatePostCard(updatedPost);
            }
        },
        error: function() {
            // エラー時は安全のため全体を再読み込み
            console.warn('Failed to update single post, reloading all posts');
            loadPosts();
        }
    });
}

/**
 * 投稿カードのDOMを更新
 */
function updatePostCard(post) {
    const $card = $(`.post-card[data-id="${post.id}"]`);
    if ($card.length === 0) return;

    const thumbPath = post.thumb_path || post.image_path || '';
    const tags = post.tags || '';
    const detail = post.detail || '';
    const isSensitive = post.is_sensitive == 1;
    const isVisible = post.is_visible == 1;

    // カードの表示状態を更新
    if (isVisible) {
        $card.removeClass('post-card-hidden');
    } else {
        $card.addClass('post-card-hidden');
    }

    // 画像を更新
    $card.find('.post-card-image img').attr('src', '/' + thumbPath);

    // タイトルを更新
    $card.find('.post-card-title').text(post.title);

    // 詳細を更新
    const $description = $card.find('.post-card-description');
    if (detail) {
        if ($description.length > 0) {
            $description.text(detail);
        } else {
            $card.find('.post-card-title').after(`<div class="post-card-description">${escapeHtml(detail)}</div>`);
        }
    } else {
        $description.remove();
    }

    // メタ情報（バッジ）を更新
    const $meta = $card.find('.post-card-meta');
    $meta.empty();

    // 表示/非表示バッジ
    if (!isVisible) {
        $meta.append('<span class="badge bg-warning text-dark me-1"><i class="bi bi-eye-slash"></i> 非表示</span>');
    }

    // センシティブバッジ
    if (isSensitive) {
        $meta.append('<span class="badge bg-danger me-1">NSFW</span>');
    }

    // タグバッジ
    if (tags) {
        $meta.append(`<span class="badge bg-secondary me-1">${escapeHtml(tags)}</span>`);
    }
}

/**
 * 投稿を保存
 */
function savePost() {
    const formData = $('#editForm').serialize();
    const $saveBtn = $('#saveEditBtn');
    const originalText = $saveBtn.html();

    // ボタンを無効化
    $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>保存中...');
    $('#editAlert').addClass('d-none');
    $('#editError').addClass('d-none');

    // 投稿IDを取得
    const postId = $('#editPostId').val();

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php?id=' + postId,
        type: 'POST',
        data: formData + '&_method=PUT',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#editAlert').text(response.message || '投稿が更新されました').removeClass('d-none');

                // 投稿一覧を再読み込みせず、該当の投稿だけを更新
                updateSinglePost(postId);

                // 2秒後にモーダルを閉じる
                //setTimeout(function() {
                //    if (editModal) {
                //        editModal.hide();
                //    }
                //    $('#editAlert').addClass('d-none');
                //}, 1500);

                // 成功メッセージをトップにも表示
                $('#uploadAlert').text(response.message || '投稿が更新されました').removeClass('d-none');
                setTimeout(function() {
                    $('#uploadAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#editError').text(response.error || '保存に失敗しました').removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#editError').text(errorMsg).removeClass('d-none');
        },
        complete: function() {
            // ボタンを有効化
            $saveBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * サイト設定を読み込み
 */
function loadSettings() {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/settings.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.settings) {
                response.settings.forEach(function(setting) {
                    if (setting.key === 'show_view_count') {
                        $('#showViewCount').prop('checked', setting.value === '1');
                    }
                });
            }
        },
        error: function() {
            console.error('Failed to load settings');
        }
    });
}

/**
 * テーマ画像をアップロード
 */
function uploadThemeImage(imageType) {
    const fileInputId = imageType === 'logo' ? '#logoImage' : '#headerImage';
    const file = $(fileInputId)[0].files[0];

    if (!file) {
        alert('画像ファイルを選択してください');
        return;
    }

    const formData = new FormData();
    formData.append('image', file);
    formData.append('image_type', imageType);
    formData.append('csrf_token', $('input[name="csrf_token"]').val());

    const $uploadBtn = imageType === 'logo' ? $('#uploadLogo') : $('#uploadHeader');
    const originalText = $uploadBtn.html();

    // ボタンを無効化
    $uploadBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/theme-image.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#themeAlert').text(response.message || '画像がアップロードされました').removeClass('d-none');

                // プレビュー画像を更新
                const previewImgId = imageType === 'logo' ? '#logoPreviewImg' : '#headerPreviewImg';
                $(previewImgId).attr('src', '/' + response.image_path).show();

                // テーマ設定を再読み込み
                loadThemeSettings();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#themeAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#themeError').text(response.error || 'アップロードに失敗しました').removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#themeError').text(errorMsg).removeClass('d-none');
        },
        complete: function() {
            // ボタンを有効化
            $uploadBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * サイト設定を保存
 */
function saveSettings() {
    const showViewCount = $('#showViewCount').is(':checked') ? '1' : '0';
    const csrfToken = $('input[name="csrf_token"]').val();

    $('#settingsAlert').addClass('d-none').removeClass('alert-success alert-danger');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/settings.php',
        type: 'POST',
        data: {
            show_view_count: showViewCount,
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#settingsAlert')
                    .addClass('alert-success')
                    .text(response.message || '設定が保存されました')
                    .removeClass('d-none');

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#settingsAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#settingsAlert')
                    .addClass('alert-danger')
                    .text(response.error || '保存に失敗しました')
                    .removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#settingsAlert')
                .addClass('alert-danger')
                .text(errorMsg)
                .removeClass('d-none');
        }
    });
}

/**
 * 一括操作ボタンの有効/無効を更新
 */
function updateBulkActionButtons() {
    const $checked = $('.post-select-checkbox:checked');
    const count = $checked.length;

    // 選択数に応じてボタンを有効/無効化
    $('#bulkPublishBtn').prop('disabled', count === 0);
    $('#bulkUnpublishBtn').prop('disabled', count === 0);

    // 全選択ボタンのテキストを更新
    const $allCheckboxes = $('.post-select-checkbox');
    const allChecked = $allCheckboxes.length > 0 && count === $allCheckboxes.length;

    if (allChecked) {
        $('#selectAllBtn').html('<i class="bi bi-square me-1"></i>全解除');
    } else {
        $('#selectAllBtn').html('<i class="bi bi-check-square me-1"></i>全選択');
    }

    // 選択件数バッジを更新
    const $selectionCount = $('#selectionCount');
    if (count > 0) {
        $selectionCount.text(`${count}件選択中`).show();
    } else {
        $selectionCount.hide();
    }
}

/**
 * 一括で公開/非公開を更新
 */
function bulkUpdateVisibility(visibility) {
    const $checked = $('.post-select-checkbox:checked');
    const postIds = [];

    $checked.each(function() {
        postIds.push($(this).data('post-id'));
    });

    if (postIds.length === 0) {
        alert('投稿を選択してください');
        return;
    }

    const action = visibility === 1 ? '公開' : '非公開';
    if (!confirm(`選択した${postIds.length}件の投稿を${action}にしますか？`)) {
        return;
    }

    const csrfToken = $('input[name="csrf_token"]').val();

    // ボタンを無効化
    const $publishBtn = $('#bulkPublishBtn');
    const $unpublishBtn = $('#bulkUnpublishBtn');
    const originalPublishText = $publishBtn.html();
    const originalUnpublishText = $unpublishBtn.html();

    $publishBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>処理中...');
    $unpublishBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>処理中...');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php',
        type: 'POST',
        data: {
            _method: 'PATCH',
            post_ids: postIds,
            is_visible: visibility,
            csrf_token: csrfToken
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#uploadAlert').text(response.message || `${postIds.length}件の投稿を${action}にしました`).removeClass('d-none');

                // 投稿一覧を再読み込み
                loadPosts();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#uploadAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#uploadError').text(response.error || '一括更新に失敗しました').removeClass('d-none');
                setTimeout(function() {
                    $('#uploadError').addClass('d-none');
                }, 3000);
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#uploadError').text(errorMsg).removeClass('d-none');
            setTimeout(function() {
                $('#uploadError').addClass('d-none');
            }, 3000);
        },
        complete: function() {
            // ボタンを有効化
            $publishBtn.prop('disabled', false).html(originalPublishText);
            $unpublishBtn.prop('disabled', false).html(originalUnpublishText);
            updateBulkActionButtons();
        }
    });
}
