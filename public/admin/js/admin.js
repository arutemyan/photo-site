/**
 * 管理ダッシュボード JavaScript
 *
 * jQuery + AJAX で REST API を呼び出し
 */

// モーダルインスタンス
let editModal;

// クリップボードから貼り付けた画像を保持
let clipboardImageFile = null;

$(document).ready(function() {
    // Bootstrapモーダルを初期化
    const editModalElement = document.getElementById('editModal');
    if (editModalElement) {
        editModal = new bootstrap.Modal(editModalElement);
    }

    // 投稿一覧を読み込み
    loadPosts();

    // テーマ設定を読み込み（テーマタブがアクティブな場合のみ）
    if ($('#theme-tab').hasClass('active')) {
        loadThemeSettings();
    }

    // サイト設定を読み込み
    loadSettings();

    // テーマタブがフォーカスされたときにテーマ設定を読み込み
    $('#theme-tab').on('shown.bs.tab', function() {
        loadThemeSettings();
        // プレビューを再読み込み
        const iframe = document.getElementById('sitePreview');
        if (iframe) {
            iframe.src = iframe.src; // iframeを再読み込み
        }
    });

    // クリップボードアップロードのトグル
    $('#toggleClipboardUpload').on('click', function() {
        const $section = $('#clipboardUploadSection');
        const $icon = $('#clipboardToggleIcon');

        if ($section.is(':visible')) {
            $section.slideUp(300);
            $icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
        } else {
            $section.slideDown(300);
            $icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
            // フォーカスを当てる
            setTimeout(function() {
                $('#clipboardPasteArea').focus();
            }, 350);
        }
    });

    // クリップボードペーストエリアのイベント
    $('#clipboardPasteArea').on('paste', function(e) {
        handleClipboardPaste(e.originalEvent);
    });

    // クリップボードペーストエリアのクリックでフォーカス
    $('#clipboardPasteArea').on('click', function() {
        $(this).focus();
    });

    // クリップボード画像をクリア
    $('#clearClipboardImage').on('click', function() {
        clearClipboardImage();
    });

    // クリップボードフォーム送信
    $('#clipboardUploadForm').on('submit', function(e) {
        e.preventDefault();
        uploadClipboardImage();
    });

    // クリップボードキャンセル
    $('#clipboardCancelBtn').on('click', function() {
        clearClipboardImage();
        $('#clipboardUploadForm')[0].reset();
        $('#clipboardUploadSection').slideUp(300);
        $('#clipboardToggleIcon').removeClass('bi-chevron-up').addClass('bi-chevron-down');
    });

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

    // 編集モーダル：差し替え画像プレビュー
    $('#editImageFile').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#editImageReplacePreviewImg').attr('src', e.target.result);
                $('#editImageReplacePreview').show();
            };
            reader.readAsDataURL(file);
        } else {
            $('#editImageReplacePreview').hide();
        }
    });

    // アップロードフォーム送信
    $('#uploadForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        // チェックボックスの値を明示的に設定（チェックされていない場合も '0' として送信）
        formData.set('is_sensitive', $('#isSensitive').is(':checked') ? '1' : '0');
        formData.set('is_visible', $('#isVisible').is(':checked') ? '1' : '0');

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


    // 一覧に戻るボタンのプレビュー更新
    $('#backButtonText, #backButtonBgColor, #backButtonTextColor').on('input change', function() {
        updateBackButtonPreview();
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

    // ロゴ画像削除
    $('#deleteLogo').on('click', function() {
        if (confirm('ロゴ画像を削除しますか？')) {
            deleteThemeImage('logo');
        }
    });

    // ヘッダー画像削除
    $('#deleteHeader').on('click', function() {
        if (confirm('背景画像を削除しますか？')) {
            deleteThemeImage('header');
        }
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

    // OGP画像アップロード
    $('#uploadOgpImage').on('click', function() {
        uploadOgpImage();
    });

    // OGP画像削除
    $('#deleteOgpImage').on('click', function() {
        if (confirm('OGP画像を削除しますか？')) {
            deleteOgpImage();
        }
    });

    // OGP画像プレビュー
    $('#ogpImageFile').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#ogpImagePreviewImg').attr('src', e.target.result).show();
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
        const isGroupPost = post.post_type == 1;

        // post_typeに応じて編集関数を切り替え
        const editFunction = isGroupPost ? 'editGroupPost' : 'editPost';
        const deleteFunction = isGroupPost ? 'deleteGroupPost' : 'deletePost';
        const shareFunction = isGroupPost ? 'shareGroupPostToSNS' : 'shareToSNS';

        html += `
            <div class="post-card ${!isVisible ? 'post-card-hidden' : ''}" data-id="${post.id}">
                <div class="post-card-checkbox">
                    <input type="checkbox" class="form-check-input post-select-checkbox" data-post-id="${post.id}" onchange="updateBulkActionButtons()">
                </div>
                <div class="post-card-image">
                    <img src="/${thumbPath}" alt="${escapeHtml(post.title)}" onerror="this.src='/uploads/thumbs/placeholder.webp'">
                    ${isGroupPost && post.image_count ? '<span class="badge bg-info position-absolute top-0 end-0 m-2"><i class="bi bi-images"></i> ' + post.image_count + '</span>' : ''}
                    <div class="post-card-overlay">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary" onclick="${editFunction}(${post.id})" title="編集">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-success" onclick="${shareFunction}(${post.id}, '${escapeHtml(post.title).replace(/'/g, "\\'")}', ${isSensitive})" title="SNS共有">
                                <i class="bi bi-share"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="${deleteFunction}(${post.id}${isGroupPost ? ", '" + escapeHtml(post.title).replace(/'/g, "\\'") + "'" : ''})" title="削除">
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
                        ${isGroupPost ? '<span class="badge bg-primary me-1"><i class="bi bi-images"></i> グループ</span>' : ''}
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

                // ナビゲーション設定（一覧に戻るボタン）
                $('#backButtonText').val(theme.back_button_text || '一覧に戻る');
                $('#backButtonBgColor').val(theme.back_button_bg_color || '#8B5AFA');
                $('#backButtonTextColor').val(theme.back_button_text_color || '#FFFFFF');

                // 画像プレビュー
                if (theme.logo_image) {
                    $('#logoPreviewImg').attr('src', '/' + theme.logo_image).show();
                    $('#deleteLogo').show();
                } else {
                    $('#logoPreviewImg').hide();
                    $('#deleteLogo').hide();
                }
                if (theme.header_image) {
                    $('#headerPreviewImg').attr('src', '/' + theme.header_image).show();
                    $('#deleteHeader').show();
                } else {
                    $('#headerPreviewImg').hide();
                    $('#deleteHeader').hide();
                }

                // プレビューを更新
                updateThemePreview();
                updateBackButtonPreview();
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

                // 表示順序を設定
                $('#editSortOrder').val(post.sort_order || 0);

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

                // 画像差し替えフィールドをリセット
                $('#editImageFile').val('');
                $('#editImageReplacePreview').hide();

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
    const isGroupPost = post.post_type == 1;

    // カードの表示状態を更新
    if (isVisible) {
        $card.removeClass('post-card-hidden');
    } else {
        $card.addClass('post-card-hidden');
    }

    // 画像を更新
    $card.find('.post-card-image img').attr('src', '/' + thumbPath);

    // グループ投稿の画像数バッジを更新
    const $imageBadge = $card.find('.post-card-image .badge.bg-info');
    if (isGroupPost && post.image_count) {
        if ($imageBadge.length > 0) {
            $imageBadge.html(`<i class="bi bi-images"></i> ${post.image_count}`);
        } else {
            $card.find('.post-card-image').append(`<span class="badge bg-info position-absolute top-0 end-0 m-2"><i class="bi bi-images"></i> ${post.image_count}</span>`);
        }
    } else {
        $imageBadge.remove();
    }

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

    // グループ投稿バッジ
    if (isGroupPost) {
        $meta.append('<span class="badge bg-primary me-1"><i class="bi bi-images"></i> グループ</span>');
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
    const $saveBtn = $('#saveEditBtn');
    const originalText = $saveBtn.html();

    // ボタンを無効化
    $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>保存中...');
    $('#editAlert').addClass('d-none');
    $('#editError').addClass('d-none');

    // 投稿IDを取得
    const postId = $('#editPostId').val();

    // FormDataを作成（画像ファイルを含める）
    const formData = new FormData($('#editForm')[0]);
    formData.append('_method', 'PUT');

    // 画像ファイルが選択されている場合は追加
    const imageFile = $('#editImageFile')[0].files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }

    // チェックボックスの値を明示的に設定
    formData.set('is_sensitive', $('#editIsSensitive').is(':checked') ? '1' : '0');
    formData.set('is_visible', $('#editIsVisible').is(':checked') ? '1' : '0');

    // 表示順序を設定
    formData.set('sort_order', $('#editSortOrder').val() || '0');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/posts.php?id=' + postId,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#editAlert').text(response.message || '投稿が更新されました').removeClass('d-none');

                // 投稿一覧を再読み込みせず、該当の投稿だけを更新
                updateSinglePost(postId);

                // 画像ファイル入力とプレビューをクリア
                $('#editImageFile').val('');
                $('#editImageReplacePreview').hide();

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
                    } else if (setting.key === 'ogp_title') {
                        $('#ogpTitle').val(setting.value || '');
                    } else if (setting.key === 'ogp_description') {
                        $('#ogpDescription').val(setting.value || '');
                    } else if (setting.key === 'ogp_image') {
                        if (setting.value) {
                            $('#ogpImagePreviewImg').attr('src', '/' + setting.value).show();
                            $('#deleteOgpImage').show();
                        } else {
                            $('#ogpImagePreviewImg').hide();
                            $('#deleteOgpImage').hide();
                        }
                    } else if (setting.key === 'twitter_card') {
                        $('#twitterCard').val(setting.value || 'summary_large_image');
                    } else if (setting.key === 'twitter_site') {
                        $('#twitterSite').val(setting.value || '');
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
 * テーマ画像を削除
 */
function deleteThemeImage(imageType) {
    const $deleteBtn = imageType === 'logo' ? $('#deleteLogo') : $('#deleteHeader');
    const originalText = $deleteBtn.html();

    // ボタンを無効化
    $deleteBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>削除中...');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/theme-image.php',
        type: 'POST',
        data: {
            _method: 'DELETE',
            image_type: imageType,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#themeAlert').text(response.message || '画像が削除されました').removeClass('d-none');

                // プレビュー画像を非表示
                const previewImgId = imageType === 'logo' ? '#logoPreviewImg' : '#headerPreviewImg';
                $(previewImgId).hide();
                $deleteBtn.hide();

                // テーマ設定を再読み込み
                loadThemeSettings();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#themeAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#themeError').text(response.error || '削除に失敗しました').removeClass('d-none');
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
            $deleteBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * OGP画像をアップロード
 */
function uploadOgpImage() {
    const file = $('#ogpImageFile')[0].files[0];

    if (!file) {
        alert('画像ファイルを選択してください');
        return;
    }

    const formData = new FormData();
    formData.append('image', file);
    formData.append('csrf_token', $('input[name="csrf_token"]').val());

    const $uploadBtn = $('#uploadOgpImage');
    const originalText = $uploadBtn.html();

    // ボタンを無効化
    $uploadBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
    $('#settingsAlert').addClass('d-none');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/ogp-image.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#settingsAlert')
                    .addClass('alert-success')
                    .text(response.message || 'OGP画像がアップロードされました')
                    .removeClass('d-none');

                // プレビュー画像を更新
                $('#ogpImagePreviewImg').attr('src', '/' + response.image_path).show();
                $('#deleteOgpImage').show();

                // 設定を再読み込み
                loadSettings();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#settingsAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#settingsAlert')
                    .addClass('alert-danger')
                    .text(response.error || 'アップロードに失敗しました')
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
        },
        complete: function() {
            // ボタンを有効化
            $uploadBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * OGP画像を削除
 */
function deleteOgpImage() {
    const $deleteBtn = $('#deleteOgpImage');
    const originalText = $deleteBtn.html();

    // ボタンを無効化
    $deleteBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>削除中...');
    $('#settingsAlert').addClass('d-none');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/ogp-image.php',
        type: 'POST',
        data: {
            _method: 'DELETE',
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 成功メッセージ
                $('#settingsAlert')
                    .addClass('alert-success')
                    .text(response.message || 'OGP画像が削除されました')
                    .removeClass('d-none');

                // プレビュー画像を非表示
                $('#ogpImagePreviewImg').hide();
                $deleteBtn.hide();

                // 設定を再読み込み
                loadSettings();

                // 3秒後にメッセージを消す
                setTimeout(function() {
                    $('#settingsAlert').addClass('d-none');
                }, 3000);
            } else {
                $('#settingsAlert')
                    .addClass('alert-danger')
                    .text(response.error || '削除に失敗しました')
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
        },
        complete: function() {
            // ボタンを有効化
            $deleteBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * サイト設定を保存
 */
function saveSettings() {
    const showViewCount = $('#showViewCount').is(':checked') ? '1' : '0';
    const ogpTitle = $('#ogpTitle').val();
    const ogpDescription = $('#ogpDescription').val();
    const twitterCard = $('#twitterCard').val();
    const twitterSite = $('#twitterSite').val();
    const csrfToken = $('input[name="csrf_token"]').val();

    $('#settingsAlert').addClass('d-none').removeClass('alert-success alert-danger');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/settings.php',
        type: 'POST',
        data: {
            show_view_count: showViewCount,
            ogp_title: ogpTitle,
            ogp_description: ogpDescription,
            twitter_card: twitterCard,
            twitter_site: twitterSite,
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
 * 一覧に戻るボタンのプレビューを更新
 */
function updateBackButtonPreview() {
    const text = $('#backButtonText').val() || '一覧に戻る';
    const bgColor = $('#backButtonBgColor').val() || '#8B5AFA';
    const textColor = $('#backButtonTextColor').val() || '#FFFFFF';

    $('#backButtonPreview')
        .text(text)
        .css({
            'background-color': bgColor,
            'color': textColor
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

/**
 * クリップボードから画像を貼り付け
 */
function handleClipboardPaste(event) {
    const items = event.clipboardData.items;

    for (let i = 0; i < items.length; i++) {
        const item = items[i];

        // 画像アイテムのみを処理
        if (item.type.indexOf('image') !== -1) {
            const blob = item.getAsFile();

            // BlobをFileオブジェクトに変換
            const format = $('#clipboardFormat').val() || 'webp';
            const extension = format === 'jpg' ? 'jpg' : format;
            const timestamp = Date.now();
            const filename = `clipboard_${timestamp}.${extension}`;

            clipboardImageFile = new File([blob], filename, {
                type: blob.type,
                lastModified: Date.now()
            });

            // プレビュー表示
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#clipboardPreviewImg').attr('src', e.target.result);
                $('#clipboardPasteHint').hide();
                $('#clipboardPreview').show();
                $('#clipboardUploadBtn').prop('disabled', false);
            };
            reader.readAsDataURL(blob);

            $('#clipboardError').addClass('d-none');
            break;
        }
    }

    // 画像以外の場合はエラー表示
    if (!clipboardImageFile) {
        $('#clipboardError').text('画像が見つかりません。画像をコピーしてから貼り付けてください。').removeClass('d-none');
    }
}

/**
 * クリップボード画像をクリア
 */
function clearClipboardImage() {
    clipboardImageFile = null;
    $('#clipboardPreviewImg').attr('src', '');
    $('#clipboardPreview').hide();
    $('#clipboardPasteHint').show();
    $('#clipboardUploadBtn').prop('disabled', true);
    $('#clipboardError').addClass('d-none');
    $('#clipboardAlert').addClass('d-none');
}

/**
 * クリップボード画像をアップロード
 */
function uploadClipboardImage() {
    if (!clipboardImageFile) {
        $('#clipboardError').text('画像が選択されていません').removeClass('d-none');
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', $('input[name="csrf_token"]').val());
    formData.append('title', $('#clipboardTitle').val());
    formData.append('tags', $('#clipboardTags').val());
    formData.append('detail', $('#clipboardDetail').val());
    formData.append('is_sensitive', $('#clipboardIsSensitive').is(':checked') ? '1' : '0');
    formData.append('is_visible', $('#clipboardIsVisible').is(':checked') ? '1' : '0');

    // 選択された形式に応じてファイルを処理
    const format = $('#clipboardFormat').val();

    // 画像を選択された形式に変換
    convertImageFormat(clipboardImageFile, format).then(function(convertedFile) {
        formData.append('image', convertedFile);

        const $submitBtn = $('#clipboardUploadBtn');
        const originalText = $submitBtn.html();

        // ボタンを無効化
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
        $('#clipboardAlert').addClass('d-none');
        $('#clipboardError').addClass('d-none');

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // 成功メッセージ
                    $('#clipboardAlert').text(response.message || '投稿が作成されました').removeClass('d-none');

                    // フォームとプレビューをリセット
                    $('#clipboardUploadForm')[0].reset();
                    clearClipboardImage();

                    // 投稿一覧を再読み込み
                    loadPosts();

                    // 3秒後にメッセージを消す
                    setTimeout(function() {
                        $('#clipboardAlert').addClass('d-none');
                    }, 3000);

                    // 成功メッセージをトップにも表示
                    $('#uploadAlert').text(response.message || '投稿が作成されました').removeClass('d-none');
                    setTimeout(function() {
                        $('#uploadAlert').addClass('d-none');
                    }, 3000);
                } else {
                    $('#clipboardError').text(response.error || 'アップロードに失敗しました').removeClass('d-none');
                }
            },
            error: function(xhr) {
                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#clipboardError').text(errorMsg).removeClass('d-none');
            },
            complete: function() {
                // ボタンを有効化
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }).catch(function(error) {
        $('#clipboardError').text('画像の変換に失敗しました: ' + error.message).removeClass('d-none');
    });
}

/**
 * 画像を指定された形式に変換
 */
function convertImageFormat(file, targetFormat) {
    return new Promise(function(resolve, reject) {
        const reader = new FileReader();

        reader.onload = function(e) {
            const img = new Image();

            img.onload = function() {
                // Canvasで画像を描画
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);

                // 指定された形式で変換
                let mimeType, quality;
                let extension;

                switch (targetFormat) {
                    case 'png':
                        mimeType = 'image/png';
                        quality = 1.0;
                        extension = 'png';
                        break;
                    case 'jpg':
                    case 'jpeg':
                        mimeType = 'image/jpeg';
                        quality = 0.92;
                        extension = 'jpg';
                        break;
                    case 'webp':
                    default:
                        mimeType = 'image/webp';
                        quality = 0.92;
                        extension = 'webp';
                        break;
                }

                // Canvasから Blob を生成
                canvas.toBlob(function(blob) {
                    if (blob) {
                        const timestamp = Date.now();
                        const filename = `clipboard_${timestamp}.${extension}`;
                        const convertedFile = new File([blob], filename, {
                            type: mimeType,
                            lastModified: Date.now()
                        });
                        resolve(convertedFile);
                    } else {
                        reject(new Error('画像の変換に失敗しました'));
                    }
                }, mimeType, quality);
            };

            img.onerror = function() {
                reject(new Error('画像の読み込みに失敗しました'));
            };

            img.src = e.target.result;
        };

        reader.onerror = function() {
            reject(new Error('ファイルの読み込みに失敗しました'));
        };

        reader.readAsDataURL(file);
    });
}

// ===== グループ投稿機能 =====

/**
 * グループ投稿一覧を読み込み
 */
function loadGroupPosts() {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_posts.php',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                renderGroupPosts(response.posts);
            } else {
                $('#groupPostsList').html('<div class="alert alert-danger">グループ投稿の読み込みに失敗しました</div>');
            }
        },
        error: function() {
            $('#groupPostsList').html('<div class="alert alert-danger">サーバーエラーが発生しました</div>');
        }
    });
}

/**
 * グループ投稿一覧を描画
 */
function renderGroupPosts(posts) {
    const $list = $('#groupPostsList');
    $list.empty();

    if (posts.length === 0) {
        $list.html('<div class="text-center py-5 text-muted">グループ投稿がありません</div>');
        return;
    }

    posts.forEach(function(post) {
        const visibilityBadge = post.is_visible == 1
            ? '<span class="badge bg-success">公開</span>'
            : '<span class="badge bg-secondary">非公開</span>';
        const nsfwBadge = post.is_sensitive == 1
            ? '<span class="badge bg-danger ms-1">NSFW</span>'
            : '';

        const thumbUrl = post.thumb_path ? '/' + post.thumb_path : '/res/images/no-image.svg';

        $list.append(`
            <div class="border-bottom pb-3 mb-3">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <img src="${thumbUrl}" class="img-thumbnail" style="width: 100%; aspect-ratio: 1; object-fit: cover;">
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-1">${escapeHtml(post.title)} ${visibilityBadge}${nsfwBadge}</h5>
                        <p class="text-muted mb-1 small">
                            <i class="bi bi-images me-1"></i>${post.image_count}枚
                            <span class="ms-2"><i class="bi bi-calendar me-1"></i>${post.created_at}</span>
                        </p>
                        ${post.tags ? '<p class="mb-0 small"><i class="bi bi-tags me-1"></i>' + escapeHtml(post.tags) + '</p>' : ''}
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="editGroupPost(${post.id})" title="編集">
                                <i class="bi bi-pencil"></i> 編集
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="addImagesToGroup(${post.id})" title="画像追加">
                                <i class="bi bi-plus-circle"></i> 画像追加
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="shareGroupPostToSNS(${post.id}, '${escapeHtml(post.title).replace(/'/g, "\\'")}', ${post.is_sensitive})" title="SNS共有">
                                <i class="bi bi-share"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteGroupPost(${post.id}, '${escapeHtml(post.title).replace(/'/g, "\\'")}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
}

/**
 * グループ投稿を削除
 */
function deleteGroupPost(id, title) {
    if (!confirm(`グループ投稿「${title}」を削除しますか？\n※内の画像も全て削除されます`)) {
        return;
    }

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_posts.php',
        type: 'DELETE',
        data: {
            csrf_token: $('input[name="csrf_token"]').val(),
            id: id
        },
        success: function(response) {
            if (response.success) {
                alert('グループ投稿を削除しました');
                loadGroupPosts();
            } else {
                alert('削除に失敗しました: ' + response.error);
            }
        },
        error: function() {
            alert('サーバーエラーが発生しました');
        }
    });
}

