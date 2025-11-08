/**
 * Modals Module
 * Handles all modal dialogs (Open, Save, Resize, Color Edit, etc.)
 */

import { state, elements } from './state.js';
import { ColorUtils } from './tools.js';

// Selected illustration ID for open modal
let selectedIllustId = null;

/**
 * Initialize all modal event listeners
 */
export function initModals(openOpenModal, saveIllust, resizeCanvas, setColor) {
    initOpenModal(openOpenModal);
    initSaveModal(saveIllust);
    initResizeModal(resizeCanvas);
    initEditColorModal(setColor);
}

/**
 * Initialize Open modal
 */
function initOpenModal(openOpenModal) {
    if (elements.btnOpen) {
        elements.btnOpen.addEventListener('click', openOpenModal);
    }
    if (elements.openModalClose) {
        elements.openModalClose.addEventListener('click', closeOpenModal);
    }
    if (elements.openModalCancel) {
        elements.openModalCancel.addEventListener('click', closeOpenModal);
    }
    if (elements.openModalLoad) {
        elements.openModalLoad.addEventListener('click', () => {
            // Will be handled by caller
        });
    }

    if (elements.openModalOverlay) {
        elements.openModalOverlay.addEventListener('click', (e) => {
            if (e.target === elements.openModalOverlay) {
                closeOpenModal();
            }
        });
    }
}

/**
 * Open illustration selection modal
 */
export async function openOpenModal(setStatus) {
    elements.openModalOverlay.classList.add('active');
    selectedIllustId = null;
    elements.openModalLoad.disabled = true;

    if (setStatus) {
        setStatus('イラスト一覧を読み込んでいます...');
    }

    try {
        const resp = await fetch('api/list.php', {
            credentials: 'same-origin'
        });

        const json = await resp.json();

        if (json.success && json.data && json.data.length > 0) {
            renderIllustGrid(json.data);
            elements.illustGrid.classList.remove('hidden');
            elements.openModalEmpty.classList.add('hidden');
        } else {
            elements.illustGrid.classList.add('hidden');
            elements.openModalEmpty.classList.remove('hidden');
        }

        if (setStatus) {
            setStatus('');
        }
    } catch (e) {
        console.error('Failed to load illustration list:', e);
        if (setStatus) {
            setStatus('イラスト一覧の読み込みに失敗しました');
        }
        elements.illustGrid.classList.add('hidden');
        elements.openModalEmpty.classList.remove('hidden');
    }
}

/**
 * Render illustration grid
 */
function renderIllustGrid(paint) {
    elements.illustGrid.innerHTML = '';

    paint.forEach(illust => {
        const item = document.createElement('div');
        item.className = 'illust-item';
        item.dataset.id = illust.id;

        // Thumbnail
        const thumbnailWrap = document.createElement('div');
        thumbnailWrap.style.position = 'relative';
        thumbnailWrap.style.width = '100%';
        thumbnailWrap.style.aspectRatio = '1';
        thumbnailWrap.style.overflow = 'hidden';
        thumbnailWrap.style.borderRadius = '4px';
        thumbnailWrap.style.background = '#F5F5F5';

        const showPlaceholder = () => {
            const placeholder = document.createElement('div');
            placeholder.style.width = '100%';
            placeholder.style.height = '100%';
            placeholder.style.display = 'flex';
            placeholder.style.alignItems = 'center';
            placeholder.style.justifyContent = 'center';
            placeholder.style.fontSize = '48px';
            placeholder.textContent = '🎨';
            thumbnailWrap.appendChild(placeholder);
        };

        if (illust.thumbnail_path || illust.image_path) {
            const thumbnail = document.createElement('img');
            thumbnail.className = 'illust-thumbnail';
            thumbnail.alt = illust.title || 'Untitled';
            thumbnail.style.width = '100%';
            thumbnail.style.height = '100%';
            thumbnail.style.objectFit = 'cover';

            let triedThumbnail = false;
            let triedImage = false;

            const tryLoad = () => {
                if (illust.thumbnail_path && !triedThumbnail) {
                    triedThumbnail = true;
                    thumbnail.src = illust.thumbnail_path;
                } else if (illust.image_path && !triedImage) {
                    triedImage = true;
                    thumbnail.src = illust.image_path;
                } else {
                    thumbnail.style.display = 'none';
                    showPlaceholder();
                }
            };

            thumbnail.onerror = () => {
                tryLoad();
            };

            thumbnailWrap.appendChild(thumbnail);
            tryLoad();
        } else {
            showPlaceholder();
        }

        const info = document.createElement('div');
        info.className = 'illust-info';

        const titleEl = document.createElement('div');
        titleEl.className = 'illust-title';
        titleEl.textContent = illust.title || '無題';

        const idEl = document.createElement('div');
        idEl.className = 'illust-id';
        idEl.textContent = `ID: ${illust.id}`;

        const dateEl = document.createElement('div');
        dateEl.className = 'illust-date';
        const date = new Date(illust.updated_at || illust.created_at);
        dateEl.textContent = date.toLocaleDateString('ja-JP');

        info.appendChild(titleEl);
        info.appendChild(idEl);
        info.appendChild(dateEl);

        // Add delete button
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'illust-delete-btn';
        deleteBtn.textContent = '🗑️';
        deleteBtn.title = '削除';
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            deleteIllustration(illust.id, illust.title || '無題');
        });

        item.appendChild(thumbnailWrap);
        item.appendChild(info);
        item.appendChild(deleteBtn);

        // Single click to select
        item.addEventListener('click', () => {
            selectIllust(illust.id);
        });

        // Double click to open immediately
        item.addEventListener('dblclick', () => {
            selectIllust(illust.id);
            // Trigger load
        });

        elements.illustGrid.appendChild(item);
    });
}

