/**
 * Storage Module
 * Handles saving and loading illustration data to/from server and localStorage
 */

import { state, elements } from './state.js';

/**
 * Convert Canvas 2D API composite operation to CSS mix-blend-mode
 */
function canvasBlendToCSSBlend(canvasBlend) {
    const map = {
        'source-over': 'normal',
        'multiply': 'multiply',
        'screen': 'screen',
        'overlay': 'overlay',
        'lighter': 'screen', // 加算合成：CSSには完全一致なし、screenが近い
        'lighten': 'lighten',
        'darken': 'darken',
        'color-dodge': 'color-dodge',
        'color-burn': 'color-burn',
        'hard-light': 'hard-light',
        'soft-light': 'soft-light',
        'difference': 'difference',
        'exclusion': 'exclusion',
        'hue': 'hue',
        'saturation': 'saturation',
        'color': 'color',
        'luminosity': 'luminosity'
    };
    return map[canvasBlend] || 'normal';
}

/**
 * Load persisted state from localStorage
 * @param {Function} restoreCanvasState - Callback to restore canvas state
 * @param {Function} setStatus - Callback to update status bar
 */
export async function loadPersistedState(restoreCanvasState, setStatus) {
    try {
        // Completely remove persisted local-session keys to keep the editor clean
        // on reload. This removes the apparent "ID stays after reload" behavior.
        try {
            localStorage.removeItem('paint_current_illust_id');
        } catch (e) {
            console.warn('Failed to remove paint_current_illust_id from localStorage:', e);
        }
        try {
            localStorage.removeItem('paint_canvas_state');
        } catch (e) {
            console.warn('Failed to remove paint_canvas_state from localStorage:', e);
        }
        // Remove any backup keys created previously (paint_canvas_state_backup_<ts>)
        try {
            const keysToRemove = [];
            for (let i = 0; i < localStorage.length; i++) {
                const k = localStorage.key(i);
                if (k && k.indexOf('paint_canvas_state_backup_') === 0) {
                    keysToRemove.push(k);
                }
            }
            keysToRemove.forEach(k => {
                try { localStorage.removeItem(k); } catch (e) { /* ignore */ }
            });
        } catch (e) {
            console.warn('Failed to sweep backup localStorage keys:', e);
        }

        // After cleaning, indicate there is no persisted state
        return false;
    } catch (e) {
        console.error('✗ Failed to load persisted state:', e);
        console.error('Error details:', e.message, e.stack);
    }
}

/**
 * Save current canvas state to localStorage
 */