// グループ画像プレビュー
$('#groupImages').on('change', function(e) {
    const files = e.target.files;
    const $previewList = $('#groupPreviewList');
    $previewList.empty();

    if (files.length > 0) {
        Array.from(files).forEach(function(file, index) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $previewList.append(`
                    <div class="col-4 col-md-3">
                        <div class="position-relative">
                            <img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                            <span class="badge bg-primary position-absolute" style="top: 5px; right: 5px;">${index + 1}</span>
                        </div>
                    </div>
                `);
            };
            reader.readAsDataURL(file);
        });
    }
});

// グループ投稿フォーム送信
$('#groupUploadForm').on('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    // チェックボックスの値を明示的に設定
    formData.set('is_sensitive', $('#groupPostIsSensitive').is(':checked') ? '1' : '0');
    formData.set('is_visible', $('#groupPostIsVisible').is(':checked') ? '1' : '0');

    const $submitBtn = $(this).find('button[type="submit"]');
    const originalText = $submitBtn.html();

    // ボタンを無効化
    $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
    $('#groupUploadAlert').addClass('d-none');
    $('#groupUploadError').addClass('d-none');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_upload.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                $('#groupUploadAlert').text(response.message || 'グループ投稿を作成しました').removeClass('d-none');

                // フォームをリセット
                $('#groupUploadForm')[0].reset();
                $('#groupPreviewList').empty();

                // 一覧を再読み込み
                loadGroupPosts();

                // 5秒後にメッセージを消す
                setTimeout(function() {
                    $('#groupUploadAlert').addClass('d-none');
                }, 5000);
            } else {
                $('#groupUploadError').text(response.error || 'アップロードに失敗しました').removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#groupUploadError').text(errorMsg).removeClass('d-none');
        },
        complete: function() {
            $submitBtn.prop('disabled', false).html(originalText);
        }
    });
});