/**
 * Select illustration in grid
 */
function selectIllust(id) {
    selectedIllustId = id;

    elements.illustGrid.querySelectorAll('.illust-item').forEach(item => {
        item.classList.toggle('selected', item.dataset.id == id);
    });

    elements.openModalLoad.disabled = false;
}

/**
 * Close open modal
 */
export function closeOpenModal() {
    elements.openModalOverlay.classList.remove('active');
    selectedIllustId = null;
}

/**
 * Get selected illustration ID
 */
export function getSelectedIllustId() {
    return selectedIllustId;
}

/**
 * Initialize Save modal
 */
function initSaveModal(saveIllust) {
    if (!elements.saveModalOverlay) return;

    if (elements.saveModalCancel) {
        elements.saveModalCancel.addEventListener('click', closeSaveModal);
    }

    if (elements.saveModalSave) {
        elements.saveModalSave.addEventListener('click', () => {
            const title = elements.saveTitle.value.trim();
            const description = elements.saveDescription.value.trim();
            const tags = elements.saveTags.value.trim();

            if (!title) {
                alert('タイトルを入力してください');
                return;
            }

            closeSaveModal();
            if (saveIllust) {
                saveIllust(title, description, tags);
            }
        });
    }

    elements.saveModalOverlay.addEventListener('click', (e) => {
        if (e.target === elements.saveModalOverlay) {
            closeSaveModal();
        }
    });
}

/**
 * Open save modal
 */
export function openSaveModal() {
    elements.saveModalOverlay.classList.add('active');

    // Pre-fill with current values
    elements.saveTitle.value = state.currentIllustTitle || '';
    elements.saveDescription.value = state.currentIllustDescription || '';
    elements.saveTags.value = state.currentIllustTags || '';
    elements.saveTitle.focus();
}

/**
 * Close save modal
 */
function closeSaveModal() {
    elements.saveModalOverlay.classList.remove('active');
}

/**
 * Initialize Resize modal
 */