export function savePersistedState() {
    // Don't save during initialization
    if (state.isInitializing) {
        return;
    }

    // If local auto-save is disabled, only persist minimal metadata (or skip)
    if (state.localAutoSave === false) {
        try {
            const minimal = {
                canvasWidth: state.layers && state.layers[0] ? state.layers[0].width : null,
                canvasHeight: state.layers && state.layers[0] ? state.layers[0].height : null,
                currentIllustTitle: state.currentIllustTitle || '',
                currentIllustDescription: state.currentIllustDescription || '',
                currentIllustTags: state.currentIllustTags || '',
                hasUnsavedChanges: !!state.hasUnsavedChanges,
                timestamp: Date.now(),
                minimalSave: true
            };
            localStorage.setItem('paint_canvas_state', JSON.stringify(minimal));
        } catch (e) {
            console.warn('Minimal local save failed:', e);
        }
        return;
    }

    try {
        const canvasState = captureCanvasState();
        let stateJson = JSON.stringify(canvasState);

        // Estimate size and attempt graceful degradation when large
        const estimateByteSize = (str) => {
            if (typeof TextEncoder !== 'undefined') {
                return new TextEncoder().encode(str).length;
            }
            // Fallback approximation: assume 1 char = 1 byte for ASCII-heavy data
            return unescape(encodeURIComponent(str)).length;
        };

        const LIMIT_BYTES = 2.5 * 1024 * 1024; // 2.5 MB heuristic
        const originalSize = estimateByteSize(stateJson);

        if (originalSize > LIMIT_BYTES) {
            console.warn('Persisted state is large:', originalSize, 'bytes. Attempting to prune before saving.');

            // Create a pruned copy: remove timelapse data and layer images to reduce size
            const pruned = Object.assign({}, canvasState);
            pruned.timelapseEvents = [];
            pruned.timelapseSnapshots = [];
            pruned.layers = (pruned.layers || []).map(l => ({ index: l.index, visible: l.visible, opacity: l.opacity, dataUrl: null }));
            pruned.prunedForLocalStorage = true;

            stateJson = JSON.stringify(pruned);
            const prunedSize = estimateByteSize(stateJson);

            if (prunedSize > LIMIT_BYTES) {
                console.warn('Pruned state still large:', prunedSize, 'bytes. Falling back to minimal metadata.');
                // Last-resort: save only minimal metadata so the UI can at least restore a title/id
                const minimal = {
                    canvasWidth: pruned.canvasWidth,
                    canvasHeight: pruned.canvasHeight,
                    currentIllustTitle: pruned.currentIllustTitle || '',
                    currentIllustDescription: pruned.currentIllustDescription || '',
                    currentIllustTags: pruned.currentIllustTags || '',
                    hasUnsavedChanges: !!pruned.hasUnsavedChanges,
                    timestamp: Date.now(),
                    prunedForLocalStorage: true
                };
                stateJson = JSON.stringify(minimal);
            }

            try {
                localStorage.setItem('paint_canvas_state', stateJson);
                if (elements && elements.statusText) {
                    elements.statusText.textContent = 'ローカル保存: ストレージが大きいため一部データを省略して保存しました';
                }
                return;
            } catch (e) {
                // fall through to outer catch to handle quota
                throw e;
            }
        }

        // Normal save when size is within limits
        localStorage.setItem('paint_canvas_state', stateJson);
    } catch (e) {
        console.error('Failed to save persisted state:', e);
        // QuotaExceededError handling and fallback to minimal metadata
        if (e && (e.name === 'QuotaExceededError' || e.code === 22 || e.number === -2147024882)) {
            console.error('LocalStorage quota exceeded! Attempting minimal save.');
            try {
                const minimal = {
                    canvasWidth: state.layers && state.layers[0] ? state.layers[0].width : null,
                    canvasHeight: state.layers && state.layers[0] ? state.layers[0].height : null,
                    currentIllustTitle: state.currentIllustTitle || '',
                    currentIllustDescription: state.currentIllustDescription || '',
                    currentIllustTags: state.currentIllustTags || '',
                    hasUnsavedChanges: !!state.hasUnsavedChanges,
                    timestamp: Date.now(),
                    prunedForLocalStorage: true
                };
                localStorage.setItem('paint_canvas_state', JSON.stringify(minimal));
                if (elements && elements.statusText) {
                    elements.statusText.textContent = 'ローカル保存: 容量不足のため最小データのみ保存されました';
                } else {
                    alert('警告: キャンバス状態が大きすぎて保存できませんでした。最小データのみ保存しています。');
                }
            } catch (e2) {
                console.error('Even minimal save failed:', e2);
                try {
                    alert('警告: ローカルストレージに保存できませんでした。ブラウザの設定かストレージが不足しています。');
                } catch (ignored) {}
            }
        } else {
            // Generic fallback alert for other errors
            try {
                alert('警告: ローカルストレージへの保存中にエラーが発生しました。');
            } catch (ignored) {}
        }
    }
}

/**
 * Set current illustration ID
 */
export function setCurrentId(id) {
    state.currentIllustId = id;
    try {
        localStorage.setItem('paint_current_illust_id', id);
    } catch (e) {
        console.error('Failed to persist ID:', e);
    }
    if (elements.illustId) {
        elements.illustId.textContent = id;
    }
    // Show or hide quick-save button depending on whether the record has an ID
    try {
        if (elements.btnSave) {
            if (id) {
                elements.btnSave.style.display = 'inline-block';
            } else {
                elements.btnSave.style.display = 'none';
            }
        }
    } catch (e) {
        // ignore DOM errors
    }
}