// タブが切り替わったときにグループ投稿を読み込み
$('#group-posts-tab').on('shown.bs.tab', function() {
    loadGroupPosts();
});

/**
 * グループ投稿を編集
 */
function editGroupPost(groupPostId) {
    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_posts.php?id=' + groupPostId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const post = response.data;

                // 編集モーダルHTMLを生成
                const modalHtml = `
                    <div class="modal fade" id="editGroupModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="bi bi-pencil me-2"></i>グループ投稿を編集
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-success d-none" id="editGroupAlert"></div>
                                    <div class="alert alert-danger d-none" id="editGroupError"></div>

                                    <form id="editGroupForm">
                                        <input type="hidden" id="editGroupPostId" name="id" value="${post.id}">
                                        <input type="hidden" name="csrf_token" value="${$('input[name="csrf_token"]').val()}">

                                        <div class="mb-3">
                                            <label for="editGroupTitle" class="form-label">タイトル <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="editGroupTitle" name="title" value="${escapeHtml(post.title)}" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="editGroupTags" class="form-label">タグ（カンマ区切り）</label>
                                            <input type="text" class="form-control" id="editGroupTags" name="tags" value="${escapeHtml(post.tags || '')}" placeholder="例: イラスト,風景,オリジナル">
                                        </div>

                                        <div class="mb-3">
                                            <label for="editGroupDetail" class="form-label">詳細説明</label>
                                            <textarea class="form-control" id="editGroupDetail" name="detail" rows="3">${escapeHtml(post.detail || '')}</textarea>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="editGroupIsSensitive" name="is_sensitive" value="1" ${post.is_sensitive == 1 ? 'checked' : ''}>
                                                <label class="form-check-label" for="editGroupIsSensitive">
                                                    NSFW（センシティブなコンテンツ）
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="editGroupIsVisible" name="is_visible" value="1" ${post.is_visible == 1 ? 'checked' : ''}>
                                                <label class="form-check-label" for="editGroupIsVisible">
                                                    公開する
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">グループ内の画像（${post.image_count}枚）</label>
                                            <div class="row g-2" id="editGroupImagesList">
                                                ${post.images.map(img => `
                                                    <div class="col-md-3 col-sm-4 col-6" data-image-id="${img.id}">
                                                        <div class="card">
                                                            <img src="/${img.thumb_path}" class="card-img-top" style="aspect-ratio: 1; object-fit: cover;" alt="画像${img.display_order}">
                                                            <div class="card-body p-2">
                                                                <div class="text-center small text-muted mb-2">順序: ${img.display_order}</div>
                                                                <div class="d-grid gap-1">
                                                                    <button type="button" class="btn btn-sm btn-primary" onclick="replaceGroupImage(${img.id}, ${post.id})">
                                                                        <i class="bi bi-arrow-repeat"></i> 差し替え
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteGroupImage(${img.id}, ${post.id})" ${post.image_count <= 1 ? 'disabled' : ''}>
                                                                        <i class="bi bi-trash"></i> 削除
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                                    <button type="button" class="btn btn-primary" id="saveGroupPostBtn">
                                        <i class="bi bi-save me-1"></i>保存
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // 既存のモーダルを削除
                $('#editGroupModal').remove();

                // 新しいモーダルを追加
                $('body').append(modalHtml);
                const editGroupModal = new bootstrap.Modal(document.getElementById('editGroupModal'));
                editGroupModal.show();

                // モーダルが閉じられたらDOMから削除
                $('#editGroupModal').on('hidden.bs.modal', function() {
                    $(this).remove();
                    // バックドロップも確実に削除
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('overflow', '').css('padding-right', '');
                });

                // 保存ボタンのイベント
                $('#saveGroupPostBtn').on('click', function() {
                    saveGroupPost();
                });
            } else {
                alert('グループ投稿の取得に失敗しました');
            }
        },
        error: function() {
            alert('サーバーエラーが発生しました');
        }
    });
}