function initResizeModal(resizeCanvas) {
    if (!elements.resizeModalOverlay) return;

    if (elements.resizeModalClose) {
        elements.resizeModalClose.addEventListener('click', closeResizeModal);
    }

    if (elements.resizeModalCancel) {
        elements.resizeModalCancel.addEventListener('click', closeResizeModal);
    }

    // Keep aspect ratio checkbox
    if (elements.resizeKeepRatio) {
        elements.resizeKeepRatio.addEventListener('change', () => {
            if (elements.resizeKeepRatio.checked) {
                // Lock aspect ratio when width changes
                if (elements.resizeWidth) {
                    elements.resizeWidth.dataset.aspectRatio = (state.layers[0].height / state.layers[0].width).toString();
                }
            }
        });
    }

    // Width input with aspect ratio lock
    if (elements.resizeWidth) {
        elements.resizeWidth.addEventListener('input', (e) => {
            if (elements.resizeKeepRatio && elements.resizeKeepRatio.checked) {
                const aspectRatio = parseFloat(e.target.dataset.aspectRatio) || 1;
                const newWidth = parseInt(e.target.value) || 512;
                elements.resizeHeight.value = Math.round(newWidth * aspectRatio);
            }
        });
    }

    // Height input with aspect ratio lock
    if (elements.resizeHeight) {
        elements.resizeHeight.addEventListener('input', (e) => {
            if (elements.resizeKeepRatio && elements.resizeKeepRatio.checked) {
                const aspectRatio = parseFloat(elements.resizeWidth.dataset.aspectRatio) || 1;
                const newHeight = parseInt(e.target.value) || 512;
                elements.resizeWidth.value = Math.round(newHeight / aspectRatio);
            }
        });
    }

    // Preset buttons
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const width = parseInt(btn.dataset.width);
            const height = parseInt(btn.dataset.height);
            elements.resizeWidth.value = width;
            elements.resizeHeight.value = height;
        });
    });

    // Apply resize
    if (elements.resizeModalApply) {
        elements.resizeModalApply.addEventListener('click', () => {
            const newWidth = parseInt(elements.resizeWidth.value) || 512;
            const newHeight = parseInt(elements.resizeHeight.value) || 512;

            if (newWidth < 64 || newWidth > 2048 || newHeight < 64 || newHeight > 2048) {
                alert('サイズは64～2048pxの範囲で指定してください');
                return;
            }

            if (resizeCanvas) {
                resizeCanvas(newWidth, newHeight);
            }
            closeResizeModal();
        });
    }

    elements.resizeModalOverlay.addEventListener('click', (e) => {
        if (e.target === elements.resizeModalOverlay) {
            closeResizeModal();
        }
    });
}

/**
 * Open resize modal
 */
export function openResizeModal() {
    elements.resizeModalOverlay.classList.add('active');
    elements.resizeWidth.value = state.layers[0].width;
    elements.resizeHeight.value = state.layers[0].height;
    elements.resizeKeepRatio.checked = true;
    elements.resizeWidth.dataset.aspectRatio = (state.layers[0].height / state.layers[0].width).toString();
}

/**
 * Close resize modal
 */
function closeResizeModal() {
    elements.resizeModalOverlay.classList.remove('active');
}

/**
 * Initialize Edit Color modal
 */
