/**
 * Storage Module
 * Handles saving and loading illustration data to/from server and localStorage
 */

import { state, elements } from './state.js';

/**
 * Load persisted state from localStorage
 * @param {Function} restoreCanvasState - Callback to restore canvas state
 * @param {Function} setStatus - Callback to update status bar
 */
export async function loadPersistedState(restoreCanvasState, setStatus) {
    try {
        // Load current illustration ID from localStorage (if any)
        const savedId = localStorage.getItem('paint_current_illust_id');
        if (savedId) {
            state.currentIllustId = savedId;
            elements.illustId.textContent = savedId;
        }

        // Load canvas state from localStorage and restore
        const savedState = localStorage.getItem('paint_canvas_state');
        if (savedState) {
            const canvasState = JSON.parse(savedState);
            await restoreCanvasState(canvasState);
            if (setStatus) {
                setStatus('以前の作業を復元しました');
            }
        }
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

    try {
        const canvasState = captureCanvasState();
        const stateJson = JSON.stringify(canvasState);
        localStorage.setItem('paint_canvas_state', stateJson);
    } catch (e) {
        console.error('Failed to save persisted state:', e);
        if (e.name === 'QuotaExceededError') {
            console.error('LocalStorage quota exceeded! Canvas state is too large.');
            alert('警告: キャンバス状態が大きすぎて保存できません。ブラウザのローカルストレージ制限を超えています。');
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
}

/**
 * Capture current canvas state
 */
export function captureCanvasState() {
    const layerData = state.layers.map((canvas, index) => ({
        index: index,
        visible: canvas.style.display !== 'none',
        opacity: parseFloat(canvas.style.opacity || '1'),
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
export async function saveIllust(title, description = '', tags = '', setStatus, setCurrentId, updateIllustDisplay) {
    if (setStatus) {
        setStatus('保存中...');
    }

    try {
        const compositeImage = createCompositeImage();
        const illustData = buildIllustData();
        const timelapseData = await compressTimelapseData();

        await sendSaveRequest(title, description, tags, compositeImage, illustData, timelapseData, setStatus, setCurrentId, updateIllustDisplay);
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

    // Draw all visible layers
    state.layers.forEach(canvas => {
        if (canvas.style.display !== 'none') {
            octx.globalAlpha = parseFloat(canvas.style.opacity || '1');
            octx.drawImage(canvas, 0, 0);
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

        if (window.Worker) {
            // Use Web Worker for compression
            if (!window._timelapseWorker) {
                window._timelapseWorker = new Worker('../js/timelapse_worker.js');
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
            // Fallback to main-thread compression
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
async function sendSaveRequest(title, description, tags, compositeImage, illustData, timelapseData, setStatus, setCurrentId, updateIllustDisplay) {
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