/**
 * グループ投稿を保存
 */
function saveGroupPost() {
    const formData = $('#editGroupForm').serialize();
    const $saveBtn = $('#saveGroupPostBtn');
    const originalText = $saveBtn.html();

    $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>保存中...');
    $('#editGroupAlert').addClass('d-none');
    $('#editGroupError').addClass('d-none');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_posts.php',
        type: 'POST',
        data: formData + '&_method=PUT',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#editGroupAlert').text(response.message || 'グループ投稿が更新されました').removeClass('d-none');

                // 一覧を再読み込み
                loadGroupPosts();

                // 2秒後にモーダルを閉じる
                setTimeout(function() {
                    $('#editGroupModal').modal('hide');
                }, 1500);
            } else {
                $('#editGroupError').text(response.error || '保存に失敗しました').removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#editGroupError').text(errorMsg).removeClass('d-none');
        },
        complete: function() {
            $saveBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * グループに画像を追加
 */
function addImagesToGroup(groupPostId) {
    // 画像追加モーダルHTMLを生成
    const modalHtml = `
        <div class="modal fade" id="addGroupImagesModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle me-2"></i>グループに画像を追加
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success d-none" id="addGroupImagesAlert"></div>
                        <div class="alert alert-danger d-none" id="addGroupImagesError"></div>

                        <form id="addGroupImagesForm">
                            <input type="hidden" name="group_post_id" value="${groupPostId}">
                            <input type="hidden" name="csrf_token" value="${$('input[name="csrf_token"]').val()}">

                            <div class="mb-3">
                                <label for="addGroupImageFiles" class="form-label">
                                    画像ファイルを選択 <span class="text-danger">*</span>
                                </label>
                                <input type="file" class="form-control" id="addGroupImageFiles" name="images[]" accept="image/*" multiple required>
                                <div class="form-text">複数の画像を一度に選択できます（最大20MB/ファイル）</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">プレビュー</label>
                                <div class="row g-2" id="addGroupImagesPreviewList"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" id="uploadGroupImagesBtn" disabled>
                            <i class="bi bi-upload me-1"></i>アップロード
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // 既存のモーダルを削除
    $('#addGroupImagesModal').remove();

    // 新しいモーダルを追加
    $('body').append(modalHtml);
    const addGroupImagesModal = new bootstrap.Modal(document.getElementById('addGroupImagesModal'));
    addGroupImagesModal.show();

    // モーダルが閉じられたらDOMから削除
    $('#addGroupImagesModal').on('hidden.bs.modal', function() {
        $(this).remove();
        // バックドロップも確実に削除
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('overflow', '').css('padding-right', '');
    });

    // ファイル選択時のプレビュー
    $('#addGroupImageFiles').on('change', function(e) {
        const files = e.target.files;
        const $previewList = $('#addGroupImagesPreviewList');
        $previewList.empty();

        if (files.length > 0) {
            $('#uploadGroupImagesBtn').prop('disabled', false);

            Array.from(files).forEach(function(file, index) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $previewList.append(`
                        <div class="col-4 col-md-3">
                            <div class="position-relative">
                                <img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                                <span class="badge bg-primary position-absolute" style="top: 5px; right: 5px;">${index + 1}</span>
                            </div>
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            });
        } else {
            $('#uploadGroupImagesBtn').prop('disabled', true);
        }
    });

    // アップロードボタンのイベント
    $('#uploadGroupImagesBtn').on('click', function() {
        uploadGroupImages();
    });
}