function initEditColorModal(setColor) {
    if (!elements.editColorModalOverlay) return;

    if (elements.editColorModalClose) {
        elements.editColorModalClose.addEventListener('click', closeEditColorModal);
    }

    if (elements.editColorModalCancel) {
        elements.editColorModalCancel.addEventListener('click', closeEditColorModal);
    }

    // Color mode tabs
    if (elements.colorModeTabs) {
        elements.colorModeTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const mode = tab.dataset.mode;
                elements.colorModeTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                if (mode === 'hsv') {
                    elements.hsvSliders.style.display = 'block';
                    elements.rgbSliders.style.display = 'none';
                } else {
                    elements.hsvSliders.style.display = 'none';
                    elements.rgbSliders.style.display = 'block';
                }
            });
        });
    }

    // RGB sliders
    const updateFromRgb = () => {
        const r = parseInt(elements.editRgbR.value);
        const g = parseInt(elements.editRgbG.value);
        const b = parseInt(elements.editRgbB.value);

        const hex = ColorUtils.rgbToHex(r, g, b);
        elements.editColorInput.value = hex;
        elements.editColorPreview.style.background = hex;

        const hsv = ColorUtils.rgbToHsv(r, g, b);
        elements.editHsvH.value = hsv.h;
        elements.editHsvS.value = hsv.s;
        elements.editHsvV.value = hsv.v;
        elements.editHsvHValue.textContent = hsv.h + '°';
        elements.editHsvSValue.textContent = hsv.s + '%';
        elements.editHsvVValue.textContent = hsv.v + '%';
    };

    if (elements.editRgbR) {
        elements.editRgbR.addEventListener('input', (e) => {
            elements.editRgbRValue.textContent = e.target.value;
            updateFromRgb();
        });
    }
    if (elements.editRgbG) {
        elements.editRgbG.addEventListener('input', (e) => {
            elements.editRgbGValue.textContent = e.target.value;
            updateFromRgb();
        });
    }
    if (elements.editRgbB) {
        elements.editRgbB.addEventListener('input', (e) => {
            elements.editRgbBValue.textContent = e.target.value;
            updateFromRgb();
        });
    }

    // HSV sliders
    const updateFromHsv = () => {
        const h = parseInt(elements.editHsvH.value);
        const s = parseInt(elements.editHsvS.value);
        const v = parseInt(elements.editHsvV.value);

        const rgb = ColorUtils.hsvToRgb(h, s, v);
        const hex = ColorUtils.rgbToHex(rgb.r, rgb.g, rgb.b);

        elements.editColorInput.value = hex;
        elements.editColorPreview.style.background = hex;

        elements.editRgbR.value = rgb.r;
        elements.editRgbG.value = rgb.g;
        elements.editRgbB.value = rgb.b;
        elements.editRgbRValue.textContent = rgb.r;
        elements.editRgbGValue.textContent = rgb.g;
        elements.editRgbBValue.textContent = rgb.b;
    };

    if (elements.editHsvH) {
        elements.editHsvH.addEventListener('input', (e) => {
            elements.editHsvHValue.textContent = e.target.value + '°';
            updateFromHsv();
        });
    }
    if (elements.editHsvS) {
        elements.editHsvS.addEventListener('input', (e) => {
            elements.editHsvSValue.textContent = e.target.value + '%';
            updateFromHsv();
        });
    }
    if (elements.editHsvV) {
        elements.editHsvV.addEventListener('input', (e) => {
            elements.editHsvVValue.textContent = e.target.value + '%';
            updateFromHsv();
        });
    }

    // Hex input
    if (elements.editColorInput) {
        elements.editColorInput.addEventListener('input', (e) => {
            const hex = e.target.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
                elements.editColorPreview.style.background = hex;
                const rgb = ColorUtils.hexToRgb(hex);
                if (rgb) {
                    elements.editRgbR.value = rgb.r;
                    elements.editRgbG.value = rgb.g;
                    elements.editRgbB.value = rgb.b;
                    elements.editRgbRValue.textContent = rgb.r;
                    elements.editRgbGValue.textContent = rgb.g;
                    elements.editRgbBValue.textContent = rgb.b;

                    const hsv = ColorUtils.rgbToHsv(rgb.r, rgb.g, rgb.b);
                    elements.editHsvH.value = hsv.h;
                    elements.editHsvS.value = hsv.s;
                    elements.editHsvV.value = hsv.v;
                    elements.editHsvHValue.textContent = hsv.h + '°';
                    elements.editHsvSValue.textContent = hsv.s + '%';
                    elements.editHsvVValue.textContent = hsv.v + '%';
                }
            }
        });
    }

    // Save button
    if (elements.editColorModalSave) {
        elements.editColorModalSave.addEventListener('click', () => {
            const hex = elements.editColorInput.value;
            if (setColor) {
                setColor(hex);
            }
            closeEditColorModal();
        });
    }

    elements.editColorModalOverlay.addEventListener('click', (e) => {
        if (e.target === elements.editColorModalOverlay) {
            closeEditColorModal();
        }
    });
}

/**
 * Close edit color modal
 */
function closeEditColorModal() {
    elements.editColorModalOverlay.classList.remove('active');
}

/**
 * Delete illustration
 */
async function deleteIllustration(id, title) {
    if (!confirm(`「${title}」を削除してもよろしいですか？\nこの操作は取り消せません。`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', id);

        const resp = await fetch('api/delete.php', {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': window.CSRF_TOKEN
            },
            body: formData
        });

        const json = await resp.json();

        if (json.success) {
            // Refresh the illustration list
            await openOpenModal();
            alert('削除しました');
        } else {
            alert('削除に失敗しました: ' + (json.error || 'Unknown error'));
        }
    } catch (e) {
        console.error('Failed to delete illustration:', e);
        alert('削除中にエラーが発生しました');
    }
}