/**
 * Capture current canvas state
 */
export function captureCanvasState() {
    const layerData = state.layers.map((canvas, index) => ({
        index: index,
        visible: canvas.style.display !== 'none',
        opacity: parseFloat(canvas.style.opacity || '1'),
        blendMode: canvas.dataset && canvas.dataset.blendMode ? canvas.dataset.blendMode : 'source-over',
        dataUrl: canvas.toDataURL('image/png')
    }));

    return {
        canvasWidth: state.layers[0].width,
        canvasHeight: state.layers[0].height,
        layers: layerData,
        activeLayer: state.activeLayer,
        currentColor: state.currentColor,
        penSize: state.penSize,
        eraserSize: state.eraserSize,
        zoomLevel: state.zoomLevel,
        panOffset: state.panOffset,
        layerNames: state.layerNames,
        timelapseEvents: state.timelapseEvents,
        timelapseSnapshots: state.timelapseSnapshots,
        lastSnapshotTime: state.lastSnapshotTime,
        // persist lightweight metadata so UI can restore title/description/tags
        currentIllustTitle: state.currentIllustTitle || '',
        currentIllustDescription: state.currentIllustDescription || '',
        currentIllustTags: state.currentIllustTags || '',
        hasUnsavedChanges: !!state.hasUnsavedChanges,
        timestamp: Date.now()
    };
}

/**
 * Save illustration to server
 */
export async function saveIllust(title, description = '', tags = '', setStatus, setCurrentId, updateIllustDisplay, options = {}) {
    if (setStatus) {
        setStatus('保存中...');
    }

    try {
        const compositeImage = createCompositeImage();
        const illustData = buildIllustData();
        const timelapseData = await compressTimelapseData();

            await sendSaveRequest(title, description, tags, compositeImage, illustData, timelapseData, setStatus, setCurrentId, updateIllustDisplay, options);
    } catch (error) {
        if (setStatus) {
            setStatus('保存エラー: ' + error.message);
        }
        console.error('Save error:', error);
    }
}

/**
 * Create composite image from all visible layers
 */
function createCompositeImage() {
    const w = state.layers[0].width;
    const h = state.layers[0].height;

    const offscreen = document.createElement('canvas');
    offscreen.width = w;
    offscreen.height = h;
    const octx = offscreen.getContext('2d');

    // White background
    octx.fillStyle = '#FFFFFF';
    octx.fillRect(0, 0, w, h);

    // Draw all visible layers with blend mode applied
    state.layers.forEach(canvas => {
        if (canvas.style.display !== 'none') {
            octx.globalAlpha = parseFloat(canvas.style.opacity || '1');

            // Apply blend mode if set
            const blendMode = canvas.dataset && canvas.dataset.blendMode ? canvas.dataset.blendMode : 'source-over';
            octx.globalCompositeOperation = blendMode;

            octx.drawImage(canvas, 0, 0);

            // Reset to defaults for next layer
            octx.globalCompositeOperation = 'source-over';
        }
    });

    return offscreen.toDataURL('image/png');
}

/**
 * Build illustration data for saving
 */
function buildIllustData() {
    const w = state.layers[0].width;
    const h = state.layers[0].height;

    return {
        version: '1.0',
        metadata: {
            canvas_width: w,
            canvas_height: h,
            background_color: '#FFFFFF'
        },
        layers: state.layers.map((canvas, idx) => ({
            id: `layer_${idx}`,
            name: state.layerNames[idx] || `Layer ${idx}`,
            order: idx,
            visible: canvas.style.display !== 'none',
            opacity: parseFloat(canvas.style.opacity || '1'),
            blendMode: canvas.dataset && canvas.dataset.blendMode ? canvas.dataset.blendMode : 'source-over',
            type: 'raster',
            data: canvas.toDataURL('image/png'),
            width: w,
            height: h
        })),
        timelapse: {
            enabled: state.timelapseEvents.length > 0
        }
    };
}