/**
 * グループに画像をアップロード
 */
function uploadGroupImages() {
    const formData = new FormData($('#addGroupImagesForm')[0]);
    const $uploadBtn = $('#uploadGroupImagesBtn');
    const originalText = $uploadBtn.html();

    $uploadBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>アップロード中...');
    $('#addGroupImagesAlert').addClass('d-none');
    $('#addGroupImagesError').addClass('d-none');

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_upload.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                $('#addGroupImagesAlert').text(response.message || '画像を追加しました').removeClass('d-none');

                // 一覧を再読み込み
                loadGroupPosts();

                // 2秒後にモーダルを閉じる
                setTimeout(function() {
                    $('#addGroupImagesModal').modal('hide');
                }, 1500);
            } else {
                $('#addGroupImagesError').text(response.error || 'アップロードに失敗しました').removeClass('d-none');
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            $('#addGroupImagesError').text(errorMsg).removeClass('d-none');
        },
        complete: function() {
            $uploadBtn.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * グループ投稿をSNSで共有
 */
function shareGroupPostToSNS(groupPostId, title, isSensitive) {
    // 詳細ページのURLを構築
    const protocol = window.location.protocol;
    const host = window.location.host;
    const detailUrl = `${protocol}//${host}/group_detail.php?id=${groupPostId}`;

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
        <div class="modal fade" id="shareGroupModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-share me-2"></i>SNSで共有（グループ投稿）
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3"><strong>${escapeHtml(title)}</strong></p>
                        <p class="text-muted small mb-3">
                            共有URL: <a href="${detailUrl}" target="_blank">${detailUrl}</a>
                        </p>
                        ${isSensitive ? '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>この投稿はNSFWです。</div>' : ''}

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
                                <input type="text" class="form-control" id="shareGroupUrlInput" value="${detailUrl}" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyShareGroupUrl()">
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
    $('#shareGroupModal').remove();

    // 新しいモーダルを追加して表示
    $('body').append(modalHtml);
    const shareGroupModal = new bootstrap.Modal(document.getElementById('shareGroupModal'));
    shareGroupModal.show();

    // モーダルが閉じられたらDOMから削除
    $('#shareGroupModal').on('hidden.bs.modal', function() {
        $(this).remove();
        // バックドロップも確実に削除
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('overflow', '').css('padding-right', '');
    });
}

/**
 * グループ投稿の共有URLをクリップボードにコピー
 */
function copyShareGroupUrl() {
    const input = document.getElementById('shareGroupUrlInput');
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

/**
 * グループ画像を差し替え
 */
function replaceGroupImage(imageId, groupPostId) {
    // ファイル選択ダイアログを作成
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';

    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // 確認ダイアログ
        if (!confirm('この画像を差し替えますか？')) {
            return;
        }

        const formData = new FormData();
        formData.append('image', file);
        formData.append('image_id', imageId);
        formData.append('csrf_token', $('input[name="csrf_token"]').val());

        // 画像要素を取得してローディング表示
        const $imageCard = $(`[data-image-id="${imageId}"]`);
        const $img = $imageCard.find('img');
        const originalSrc = $img.attr('src');

        // ボタンを無効化
        $imageCard.find('button').prop('disabled', true);

        $.ajax({
            url: '/' + ADMIN_PATH + '/api/group_image_replace.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // 画像を更新（キャッシュバスター付き）
                    const timestamp = new Date().getTime();
                    $img.attr('src', '/' + response.thumb_path + '?' + timestamp);

                    // 成功メッセージ
                    $('#editGroupAlert')
                        .text(response.message || '画像を差し替えました')
                        .removeClass('d-none');

                    setTimeout(function() {
                        $('#editGroupAlert').addClass('d-none');
                    }, 3000);

                    // グループ投稿一覧を再読み込み（バックグラウンドで）
                    loadGroupPosts();
                } else {
                    const errorMsg = response.error || '不明なエラー';
                    const debugInfo = response.debug ? '\n\nデバッグ情報:\n' + response.debug : '';
                    alert('差し替えに失敗しました: ' + errorMsg + debugInfo);
                    console.error('Replace error:', response);
                    $img.attr('src', originalSrc);
                }
            },
            error: function(xhr, status, error) {
                console.error('XHR Error:', xhr);
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);

                let errorMsg = 'サーバーエラーが発生しました';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                } else if (xhr.responseText) {
                    errorMsg = xhr.responseText.substring(0, 200);
                }
                alert('差し替えに失敗しました: ' + errorMsg);
                $img.attr('src', originalSrc);
            },
            complete: function() {
                // ボタンを有効化
                $imageCard.find('button').prop('disabled', false);
            }
        });
    };

    // ファイル選択ダイアログを開く
    input.click();
}

/**
 * グループ画像を削除
 */
function deleteGroupImage(imageId, groupPostId) {
    if (!confirm('この画像を削除しますか？\nこの操作は取り消せません。')) {
        return;
    }

    const $imageCard = $(`[data-image-id="${imageId}"]`);

    // ボタンを無効化
    $imageCard.find('button').prop('disabled', true);

    $.ajax({
        url: '/' + ADMIN_PATH + '/api/group_image_replace.php',
        type: 'POST',
        data: {
            _method: 'DELETE',
            image_id: imageId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 画像カードをフェードアウトして削除
                $imageCard.fadeOut(300, function() {
                    $(this).remove();

                    // 残りの画像数を更新
                    const remainingImages = $('#editGroupImagesList [data-image-id]').length;
                    const $label = $('#editGroupImagesList').prev('label');
                    $label.text('グループ内の画像（' + remainingImages + '枚）');

                    // 残りが1枚になった場合、すべての削除ボタンを無効化
                    if (remainingImages <= 1) {
                        $('#editGroupImagesList').find('.btn-danger').prop('disabled', true);
                    }
                });

                // 成功メッセージ
                $('#editGroupAlert')
                    .text(response.message || '画像を削除しました')
                    .removeClass('d-none');

                setTimeout(function() {
                    $('#editGroupAlert').addClass('d-none');
                }, 3000);

                // グループ投稿一覧を再読み込み（バックグラウンドで）
                loadGroupPosts();
            } else {
                const errorMsg = response.error || '不明なエラー';
                const debugInfo = response.debug ? '\n\nデバッグ情報:\n' + response.debug : '';
                alert('削除に失敗しました: ' + errorMsg + debugInfo);
                console.error('Delete error:', response);
                $imageCard.find('button').prop('disabled', false);
            }
        },
        error: function(xhr) {
            let errorMsg = 'サーバーエラーが発生しました';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            alert('削除に失敗しました: ' + errorMsg);
            $imageCard.find('button').prop('disabled', false);
        }
    });
}