/**
 * Compress timelapse data using pako
 */
async function compressTimelapseData() {
    if (state.timelapseEvents.length === 0) {
        return null;
    }

    try {
        // Add canvas metadata as the first event
        const canvasWidth = state.layers[0].width;
        const canvasHeight = state.layers[0].height;
        const eventsWithMeta = [
            {
                type: 'meta',
                canvas_width: canvasWidth,
                canvas_height: canvasHeight,
                t: state.timelapseEvents.length > 0 ? state.timelapseEvents[0].t : Date.now()
            },
            ...state.timelapseEvents
        ];

        // Debug: report events count and last sequence id when available
        try {
            const last = state.timelapseEvents.length > 0 ? state.timelapseEvents[state.timelapseEvents.length - 1] : null;
            const lastSeq = last && typeof last._seq !== 'undefined' ? last._seq : null;
            console.debug('[timelapse] compressTimelapseData: eventsWithMeta=', eventsWithMeta.length, 'last_seq=', lastSeq);
        } catch (e) {
            // ignore
        }

        // If snapshots exist, produce a JSON package including events and snapshots so playback
        // can render snapshots (which capture composite including layer order changes).
        if (state.timelapseSnapshots && state.timelapseSnapshots.length > 0) {
            const packageObj = {
                version: '1.0',
                canvasWidth: canvasWidth,
                canvasHeight: canvasHeight,
                events: state.timelapseEvents,
                snapshots: state.timelapseSnapshots
            };
            const json = JSON.stringify(packageObj);
            if (typeof pako !== 'undefined') {
                const gz = pako.gzip(json);
                let bin = '';
                for (let i = 0; i < gz.length; i++) bin += String.fromCharCode(gz[i]);
                return 'data:application/octet-stream;base64,' + btoa(bin);
            } else {
                const blob = new Blob([json], { type: 'application/json' });
                const reader = new FileReader();
                // synchronous fallback not available; return null to skip including timelapse
                return null;
            }
        }

        // Otherwise, fallback to existing CSV compression (worker or pako)
        if (window.Worker && !state.timelapseSnapshots) {
            // Use Web Worker for compression
            if (!window._timelapseWorker) {
                // Resolve worker path using injected base URL when available
                let workerPath = 'js/timelapse_worker.js';
                try {
                    if (typeof window !== 'undefined' && window.PAINT_BASE_URL) {
                        // Ensure no double-slash
                        const base = String(window.PAINT_BASE_URL).replace(/\/$/, '');
                        workerPath = base + '/js/timelapse_worker.js';
                    }
                } catch (e) {
                    // fall back to relative path
                }
                window._timelapseWorker = new Worker(workerPath);
            }

            return await new Promise((resolve, reject) => {
                const worker = window._timelapseWorker;
                const onmsg = (ev) => {
                    worker.removeEventListener('message', onmsg);
                    if (ev.data && ev.data.success) {
                        resolve(ev.data.payload);
                    } else {
                        reject(ev.data && ev.data.error ? ev.data.error : 'worker error');
                    }
                };
                worker.addEventListener('message', onmsg);
                worker.postMessage({ events: eventsWithMeta });
            });
        } else if (typeof pako !== 'undefined') {
            // Fallback to main-thread compression (CSV)
            const headers = [];
            eventsWithMeta.forEach(ev => {
                Object.keys(ev).forEach(k => {
                    if (headers.indexOf(k) === -1) headers.push(k);
                });
            });
            const lines = [headers.join(',')];
            eventsWithMeta.forEach(ev => {
                const row = headers.map(h => {
                    let v = ev[h];
                    if (v === undefined || v === null) return '';
                    if (Array.isArray(v) || typeof v === 'object') v = JSON.stringify(v);
                    v = String(v);
                    if (v.indexOf(',') !== -1 || v.indexOf('"') !== -1 || v.indexOf('\n') !== -1) {
                        v = '"' + v.replace(/"/g, '""') + '"';
                    }
                    return v;
                });
                lines.push(row.join(','));
            });
            const csv = lines.join('\n');
            const gz = pako.gzip(csv);
            let bin = '';
            for (let i = 0; i < gz.length; i++) {
                bin += String.fromCharCode(gz[i]);
            }
            return 'data:application/octet-stream;base64,' + btoa(bin);
        }
    } catch (err) {
        console.error('Timelapse compression error:', err);
    }
    return null;
}

/**
 * Send save request to server
 */
async function sendSaveRequest(title, description, tags, compositeImage, illustData, timelapseData, setStatus, setCurrentId, updateIllustDisplay, options = {}) {
    try {
        const payload = {
            title: title,
            description: description,
            tags: tags,
            canvas_width: state.layers[0].width,
            canvas_height: state.layers[0].height,
            background_color: '#FFFFFF',
            illust_data: JSON.stringify(illustData),
            image_data: compositeImage,
            timelapse_data: timelapseData,
            id: state.currentIllustId
        };
        // include optional flags
            if (options) {
            if (typeof options.nsfw !== 'undefined') payload.nsfw = options.nsfw ? 1 : 0;
            if (typeof options.is_visible !== 'undefined') payload.is_visible = options.is_visible ? 1 : 0;
            if (typeof options.artist_name !== 'undefined') payload.artist_name = options.artist_name;
            // forceNew: if true, ensure we clear id so server creates new record
            if (options.forceNew) payload.id = null;
        }

        const res = await fetch('api/save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }

        const json = await res.json();

        if (json.success) {
            if (setStatus) {
                setStatus(`保存完了 (ID: ${json.data.id})`);
            }
            if (setCurrentId) {
                setCurrentId(json.data.id);
            }

            // Update current illust info
            state.currentIllustTitle = title;
            state.currentIllustDescription = description;
            state.currentIllustTags = tags;
            state.hasUnsavedChanges = false;

            // Update UI
            if (updateIllustDisplay) {
                updateIllustDisplay();
            }

            // Clear timelapse events after successful save
            state.timelapseEvents = [];
        } else {
            throw new Error(json.error || 'unknown');
        }
    } catch (error) {
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            throw new Error('ネットワークエラーが発生しました。接続を確認してください。');
        }
        throw error;
    }
}

/**
 * Create new illustration (clear canvas)
 */
export function newIllust(renderLayers, updateIllustDisplay, setStatus) {
    // Confirm if there are unsaved changes
    if (state.hasUnsavedChanges) {
        if (!confirm('未保存の変更があります。新規作成しますか？現在の作業内容は失われます。')) {
            return;
        }
    }

    // Clear all layers
    state.layers.forEach((canvas, i) => {
        const ctx = state.contexts[i];
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        canvas.style.display = 'block';
        canvas.style.opacity = '1';
    });

    // Reset state
    state.activeLayer = 3;
    state.undoStacks = state.layers.map(() => []);
    state.redoStacks = state.layers.map(() => []);
    state.timelapseEvents = [];
    state.currentIllustId = null;
    state.currentIllustTitle = '';
    state.currentIllustDescription = '';
    state.currentIllustTags = '';
    state.hasUnsavedChanges = false;

    try {
        localStorage.removeItem('paint_current_illust_id');
        localStorage.removeItem('paint_canvas_state');
    } catch (e) {
        console.error('Failed to clear persisted data:', e);
    }
    // Hide quick-save button when creating a new blank canvas
    try {
        if (elements.btnSave) elements.btnSave.style.display = 'none';
    } catch (e) {}

    if (updateIllustDisplay) {
        updateIllustDisplay();
    }
    if (renderLayers) {
        renderLayers();
    }
    if (setStatus) {
        setStatus('新規作成しました');
    }
}

/**
 * Mark canvas as changed (unsaved changes)
 */
export function markAsChanged() {
    state.hasUnsavedChanges = true;
}

/**
 * Restore canvas state from saved data
 */
export function restoreCanvasState(canvasState, renderLayers, setActiveLayer, updateIllustDisplay, applyZoom) {
    return new Promise((resolve) => {
        try {
            // Canvas state restoration started (debug logs removed)

            const targetWidth = canvasState.canvasWidth || state.layers[0].width;
            const targetHeight = canvasState.canvasHeight || state.layers[0].height;

            // Resize canvas if needed
            if (targetWidth !== state.layers[0].width || targetHeight !== state.layers[0].height) {
                // Resizing canvas to target dimensions (debug log removed)

                state.layers.forEach((canvas, idx) => {
                    canvas.width = targetWidth;
                    canvas.height = targetHeight;
                    canvas.style.width = `${targetWidth}px`;
                    canvas.style.height = `${targetHeight}px`;
                });

                if (elements.canvasWrap) {
                    elements.canvasWrap.style.width = `${targetWidth}px`;
                    elements.canvasWrap.style.height = `${targetHeight}px`;
                }

                const canvasInfo = document.querySelector('.canvas-info');
                if (canvasInfo) {
                    canvasInfo.textContent = `${targetWidth} x ${targetHeight} px`;
                }

                if (elements.timelapseCanvas) {
                    elements.timelapseCanvas.width = targetWidth;
                    elements.timelapseCanvas.height = targetHeight;
                }
            }

            // Restore layer data
            if (canvasState.layers && canvasState.layers.length > 0) {
                // Restoring layer images (debug log removed)
                const loadPromises = canvasState.layers.map(layerInfo => {
                    return new Promise((resolveLayer) => {
                        if (layerInfo.index < state.layers.length) {
                            const canvas = state.layers[layerInfo.index];
                            const img = new Image();
                            img.onload = () => {
                                const ctx = canvas.getContext('2d');
                                ctx.clearRect(0, 0, canvas.width, canvas.height);
                                ctx.drawImage(img, 0, 0);
                                resolveLayer();
                            };
                            img.onerror = () => {
                                console.error('Failed to load image for layer', layerInfo.index);
                                resolveLayer();
                            };
                            img.src = layerInfo.dataUrl;

                            canvas.style.display = layerInfo.visible ? 'block' : 'none';
                            canvas.style.opacity = layerInfo.opacity;

                            // Restore blend mode
                            if (layerInfo.blendMode) {
                                if (!canvas.dataset) {
                                    canvas.dataset = {};
                                }
                                canvas.dataset.blendMode = layerInfo.blendMode;
                                // Apply CSS mix-blend-mode for preview
                                canvas.style.mixBlendMode = canvasBlendToCSSBlend(layerInfo.blendMode);
                            }
                        } else {
                            resolveLayer();
                        }
                    });
                });

                Promise.all(loadPromises).then(() => {
                    // Restore other state
                    if (canvasState.activeLayer !== undefined) {
                        state.activeLayer = canvasState.activeLayer;
                        if (setActiveLayer) {
                            setActiveLayer(canvasState.activeLayer);
                        }
                    }
                    if (canvasState.currentColor) {
                        state.currentColor = canvasState.currentColor;
                        if (elements.currentColor) {
                            elements.currentColor.style.background = canvasState.currentColor;
                        }
                    }
                    if (canvasState.penSize !== undefined) {
                        state.penSize = canvasState.penSize;
                    }
                    if (canvasState.eraserSize !== undefined) {
                        state.eraserSize = canvasState.eraserSize;
                    }
                    if (canvasState.zoomLevel !== undefined) {
                        state.zoomLevel = canvasState.zoomLevel;
                        if (applyZoom) {
                            applyZoom();
                        }
                    }
                    if (canvasState.panOffset) {
                        state.panOffset = canvasState.panOffset;
                        if (applyZoom) {
                            applyZoom();
                        }
                    }
                    if (canvasState.layerNames) {
                        state.layerNames = canvasState.layerNames;
                    }
                    if (canvasState.timelapseEvents && Array.isArray(canvasState.timelapseEvents)) {
                        state.timelapseEvents = canvasState.timelapseEvents;
                    }
                    if (canvasState.timelapseSnapshots && Array.isArray(canvasState.timelapseSnapshots)) {
                        state.timelapseSnapshots = canvasState.timelapseSnapshots;
                    }
                    if (canvasState.lastSnapshotTime !== undefined) {
                        state.lastSnapshotTime = canvasState.lastSnapshotTime;
                    }

                    // Restore metadata (title/description/tags) if present
                    if (canvasState.currentIllustTitle !== undefined) {
                        state.currentIllustTitle = canvasState.currentIllustTitle || '';
                    }
                    if (canvasState.currentIllustDescription !== undefined) {
                        state.currentIllustDescription = canvasState.currentIllustDescription || '';
                    }
                    if (canvasState.currentIllustTags !== undefined) {
                        state.currentIllustTags = canvasState.currentIllustTags || '';
                    }
                    if (canvasState.hasUnsavedChanges !== undefined) {
                        state.hasUnsavedChanges = !!canvasState.hasUnsavedChanges;
                    }

                    if (renderLayers) {
                        renderLayers();
                    }
                    if (updateIllustDisplay) {
                        updateIllustDisplay();
                    }

                    resolve();
                });
            } else {
                resolve();
            }
        } catch (e) {
            console.error('Failed to restore canvas state:', e);
            resolve();
        }
    });
}

/**
 * Fetch illustration data from server
 */
export async function fetchIllustData(id) {
    const resp = await fetch(`api/load.php?id=${id}`, {
        credentials: 'same-origin'
    });

    if (!resp.ok) {
        throw new Error(`HTTP ${resp.status}: ${resp.statusText}`);
    }

    const json = await resp.json();

    if (!json.success || !json.data) {
        throw new Error(json.error || 'データがありません');
    }

    const illust = json.data;

    if (!illust.illust_data) {
        throw new Error('illust_data is empty');
    }

    try {
        const parsedData = JSON.parse(illust.illust_data);
        return {
            ...illust,
            ...parsedData
        };
    } catch (e) {
        console.error('Failed to parse illust_data:', e);
        throw new Error('イラストデータの解析に失敗しました');
    }
}

/**
 * Load illustration layers to canvas
 */
export async function loadIllustLayers(illustData, renderLayers) {
    const CONFIG = await import('./config.js').then(m => m.CONFIG);

    // Clear current canvas
    state.layers.forEach((canvas, i) => {
        const ctx = state.contexts[i];
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        canvas.style.opacity = '1';
        canvas.style.display = 'block';
        state.layerNames[i] = CONFIG.LAYER_NAMES[i] || `レイヤー ${i}`;
    });

    // Reset undo/redo stacks
    state.undoStacks = state.layers.map(() => []);
    state.redoStacks = state.layers.map(() => []);
    state.timelapseEvents = [];

    // Load layers
    if (illustData.layers && Array.isArray(illustData.layers)) {
        const loadPromises = illustData.layers.map((layerData, idx) => {
            return new Promise((resolve, reject) => {
                if (!layerData.data) {
                    resolve();
                    return;
                }

                const img = new Image();
                img.onload = () => {
                    try {
                        const targetIdx = layerData.order !== undefined ? layerData.order : idx;

                        if (targetIdx < state.layers.length) {
                            const ctx = state.contexts[targetIdx];
                            ctx.clearRect(0, 0, state.layers[targetIdx].width, state.layers[targetIdx].height);
                            ctx.drawImage(img, 0, 0);

                            state.layers[targetIdx].style.opacity = layerData.opacity !== undefined ? layerData.opacity.toString() : '1';
                            state.layers[targetIdx].style.display = layerData.visible !== false ? 'block' : 'none';

                            // Restore blend mode
                            if (layerData.blendMode) {
                                if (!state.layers[targetIdx].dataset) {
                                    state.layers[targetIdx].dataset = {};
                                }
                                state.layers[targetIdx].dataset.blendMode = layerData.blendMode;
                                // Apply CSS mix-blend-mode for preview
                                state.layers[targetIdx].style.mixBlendMode = canvasBlendToCSSBlend(layerData.blendMode);
                            }

                            if (layerData.name) {
                                state.layerNames[targetIdx] = layerData.name;
                            }
                        }
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                };
                img.onerror = (e) => {
                    reject(new Error(`Failed to load layer ${idx} image`));
                };
                img.src = layerData.data;
            });
        });

        await Promise.all(loadPromises);
    }

    if (renderLayers) {
        renderLayers();
    }
}

/**
 * Export current working data as a compressed file (.json.gz)
 * Uses pako.gzip if available, otherwise falls back to plain JSON download.
 */
export function exportWorkingData() {
    try {
        const canvasState = captureCanvasState();
        const json = JSON.stringify({ version: '1.0', exportedAt: Date.now(), data: canvasState });

        if (typeof pako !== 'undefined' && typeof pako.gzip === 'function') {
            const gz = pako.gzip(json);
            const u8 = new Uint8Array(gz);
            const blob = new Blob([u8], { type: 'application/gzip' });
            const name = `paint-export-${new Date().toISOString().replace(/[:.]/g,'')}.json.gz`;
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = name;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            if (elements && elements.statusText) elements.statusText.textContent = 'エクスポートしました';
            return;
        }

        // Fallback: plain JSON
        const blob = new Blob([json], { type: 'application/json' });
        const name = `paint-export-${new Date().toISOString().replace(/[:.]/g,'')}.json`;
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        if (elements && elements.statusText) elements.statusText.textContent = 'エクスポートしました';
    } catch (err) {
        console.error('Export failed:', err);
        try { alert('エクスポートに失敗しました。コンソールを確認してください。'); } catch (ignored) {}
    }
}

/**
 * Import working data from a file (supports gzip-compressed JSON produced by exportWorkingData)
 * @param {File} file
 * @returns {Promise<void>}
 */
export async function importWorkingFile(file) {
    try {
        if (!file) throw new Error('No file provided');
        const arrayBuffer = await file.arrayBuffer();

        let text = null;
        try {
            // Try to decompress with pako if available
            if (typeof pako !== 'undefined' && typeof pako.ungzip === 'function') {
                const u8 = new Uint8Array(arrayBuffer);
                const decomp = pako.ungzip(u8, { to: 'string' });
                text = decomp;
            }
        } catch (e) {
            console.warn('Decompression failed, will try treating as plain text:', e);
        }

        if (text === null) {
            // Fallback: try interpret as UTF-8 text
            try {
                const decoder = new TextDecoder('utf-8');
                text = decoder.decode(arrayBuffer);
            } catch (e) {
                throw new Error('ファイルの読み込みに失敗しました');
            }
        }

        const parsed = JSON.parse(text);
        // Support wrapped format { version, exportedAt, data }
        const canvasState = parsed && parsed.data ? parsed.data : parsed;

        // Use restoreCanvasState (exported in this module)
        await restoreCanvasState(canvasState);

        if (elements && elements.statusText) elements.statusText.textContent = 'インポート完了: 作業状態を復元しました';
    } catch (err) {
        console.error('Import failed:', err);
        try { alert('インポートに失敗しました: ' + (err.message || err)); } catch (ignored) {}
    }
}

// Setup beforeunload guard to prevent accidental close when there are unsaved changes
if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', (e) => {
        if (state.hasUnsavedChanges) {
            const msg = '未保存の変更があります。ページを離れると失われます。よろしいですか？';
            e.preventDefault();
            // Modern browsers ignore custom strings, but setting returnValue is required
            e.returnValue = msg;
            return msg;
        }
        return undefined;
    });
}
