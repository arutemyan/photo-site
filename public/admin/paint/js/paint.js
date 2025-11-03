/* Enhanced Paint Application with Professional UI
 * Features:
 * - Multiple drawing tools (pen, eraser, bucket, eyedropper)
 * - 4-layer system with full controls
 * - Color palette with 16 preset colors
 * - Timelapse recording and playback in modal overlay
 * - Undo/Redo system (up to 50 steps)
 * - Zoom controls
 * - Keyboard shortcuts
 * - Responsive design
 */

(function () {
    'use strict';

    // ===== Constants =====
    const DEFAULT_COLORS = [
        '#000000', '#FFFFFF', '#FF0000', '#00FF00',
        '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF',
        '#800000', '#008000', '#000080', '#808000',
        '#800080', '#008080', '#C0C0C0', '#808080'
    ];

    const LAYER_NAMES = ['背景', '下書き', '清書', '着色'];
    const MAX_UNDO_STEPS = 50;

    // ===== State =====
    const state = {
        layers: [],
        contexts: [],
        activeLayer: 3,
        currentTool: 'pen',
        currentColor: '#000000',
        penSize: 4,
        eraserSize: 10,
        bucketTolerance: 32,
        isDrawing: false,
        undoStacks: [[], [], [], []],
        redoStacks: [[], [], [], []],
    timelapseEvents: [],
    timelapseSnapshots: [], // { idx, t, data }
    lastSnapshotTime: 0,
        currentIllustId: null,
        zoomLevel: 1,
        isPanning: false,
        panStart: { x: 0, y: 0 },
        panOffset: { x: 0, y: 0 },
        spaceKeyPressed: false,
        layerNames: [...LAYER_NAMES]
    };

    // ===== DOM Elements =====
    const elements = {
        // Canvas
        canvasWrap: document.getElementById('canvas-wrap'),
        layers: Array.from(document.querySelectorAll('canvas.layer')),

        // Header buttons
        btnSave: document.getElementById('btn-save'),
        btnTimelapse: document.getElementById('btn-timelapse'),
        btnNew: document.getElementById('btn-new'),
        btnClear: document.getElementById('btn-clear'),
        illustId: document.getElementById('illust-id'),

        // Tools
        toolBtns: document.querySelectorAll('.tool-btn[data-tool]'),
        toolUndo: document.getElementById('tool-undo'),
        toolRedo: document.getElementById('tool-redo'),
        toolZoomIn: document.getElementById('tool-zoom-in'),
        toolZoomOut: document.getElementById('tool-zoom-out'),
        toolZoomFit: document.getElementById('tool-zoom-fit'),

        // Color palette
        colorPicker: document.getElementById('color-picker'),
        currentColor: document.getElementById('current-color'),
        currentColorHex: document.getElementById('current-color-hex'),
        colorPaletteGrid: document.getElementById('color-palette-grid'),

        // Tool settings
        penSize: document.getElementById('pen-size'),
        penSizeValue: document.getElementById('pen-size-value'),
        penAntialias: document.getElementById('pen-antialias'),
        eraserSize: document.getElementById('eraser-size'),
        eraserSizeValue: document.getElementById('eraser-size-value'),
        bucketTolerance: document.getElementById('bucket-tolerance'),
        bucketToleranceValue: document.getElementById('bucket-tolerance-value'),
        penSettings: document.getElementById('pen-settings'),
        eraserSettings: document.getElementById('eraser-settings'),
        bucketSettings: document.getElementById('bucket-settings'),

        // Layers
        layersList: document.getElementById('layers-list'),

        // Context menu
        layerContextMenu: document.getElementById('layer-context-menu'),

        // Open modal
        openModalOverlay: document.getElementById('open-modal-overlay'),
        openModalClose: document.getElementById('open-modal-close'),
        openModalCancel: document.getElementById('open-modal-cancel'),
        openModalLoad: document.getElementById('open-modal-load'),
        illustGrid: document.getElementById('illust-grid'),
        openModalEmpty: document.getElementById('open-modal-empty'),
        btnOpen: document.getElementById('btn-open'),

        // Status bar
        statusText: document.getElementById('status-text'),
        statusTool: document.getElementById('status-tool'),
        statusLayer: document.getElementById('status-layer'),

        // Timelapse modal
        timelapseOverlay: document.getElementById('timelapse-overlay'),
        timelapseCanvas: document.getElementById('timelapse-canvas'),
        timelapsePlay: document.getElementById('timelapse-play'),
        timelapseStop: document.getElementById('timelapse-stop'),
        timelapseRestart: document.getElementById('timelapse-restart'),
        timelapseSeek: document.getElementById('timelapse-seek'),
        timelapseSpeed: document.getElementById('timelapse-speed'),
        timelapseSpeedValue: document.getElementById('timelapse-speed-value'),
        timelapseCurrentTime: document.getElementById('timelapse-current-time'),
        timelapseTotalTime: document.getElementById('timelapse-total-time'),
        timelapseClose: document.getElementById('timelapse-close'),
        timelapseIgnoreTime: document.getElementById('timelapse-ignore-time')
    };

    // ===== Initialization =====
    function init() {
        // Initialize contexts
        state.layers = elements.layers;
        state.contexts = state.layers.map(canvas => canvas.getContext('2d', { willReadFrequently: true }));

        // Set layer z-indexes
        state.layers.forEach((canvas, i) => {
            canvas.style.zIndex = i;
        });

        // Load persisted ID
        loadPersistedId();

        // Initialize UI
        initColorPalette();
        initLayers();
        initLayerContextMenu();
        initToolListeners();
        initCanvasListeners();
        initKeyboardShortcuts();
        initTimelapseModal();
        initCanvasPan();
        initOpenModal();

        setStatus('準備完了');
    }

    // ===== Color Palette =====
    function initColorPalette() {
        // Generate 16-color palette
        DEFAULT_COLORS.forEach(color => {
            const swatch = document.createElement('div');
            swatch.className = 'color-swatch';
            swatch.style.background = color;
            swatch.title = color;
            swatch.addEventListener('click', () => setColor(color));
            elements.colorPaletteGrid.appendChild(swatch);
        });

        // Color picker
        elements.colorPicker.addEventListener('input', (e) => {
            setColor(e.target.value);
        });
    }

    function setColor(color) {
        state.currentColor = color;
        elements.currentColor.style.background = color;
        elements.currentColorHex.textContent = color.toUpperCase();
        elements.colorPicker.value = color;
    }

    // ===== Layers =====
    function initLayers() {
        renderLayers();
    }

    function renderLayers() {
        elements.layersList.innerHTML = '';

        // Render in reverse order (top layer first)
        for (let i = state.layers.length - 1; i >= 0; i--) {
            const layer = state.layers[i];
            const layerItem = document.createElement('div');
            layerItem.className = 'layer-item' + (i === state.activeLayer ? ' active' : '');
            layerItem.dataset.layer = i;

            // Visibility toggle
            const visibility = document.createElement('span');
            visibility.className = 'layer-visibility';
            visibility.textContent = layer.style.display === 'none' ? '👁️‍🗨️' : '👁️';
            visibility.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleLayerVisibility(i);
            });

            // Layer name (editable)
            const name = document.createElement('span');
            name.className = 'layer-name';
            name.contentEditable = 'false';
            name.textContent = state.layerNames[i] || `レイヤー ${i}`;
            name.addEventListener('dblclick', (e) => {
                e.stopPropagation();
                name.contentEditable = 'true';
                name.focus();
                document.execCommand('selectAll', false, null);
            });
            name.addEventListener('blur', () => {
                name.contentEditable = 'false';
                const newName = name.textContent.trim();
                if (newName) {
                    state.layerNames[i] = newName;
                } else {
                    name.textContent = state.layerNames[i];
                }
            });
            name.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    name.blur();
                }
            });

            // Opacity control
            const opacity = document.createElement('input');
            opacity.type = 'range';
            opacity.className = 'layer-opacity setting-slider';
            opacity.min = 0;
            opacity.max = 100;
            opacity.value = parseInt((parseFloat(layer.style.opacity || '1') * 100).toString());
            opacity.addEventListener('input', (e) => {
                e.stopPropagation();
                setLayerOpacity(i, parseInt(e.target.value) / 100);
            });

            // Controls
            const controls = document.createElement('div');
            controls.className = 'layer-controls';

            const upBtn = document.createElement('button');
            upBtn.className = 'layer-control-btn';
            upBtn.textContent = '↑';
            upBtn.title = '上へ';
            upBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                moveLayer(i, -1);
            });

            const downBtn = document.createElement('button');
            downBtn.className = 'layer-control-btn';
            downBtn.textContent = '↓';
            downBtn.title = '下へ';
            downBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                moveLayer(i, 1);
            });

            controls.appendChild(upBtn);
            controls.appendChild(downBtn);

            // Click to select layer
            layerItem.addEventListener('click', () => {
                setActiveLayer(i);
            });

            // Right-click context menu
            layerItem.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                showLayerContextMenu(e.clientX, e.clientY, i);
            });

            layerItem.appendChild(visibility);
            layerItem.appendChild(name);
            layerItem.appendChild(opacity);
            layerItem.appendChild(controls);

            elements.layersList.appendChild(layerItem);
        }

        updateStatusBar();
    }

    // ===== Layer Context Menu =====
    let contextMenuTargetLayer = -1;

    function showLayerContextMenu(x, y, layerIndex) {
        contextMenuTargetLayer = layerIndex;

        elements.layerContextMenu.style.left = x + 'px';
        elements.layerContextMenu.style.top = y + 'px';
        elements.layerContextMenu.classList.remove('hidden');

        // Close on click outside
        const closeMenu = (e) => {
            if (!elements.layerContextMenu.contains(e.target)) {
                elements.layerContextMenu.classList.add('hidden');
                document.removeEventListener('click', closeMenu);
            }
        };

        setTimeout(() => {
            document.addEventListener('click', closeMenu);
        }, 0);
    }

    function initLayerContextMenu() {
        const menuItems = elements.layerContextMenu.querySelectorAll('.context-menu-item');

        menuItems.forEach(item => {
            item.addEventListener('click', () => {
                const action = item.dataset.action;

                switch (action) {
                    case 'duplicate':
                        duplicateLayer(contextMenuTargetLayer);
                        break;
                    case 'merge-down':
                        mergeLayerDown(contextMenuTargetLayer);
                        break;
                    case 'clear':
                        clearLayer(contextMenuTargetLayer);
                        break;
                }

                elements.layerContextMenu.classList.add('hidden');
            });
        });
    }

    function duplicateLayer(layerIndex) {
        if (layerIndex < 0 || layerIndex >= state.layers.length) return;

        pushUndo(layerIndex);

        const sourceCanvas = state.layers[layerIndex];
        const targetCanvas = state.layers[(layerIndex + 1) % state.layers.length];
        const targetCtx = state.contexts[(layerIndex + 1) % state.layers.length];

        // Copy to next layer
        targetCtx.clearRect(0, 0, targetCanvas.width, targetCanvas.height);
        targetCtx.drawImage(sourceCanvas, 0, 0);

        // Copy layer properties
        targetCanvas.style.opacity = sourceCanvas.style.opacity;
        targetCanvas.style.display = sourceCanvas.style.display;

        state.layerNames[(layerIndex + 1) % state.layers.length] = state.layerNames[layerIndex] + ' (コピー)';

        renderLayers();
        setStatus(`レイヤー ${layerIndex} を複製しました`);
    }

    function mergeLayerDown(layerIndex) {
        if (layerIndex <= 0) {
            setStatus('最下層のレイヤーは結合できません');
            return;
        }

        pushUndo(layerIndex - 1);

        const sourceCanvas = state.layers[layerIndex];
        const targetCanvas = state.layers[layerIndex - 1];
        const targetCtx = state.contexts[layerIndex - 1];

        // Merge down
        targetCtx.globalAlpha = parseFloat(sourceCanvas.style.opacity || '1');
        targetCtx.drawImage(sourceCanvas, 0, 0);
        targetCtx.globalAlpha = 1;

        // Clear source layer
        const sourceCtx = state.contexts[layerIndex];
        sourceCtx.clearRect(0, 0, sourceCanvas.width, sourceCanvas.height);

        renderLayers();
        setStatus(`レイヤー ${layerIndex} を下のレイヤーと結合しました`);
    }

    function clearLayer(layerIndex) {
        if (!confirm(`レイヤー ${layerIndex} (${state.layerNames[layerIndex]}) をクリアしますか？`)) {
            return;
        }

        pushUndo(layerIndex);

        const ctx = state.contexts[layerIndex];
        const canvas = state.layers[layerIndex];

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        setStatus(`レイヤー ${layerIndex} をクリアしました`);
    }

    function setActiveLayer(index) {
        state.activeLayer = index;
        renderLayers();
    }

    function toggleLayerVisibility(index) {
        const layer = state.layers[index];
        layer.style.display = layer.style.display === 'none' ? 'block' : 'none';
        renderLayers();
    }

    function setLayerOpacity(index, opacity) {
        state.layers[index].style.opacity = opacity.toString();
    }

    function moveLayer(index, delta) {
        const to = index + delta;
        if (to < 0 || to >= state.layers.length) return;

        // Swap DOM elements
        const a = state.layers[index];
        const b = state.layers[to];
        const parent = a.parentNode;

        if (delta > 0) {
            parent.insertBefore(b, a);
        } else {
            parent.insertBefore(a, b);
        }

        // Swap in arrays
        [state.layers[index], state.layers[to]] = [state.layers[to], state.layers[index]];
        [state.contexts[index], state.contexts[to]] = [state.contexts[to], state.contexts[index]];
        [state.undoStacks[index], state.undoStacks[to]] = [state.undoStacks[to], state.undoStacks[index]];
        [state.redoStacks[index], state.redoStacks[to]] = [state.redoStacks[to], state.redoStacks[index]];

        // Update z-index
        state.layers.forEach((canvas, i) => {
            canvas.style.zIndex = i;
        });

        renderLayers();
    }

    // ===== Tools =====
    function initToolListeners() {
        // Tool buttons
        elements.toolBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tool = btn.dataset.tool;
                setTool(tool);
            });
        });

        // Undo/Redo
        elements.toolUndo.addEventListener('click', undo);
        elements.toolRedo.addEventListener('click', redo);

        // Zoom
        elements.toolZoomIn.addEventListener('click', () => zoom(0.1));
        elements.toolZoomOut.addEventListener('click', () => zoom(-0.1));
        elements.toolZoomFit.addEventListener('click', zoomFit);

        // Header buttons
        elements.btnSave.addEventListener('click', saveIllust);
        elements.btnTimelapse.addEventListener('click', openTimelapseModal);
        elements.btnNew.addEventListener('click', newIllust);
        elements.btnClear.addEventListener('click', clearCurrentLayer);

        // Tool settings
        elements.penSize.addEventListener('input', (e) => {
            state.penSize = parseInt(e.target.value);
            elements.penSizeValue.textContent = state.penSize;
        });

        elements.eraserSize.addEventListener('input', (e) => {
            state.eraserSize = parseInt(e.target.value);
            elements.eraserSizeValue.textContent = state.eraserSize;
        });

        elements.bucketTolerance.addEventListener('input', (e) => {
            state.bucketTolerance = parseInt(e.target.value);
            elements.bucketToleranceValue.textContent = state.bucketTolerance;
        });
    }

    function setTool(tool) {
        state.currentTool = tool;

        // Update active button
        elements.toolBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tool === tool);
        });

        // Show/hide tool settings
        elements.penSettings.classList.toggle('hidden', tool !== 'pen');
        elements.eraserSettings.classList.toggle('hidden', tool !== 'eraser');
        elements.bucketSettings.classList.toggle('hidden', tool !== 'bucket');

        // Update cursor
        state.layers.forEach(canvas => {
            if (tool === 'pen' || tool === 'eraser') {
                canvas.style.cursor = 'crosshair';
            } else if (tool === 'bucket') {
                canvas.style.cursor = 'pointer';
            } else if (tool === 'eyedropper') {
                canvas.style.cursor = 'cell';
            }
        });

        updateStatusBar();
    }

    // ===== Canvas Drawing =====
    function initCanvasListeners() {
        state.layers.forEach((canvas, i) => {
            canvas.addEventListener('mousedown', (e) => handleDrawStart(e, i));
            canvas.addEventListener('mousemove', handleDrawMove);
            canvas.addEventListener('mouseup', handleDrawEnd);
            canvas.addEventListener('mouseleave', handleDrawEnd);

            canvas.addEventListener('touchstart', (e) => { e.preventDefault(); handleDrawStart(e, i); }, { passive: false });
            canvas.addEventListener('touchmove', (e) => { e.preventDefault(); handleDrawMove(e); }, { passive: false });
            canvas.addEventListener('touchend', (e) => { e.preventDefault(); handleDrawEnd(e); }, { passive: false });
        });
    }

    function handleDrawStart(e, layerIndex) {
        // Don't draw when panning
        if (state.spaceKeyPressed || state.isPanning) {
            return;
        }

        state.activeLayer = layerIndex;
        renderLayers();

        const pos = getPointerPos(e, state.layers[layerIndex]);
        const ctx = state.contexts[layerIndex];

        if (state.currentTool === 'eyedropper') {
            pickColor(layerIndex, pos);
            return;
        }

        if (state.currentTool === 'bucket') {
            floodFill(layerIndex, pos);
            return;
        }

        // Save undo state
        pushUndo(layerIndex);

        state.isDrawing = true;

        // Start drawing
        ctx.beginPath();
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (state.currentTool === 'pen') {
            ctx.lineWidth = state.penSize;
            ctx.strokeStyle = state.currentColor;
            ctx.globalCompositeOperation = 'source-over';
        } else if (state.currentTool === 'eraser') {
            ctx.lineWidth = state.eraserSize;
            ctx.globalCompositeOperation = 'destination-out';
        }

        ctx.moveTo(pos.x, pos.y);

        // Record timelapse
        recordTimelapse({
            t: Date.now(),
            type: 'start',
            layer: layerIndex,
            x: pos.x,
            y: pos.y,
            color: state.currentColor,
            size: state.currentTool === 'pen' ? state.penSize : state.eraserSize,
            tool: state.currentTool
        });
    }

    function handleDrawMove(e) {
        if (!state.isDrawing) return;

        const pos = getPointerPos(e, state.layers[state.activeLayer]);
        const ctx = state.contexts[state.activeLayer];

        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();

        recordTimelapse({
            t: Date.now(),
            type: 'move',
            layer: state.activeLayer,
            x: pos.x,
            y: pos.y
        });
    }

    function handleDrawEnd() {
        if (!state.isDrawing) return;

        state.isDrawing = false;

        recordTimelapse({
            t: Date.now(),
            type: 'end',
            layer: state.activeLayer
        });
    }

    function getPointerPos(e, canvas) {
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX ?? e.touches?.[0]?.clientX ?? 0) - rect.left;
        const y = (e.clientY ?? e.touches?.[0]?.clientY ?? 0) - rect.top;

        // Scale for canvas coordinates
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;

        return {
            x: x * scaleX,
            y: y * scaleY
        };
    }

    // ===== Drawing Tools =====
    function pickColor(layerIndex, pos) {
        const ctx = state.contexts[layerIndex];
        const imageData = ctx.getImageData(pos.x, pos.y, 1, 1);
        const [r, g, b] = imageData.data;

        const hex = '#' + [r, g, b].map(x => {
            const h = x.toString(16);
            return h.length === 1 ? '0' + h : h;
        }).join('');

        setColor(hex);
        setTool('pen');
        setStatus(`色を取得しました: ${hex}`);
    }

    function floodFill(layerIndex, pos) {
        pushUndo(layerIndex);

        const canvas = state.layers[layerIndex];
        const ctx = state.contexts[layerIndex];
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const pixels = imageData.data;

        const startX = Math.floor(pos.x);
        const startY = Math.floor(pos.y);
        const startIndex = (startY * canvas.width + startX) * 4;

        const targetColor = {
            r: pixels[startIndex],
            g: pixels[startIndex + 1],
            b: pixels[startIndex + 2],
            a: pixels[startIndex + 3]
        };

        // Parse fill color
        const fillColor = hexToRgb(state.currentColor);

        // Check if already same color
        if (colorMatch(targetColor, fillColor, 0)) {
            setStatus('同じ色です');
            return;
        }

        // Flood fill algorithm
        const stack = [[startX, startY]];
        const visited = new Set();

        while (stack.length > 0) {
            const [x, y] = stack.pop();

            if (x < 0 || x >= canvas.width || y < 0 || y >= canvas.height) continue;

            const key = `${x},${y}`;
            if (visited.has(key)) continue;
            visited.add(key);

            const index = (y * canvas.width + x) * 4;
            const currentColor = {
                r: pixels[index],
                g: pixels[index + 1],
                b: pixels[index + 2],
                a: pixels[index + 3]
            };

            if (!colorMatch(currentColor, targetColor, state.bucketTolerance)) continue;

            // Fill pixel
            pixels[index] = fillColor.r;
            pixels[index + 1] = fillColor.g;
            pixels[index + 2] = fillColor.b;
            pixels[index + 3] = 255;

            // Add neighbors
            stack.push([x + 1, y]);
            stack.push([x - 1, y]);
            stack.push([x, y + 1]);
            stack.push([x, y - 1]);
        }

        ctx.putImageData(imageData, 0, 0);

        recordTimelapse({
            t: Date.now(),
            type: 'fill',
            layer: layerIndex,
            x: startX,
            y: startY,
            color: state.currentColor
        });

        setStatus('塗りつぶし完了');
    }

    function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : { r: 0, g: 0, b: 0 };
    }

    function colorMatch(c1, c2, tolerance) {
        return Math.abs(c1.r - c2.r) <= tolerance &&
            Math.abs(c1.g - c2.g) <= tolerance &&
            Math.abs(c1.b - c2.b) <= tolerance;
    }

    // ===== Undo/Redo =====
    function pushUndo(layerIndex) {
        const canvas = state.layers[layerIndex];
        const snapshot = {
            img: canvas.toDataURL(),
            visible: canvas.style.display !== 'none',
            opacity: canvas.style.opacity || '1'
        };

        state.undoStacks[layerIndex].push(snapshot);

        if (state.undoStacks[layerIndex].length > MAX_UNDO_STEPS) {
            state.undoStacks[layerIndex].shift();
        }

        state.redoStacks[layerIndex] = [];
    }

    function undo() {
        const stack = state.undoStacks[state.activeLayer];
        if (stack.length === 0) {
            setStatus('これ以上戻せません');
            return;
        }

        const current = {
            img: state.layers[state.activeLayer].toDataURL(),
            visible: state.layers[state.activeLayer].style.display !== 'none',
            opacity: state.layers[state.activeLayer].style.opacity || '1'
        };

        state.redoStacks[state.activeLayer].push(current);

        const snapshot = stack.pop();
        restoreSnapshot(state.activeLayer, snapshot);

        setStatus('元に戻しました');
    }

    function redo() {
        const stack = state.redoStacks[state.activeLayer];
        if (stack.length === 0) {
            setStatus('やり直せません');
            return;
        }

        const snapshot = stack.pop();
        pushUndo(state.activeLayer);
        restoreSnapshot(state.activeLayer, snapshot);

        setStatus('やり直しました');
    }

    function restoreSnapshot(layerIndex, snapshot) {
        const img = new Image();
        img.onload = () => {
            const ctx = state.contexts[layerIndex];
            const canvas = state.layers[layerIndex];

            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);

            canvas.style.display = snapshot.visible ? 'block' : 'none';
            canvas.style.opacity = snapshot.opacity;

            renderLayers();
        };
        img.src = snapshot.img;
    }

    // ===== Zoom =====
    function zoom(delta) {
        state.zoomLevel = Math.max(0.25, Math.min(4, state.zoomLevel + delta));
        applyZoom();
    }

    function zoomFit() {
        state.zoomLevel = 1;
        applyZoom();
    }

    function applyZoom() {
        const scale = state.zoomLevel;
        const transform = `scale(${scale}) translate(${state.panOffset.x / scale}px, ${state.panOffset.y / scale}px)`;
        elements.canvasWrap.style.transform = transform;
        setStatus(`ズーム: ${Math.round(scale * 100)}%`);
    }

    // ===== Canvas Pan =====
    function initCanvasPan() {
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space' && !state.spaceKeyPressed) {
                state.spaceKeyPressed = true;
                state.layers.forEach(canvas => {
                    canvas.style.cursor = 'grab';
                });
            }
        });

        document.addEventListener('keyup', (e) => {
            if (e.code === 'Space') {
                state.spaceKeyPressed = false;
                state.isPanning = false;
                setTool(state.currentTool); // Restore cursor
            }
        });

        elements.canvasWrap.addEventListener('mousedown', (e) => {
            if (state.spaceKeyPressed) {
                state.isPanning = true;
                state.panStart = { x: e.clientX, y: e.clientY };
                state.layers.forEach(canvas => {
                    canvas.style.cursor = 'grabbing';
                });
                e.preventDefault();
            }
        });

        document.addEventListener('mousemove', (e) => {
            if (state.isPanning && state.spaceKeyPressed) {
                const dx = e.clientX - state.panStart.x;
                const dy = e.clientY - state.panStart.y;

                state.panOffset.x += dx;
                state.panOffset.y += dy;
                state.panStart = { x: e.clientX, y: e.clientY };

                applyPan();
                e.preventDefault();
            }
        });

        document.addEventListener('mouseup', () => {
            if (state.isPanning) {
                state.isPanning = false;
                if (state.spaceKeyPressed) {
                    state.layers.forEach(canvas => {
                        canvas.style.cursor = 'grab';
                    });
                }
            }
        });
    }

    function applyPan() {
        const transform = `scale(${state.zoomLevel}) translate(${state.panOffset.x / state.zoomLevel}px, ${state.panOffset.y / state.zoomLevel}px)`;
        elements.canvasWrap.style.transform = transform;
    }

    // ===== Keyboard Shortcuts =====
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+Z: Undo
            if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
            }

            // Ctrl+Y or Ctrl+Shift+Z: Redo
            if ((e.ctrlKey && e.key === 'y') || (e.ctrlKey && e.shiftKey && e.key === 'z')) {
                e.preventDefault();
                redo();
            }

            // Ctrl+S: Save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveIllust();
            }

            // Tool shortcuts
            if (!e.ctrlKey && !e.altKey && !e.metaKey) {
                switch (e.key) {
                    case 'p': setTool('pen'); break;
                    case 'e': setTool('eraser'); break;
                    case 'b': setTool('bucket'); break;
                    case 'i': setTool('eyedropper'); break;
                }
            }
        });
    }

    // ===== Timelapse =====
    function recordTimelapse(event) {
        state.timelapseEvents.push(event);
        // Consider creating a snapshot every N events or every M ms
        const SNAPSHOT_EVENTS = 200;
        const SNAPSHOT_MS = 2000;
        const now = Date.now();
        const len = state.timelapseEvents.length;
        if (len % SNAPSHOT_EVENTS === 0 || (now - (state.lastSnapshotTime || 0)) > SNAPSHOT_MS) {
            try {
                createTimelapseSnapshot(len - 1);
                state.lastSnapshotTime = now;
            } catch (e) {
                console.warn('Snapshot creation failed:', e);
            }
        }
    }

    function createTimelapseSnapshot(eventIndex) {
        // composite current visible layers into a dataURL snapshot
        const w = state.layers[0].width, h = state.layers[0].height;
        const tmp = document.createElement('canvas');
        tmp.width = w; tmp.height = h;
        const tctx = tmp.getContext('2d');
        // white background
        tctx.fillStyle = '#FFFFFF'; tctx.fillRect(0,0,w,h);
        state.layers.forEach(c => {
            if (c.style.display !== 'none') {
                tctx.globalAlpha = parseFloat(c.style.opacity || '1');
                tctx.drawImage(c,0,0);
            }
        });
        const data = tmp.toDataURL('image/png');
        state.timelapseSnapshots.push({ idx: eventIndex, t: Date.now(), data });
        // keep last few snapshots to limit memory
        if (state.timelapseSnapshots.length > 10) state.timelapseSnapshots.shift();
    }

    // Lightweight preview update for seek (fast, avoids full replay)
    function updateTimelapsePreview(pct) {
        const total = state.timelapseEvents.length;
        const idx = Math.max(0, Math.min(total - 1, Math.floor(total * pct)));
        // Try to find a snapshot at or before idx
        let snap = null;
        for (let i = state.timelapseSnapshots.length - 1; i >= 0; i--) {
            if (state.timelapseSnapshots[i].idx <= idx) { snap = state.timelapseSnapshots[i]; break; }
        }
        if (snap) {
            // draw snapshot to timelapse canvas for quick preview
            const img = new Image();
            img.onload = () => {
                const c = elements.timelapseCanvas;
                const ctx = c.getContext('2d');
                ctx.clearRect(0,0,c.width,c.height);
                ctx.drawImage(img, 0, 0);
            };
            img.src = snap.data;
        } else {
            // no snapshot: clear preview canvas
            const c = elements.timelapseCanvas;
            const ctx = c.getContext('2d');
            ctx.clearRect(0,0,c.width,c.height);
            ctx.fillStyle = '#FFFFFF'; ctx.fillRect(0,0,c.width,c.height);
        }
    }

    // performSeek does heavier work: restore nearest snapshot then replay events up to target index
    function performSeek(pct) {
        const total = state.timelapseEvents.length;
        if (total === 0) return;
        const targetIdx = Math.max(0, Math.min(total - 1, Math.floor(total * pct)));

        // find nearest snapshot not after targetIdx
        let snap = null;
        for (let i = state.timelapseSnapshots.length - 1; i >= 0; i--) {
            if (state.timelapseSnapshots[i].idx <= targetIdx) { snap = state.timelapseSnapshots[i]; break; }
        }

        // start with blank canvas
        const c = elements.timelapseCanvas;
        const ctx = c.getContext('2d');
        ctx.clearRect(0,0,c.width,c.height);
        ctx.fillStyle = '#FFFFFF'; ctx.fillRect(0,0,c.width,c.height);

        if (snap) {
            // draw snapshot then replay remaining events
            const img = new Image();
            img.onload = () => {
                ctx.drawImage(img, 0, 0);
                const start = snap.idx + 1;
                for (let i = start; i <= targetIdx; i++) {
                    applyTimelapseEventToCanvas(ctx, state.timelapseEvents[i]);
                }
                elements.timelapseSeek.value = Math.floor(((targetIdx+1) / (total || 1)) * 100);
            };
            img.src = snap.data;
        } else {
            // no snapshot, replay from 0
            for (let i = 0; i <= targetIdx; i++) {
                applyTimelapseEventToCanvas(ctx, state.timelapseEvents[i]);
            }
            elements.timelapseSeek.value = Math.floor(((targetIdx+1) / (total || 1)) * 100);
        }
    }

    function applyTimelapseEventToCanvas(ctx, ev) {
        if (!ev) return;
        if (ev.type === 'start') {
            ctx.beginPath(); ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.lineWidth = ev.size || 4;
            if (ev.tool === 'eraser') ctx.globalCompositeOperation = 'destination-out'; else { ctx.strokeStyle = ev.color || '#000'; ctx.globalCompositeOperation = 'source-over'; }
            ctx.moveTo(ev.x, ev.y);
        } else if (ev.type === 'move') {
            ctx.lineTo(ev.x, ev.y); ctx.stroke();
        } else if (ev.type === 'fill') {
            ctx.fillStyle = ev.color || '#000'; ctx.fillRect(0,0,ctx.canvas.width, ctx.canvas.height);
        }
    }

    function initTimelapseModal() {
        elements.timelapseClose.addEventListener('click', closeTimelapseModal);
        elements.timelapseOverlay.addEventListener('click', (e) => {
            if (e.target === elements.timelapseOverlay) {
                closeTimelapseModal();
            }
        });

        elements.timelapsePlay.addEventListener('click', toggleTimelapsePlayback);
        elements.timelapseStop.addEventListener('click', stopTimelapsePlayback);
        elements.timelapseRestart.addEventListener('click', restartTimelapse);

        elements.timelapseSpeed.addEventListener('input', (e) => {
            elements.timelapseSpeedValue.textContent = e.target.value + 'x';
        });

        // Seek range: update quick preview on input, perform heavy seek on change/pointerup
        let seeking = false;
        elements.timelapseSeek.addEventListener('input', (e) => {
            seeking = true;
            const pct = e.target.value / 100;
            updateTimelapsePreview(pct);
        });
        elements.timelapseSeek.addEventListener('change', (e) => {
            const pct = e.target.value / 100;
            performSeek(pct);
            seeking = false;
        });
        elements.timelapseSeek.addEventListener('pointerup', (e) => {
            if (seeking) {
                const pct = e.target.value / 100;
                performSeek(pct);
                seeking = false;
            }
        });
    }

    let timelapsePlayer = null;

    function openTimelapseModal() {
        if (state.timelapseEvents.length === 0 && !state.currentIllustId) {
            setStatus('タイムラプスデータがありません');
            return;
        }

        elements.timelapseOverlay.classList.add('active');

        if (state.timelapseEvents.length > 0) {
            playTimelapse(state.timelapseEvents);
        } else if (state.currentIllustId) {
            loadAndPlayTimelapse(state.currentIllustId);
        }
    }

    function closeTimelapseModal() {
        elements.timelapseOverlay.classList.remove('active');
        stopTimelapsePlayback();
    }

    async function loadAndPlayTimelapse(id) {
        setStatus('タイムラプスを読み込んでいます...');

        try {
            const resp = await fetch(`/admin/paint/api/timelapse.php?id=${encodeURIComponent(id)}`, {
                credentials: 'same-origin'
            });

            if (!resp.ok) {
                setStatus('タイムラプスの読み込みに失敗しました');
                return;
            }

            const ab = await resp.arrayBuffer();

            if (typeof pako !== 'undefined') {
                const uint8 = new Uint8Array(ab);
                const out = pako.ungzip(uint8, { to: 'string' });
                
                // Try to parse as JSON first, then CSV
                let events = null;
                try {
                    events = JSON.parse(out);
                } catch (jsonError) {
                    // Parse CSV format
                    console.log('Parsing timelapse as CSV format');
                    events = parseTimelapseCSV(out);
                }

                if (events && events.length > 0) {
                    playTimelapse(events);
                    setStatus('タイムラプス再生中');
                } else {
                    setStatus('タイムラプスデータが空です');
                }
            }
        } catch (e) {
            console.error('Timelapse load error:', e);
            setStatus('タイムラプスの読み込みに失敗しました');
        }
    }

    function parseTimelapseCSV(csvText) {
        const lines = csvText.trim().split(/\r\n|\n|\r/);
        if (lines.length < 2) return [];
        
        const headers = lines[0].split(',');
        const events = [];
        
        for (let i = 1; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;
            
            const values = parseCSVLine(line);
            if (values.length !== headers.length) continue;
            
            const event = {};
            headers.forEach((header, idx) => {
                let value = values[idx];
                
                // Convert numeric fields
                if (header === 't' || header === 'x' || header === 'y' || header === 'size' || header === 'layer') {
                    value = parseFloat(value) || 0;
                }
                
                event[header] = value;
            });
            
            events.push(event);
        }
        
        console.log(`Parsed ${events.length} timelapse events from CSV`);
        return events;
    }

    function parseCSVLine(line) {
        const result = [];
        let current = '';
        let inQuotes = false;
        
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            
            if (char === '"') {
                if (inQuotes && line[i + 1] === '"') {
                    current += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (char === ',' && !inQuotes) {
                result.push(current);
                current = '';
            } else {
                current += char;
            }
        }
        
        result.push(current);
        return result;
    }

    // Timelapse playback with pause/resume, precise seek, and chunked seek rendering
    let timelapseState = {
        events: null,
        idx: 0,
        startReal: 0,
        startT: 0,
        playing: false,
        paused: false,
        ignoreTime: false,
        speed: 1
    };

    function prepareEvents(events) {
        if (!events || events.length === 0) return [];
        // clone to avoid mutating original
        const evs = events.map(e => Object.assign({}, e));
        if (elements.timelapseIgnoreTime.checked) {
            // group into actions and assign equal intervals
            const actions = [];
            let current = null;
            evs.forEach(ev => {
                if (ev.type === 'start' || ev.type === 'fill') {
                    if (current) actions.push(current);
                    current = [ev];
                } else if (ev.type === 'move' && current) {
                    current.push(ev);
                } else if (ev.type === 'end' && current) {
                    current.push(ev);
                    actions.push(current);
                    current = null;
                }
            });
            if (current) actions.push(current);
            const interval = 500;
            evs.forEach(ev => {
                let ai = 0;
                for (let i = 0; i < actions.length; i++) {
                    if (actions[i].includes(ev)) { ai = i; break; }
                }
                ev._virtualT = ai * interval;
            });
            timelapseState.startT = 0;
            timelapseState.duration = (actions.length - 1) * interval;
        } else {
            timelapseState.startT = evs[0].t;
            timelapseState.duration = evs[evs.length - 1].t - timelapseState.startT;
            evs.forEach(ev => { ev._virtualT = ev.t; });
        }
        elements.timelapseTotalTime.textContent = formatTime((timelapseState.duration || 0) / 1000);
        return evs;
    }

    function playTimelapse(events) {
        if (!events || events.length === 0) return;

        // Initialize state
        stopTimelapsePlayback();
        timelapseState.events = prepareEvents(events);
        timelapseState.idx = 0;
        timelapseState.speed = parseFloat(elements.timelapseSpeed.value) || 1;
        timelapseState.ignoreTime = elements.timelapseIgnoreTime.checked;
        timelapseState.playing = true;
        timelapseState.paused = false;
        timelapseState.startReal = performance.now();

        // Clear canvas
        const canvas = elements.timelapseCanvas;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#FFFFFF'; ctx.fillRect(0, 0, canvas.width, canvas.height);

        // Animation frame loop
        function frame(now) {
            if (!timelapseState.playing || timelapseState.paused) return;
            const speed = timelapseState.speed;
            const startT = timelapseState.startT;
            const virtualT = startT + (now - timelapseState.startReal) * speed;

            const evs = timelapseState.events;
            const ctx = elements.timelapseCanvas.getContext('2d');

            while (timelapseState.idx < evs.length && evs[timelapseState.idx]._virtualT <= virtualT) {
                const ev = evs[timelapseState.idx++];
                if (ev.type === 'start') {
                    ctx.beginPath(); ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.lineWidth = ev.size || 4;
                    if (ev.tool === 'eraser') ctx.globalCompositeOperation = 'destination-out'; else { ctx.strokeStyle = ev.color || '#000'; ctx.globalCompositeOperation = 'source-over'; }
                    ctx.moveTo(ev.x, ev.y);
                } else if (ev.type === 'move') {
                    ctx.lineTo(ev.x, ev.y); ctx.stroke();
                }
            }

            // Update UI
            const elapsed = ((virtualT - startT) / 1000) || 0;
            elements.timelapseCurrentTime.textContent = formatTime(elapsed);
            elements.timelapseSeek.value = Math.floor((timelapseState.idx / (evs.length || 1)) * 100);

            if (timelapseState.idx < evs.length) {
                timelapsePlayer = requestAnimationFrame(frame);
            } else {
                timelapseState.playing = false;
                timelapsePlayer = null;
                elements.timelapsePlay.textContent = '▶️';
                setStatus('タイムラプス再生完了');
            }
        }

        timelapsePlayer = requestAnimationFrame(frame);
        elements.timelapsePlay.textContent = '⏸';
    }

    function toggleTimelapsePlayback() {
        // If nothing loaded, start playing from current source
        if (!timelapseState.events || timelapseState.events.length === 0) {
            if (state.timelapseEvents && state.timelapseEvents.length > 0) {
                playTimelapse(state.timelapseEvents);
            } else if (state.currentIllustId) {
                loadAndPlayTimelapse(state.currentIllustId);
            }
            return;
        }

        if (timelapseState.paused) {
            // resume
            timelapseState.paused = false;
            // adjust startReal so time continues from current index
            const idx = Math.max(0, Math.min(timelapseState.idx, timelapseState.events.length - 1));
            const currentVirtual = timelapseState.events[idx]?._virtualT || timelapseState.startT;
            timelapseState.startReal = performance.now() - (currentVirtual - timelapseState.startT) / (timelapseState.speed || 1);
            timelapsePlayer = requestAnimationFrame((t) => { /* kick frame */ timelapsePlayer = requestAnimationFrame(function f(now){ if (!timelapseState.playing) return; /* delegate to play loop */ timelapsePlayer = requestAnimationFrame(f); }); });
            elements.timelapsePlay.textContent = '⏸';
            setStatus('再開');
        } else {
            // pause
            timelapseState.paused = true;
            if (timelapsePlayer) {
                cancelAnimationFrame(timelapsePlayer);
                timelapsePlayer = null;
            }
            elements.timelapsePlay.textContent = '▶️';
            setStatus('一時停止');
        }
    }

    function stopTimelapsePlayback() {
        if (timelapsePlayer) {
            cancelAnimationFrame(timelapsePlayer);
            timelapsePlayer = null;
        }

        // reset state
        timelapseState.events = null;
        timelapseState.idx = 0;
        timelapseState.playing = false;
        timelapseState.paused = false;

        elements.timelapsePlay.textContent = '▶️';
        elements.timelapseSeek.value = 0;
        elements.timelapseCurrentTime.textContent = '0:00';

        // clear canvas
        const canvas = elements.timelapseCanvas;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#FFFFFF'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    function restartTimelapse() {
        stopTimelapsePlayback();

        if (state.timelapseEvents.length > 0) {
            playTimelapse(state.timelapseEvents);
        } else if (state.currentIllustId) {
            loadAndPlayTimelapse(state.currentIllustId);
        }
    }

    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    // ===== Save/Load =====
    function loadPersistedId() {
        try {
            const saved = localStorage.getItem('paint_current_illust_id');
            if (saved) {
                state.currentIllustId = saved;
                elements.illustId.textContent = saved;
            }
        } catch (e) {
            console.error('Failed to load persisted ID:', e);
        }
    }

    function setCurrentId(id) {
        state.currentIllustId = id;
        try {
            localStorage.setItem('paint_current_illust_id', id);
        } catch (e) {
            console.error('Failed to persist ID:', e);
        }
        elements.illustId.textContent = id;
    }

    async function saveIllust() {
        setStatus('保存中...');

        // Composite layers
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

        const dataUrl = offscreen.toDataURL('image/png');

        // Build illust data
        const illust = {
            version: '1.0',
            metadata: {
                canvas_width: w,
                canvas_height: h,
                background_color: '#FFFFFF'
            },
            layers: state.layers.map((canvas, idx) => ({
                id: `layer_${idx}`,
                name: LAYER_NAMES[idx] || `Layer ${idx}`,
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

        // Compress timelapse as CSV.gz using a Web Worker to avoid blocking the UI
        let timelapsePayload = null;
        if (state.timelapseEvents.length > 0) {
            try {
                if (window.Worker) {
                    // create worker lazily
                    if (!window._timelapseWorker) {
                        window._timelapseWorker = new Worker('/admin/paint/js/timelapse-worker.js');
                    }
                    timelapsePayload = await new Promise((resolve, reject) => {
                        const worker = window._timelapseWorker;
                        const onmsg = (ev) => {
                            worker.removeEventListener('message', onmsg);
                            if (ev.data && ev.data.success) resolve(ev.data.payload); else reject(ev.data && ev.data.error ? ev.data.error : 'worker error');
                        };
                        worker.addEventListener('message', onmsg);
                        worker.postMessage({ events: state.timelapseEvents });
                    });
                } else if (typeof pako !== 'undefined') {
                    // fallback to main-thread compression
                    const headers = [];
                    state.timelapseEvents.forEach(ev => { Object.keys(ev).forEach(k => { if (headers.indexOf(k) === -1) headers.push(k); }); });
                    const lines = [headers.join(',')];
                    state.timelapseEvents.forEach(ev => { const row = headers.map(h => { let v = ev[h]; if (v === undefined || v === null) return ''; if (Array.isArray(v) || typeof v === 'object') v = JSON.stringify(v); v = String(v); if (v.indexOf(',') !== -1 || v.indexOf('"') !== -1 || v.indexOf('\n') !== -1) { v = '"' + v.replace(/"/g, '""') + '"'; } return v; }); lines.push(row.join(',')); });
                    const csv = lines.join('\n');
                    const gz = pako.gzip(csv);
                    let bin = '';
                    for (let i = 0; i < gz.length; i++) bin += String.fromCharCode(gz[i]);
                    timelapsePayload = 'data:application/octet-stream;base64,' + btoa(bin);
                }
            } catch (err) {
                console.error('Timelapse compression error:', err);
            }
        }

        const payload = {
            title: 'Canvas Artwork',
            canvas_width: w,
            canvas_height: h,
            background_color: '#FFFFFF',
            illust_data: JSON.stringify(illust),
            image_data: dataUrl,
            timelapse_data: timelapsePayload,
            csrf_token: window.CSRF_TOKEN,
            id: state.currentIllustId
        };

        try {
            const res = await fetch('/admin/paint/api/save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });

            const json = await res.json();

            if (json.success) {
                setStatus(`保存完了 (ID: ${json.data.id})`);
                setCurrentId(json.data.id);

                // Clear timelapse events
                state.timelapseEvents = [];
            } else {
                setStatus(`保存失敗: ${json.error || 'unknown'}`);
            }
        } catch (e) {
            setStatus('保存エラー');
            console.error('Save error:', e);
        }
    }

    function newIllust() {
        if (!confirm('新規作成しますか？現在の作業内容は失われます。')) {
            return;
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
        state.undoStacks = [[], [], [], []];
        state.redoStacks = [[], [], [], []];
        state.timelapseEvents = [];
        state.currentIllustId = null;

        elements.illustId.textContent = '(未保存)';

        try {
            localStorage.removeItem('paint_current_illust_id');
        } catch (e) {
            console.error('Failed to clear persisted ID:', e);
        }

        renderLayers();
        setStatus('新規作成しました');
    }

    function clearCurrentLayer() {
        if (!confirm('現在のレイヤーをクリアしますか？')) {
            return;
        }

        pushUndo(state.activeLayer);

        const ctx = state.contexts[state.activeLayer];
        const canvas = state.layers[state.activeLayer];

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        setStatus('レイヤーをクリアしました');
    }

    // ===== Status Bar =====
    function setStatus(text) {
        elements.statusText.textContent = text;
    }

    function updateStatusBar() {
        const toolNames = {
            pen: 'ペン',
            eraser: '消しゴム',
            bucket: '塗りつぶし',
            eyedropper: 'スポイト'
        };

        elements.statusTool.textContent = `ツール: ${toolNames[state.currentTool] || state.currentTool}`;
        elements.statusLayer.textContent = `レイヤー: ${state.activeLayer} (${state.layerNames[state.activeLayer] || 'レイヤー ' + state.activeLayer})`;
    }

    // ===== Open Modal =====
    let selectedIllustId = null;

    function initOpenModal() {
        elements.btnOpen.addEventListener('click', openOpenModal);
        elements.openModalClose.addEventListener('click', closeOpenModal);
        elements.openModalCancel.addEventListener('click', closeOpenModal);
        elements.openModalLoad.addEventListener('click', loadSelectedIllust);

        elements.openModalOverlay.addEventListener('click', (e) => {
            if (e.target === elements.openModalOverlay) {
                closeOpenModal();
            }
        });
    }

    async function openOpenModal() {
        elements.openModalOverlay.classList.add('active');
        selectedIllustId = null;
        elements.openModalLoad.disabled = true;

        setStatus('イラスト一覧を読み込んでいます...');

        try {
            const resp = await fetch('/admin/paint/api/list.php', {
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

            setStatus('');
        } catch (e) {
            console.error('Failed to load illustration list:', e);
            setStatus('イラスト一覧の読み込みに失敗しました');
            elements.illustGrid.classList.add('hidden');
            elements.openModalEmpty.classList.remove('hidden');
        }
    }

    function renderIllustGrid(illusts) {
        elements.illustGrid.innerHTML = '';

        illusts.forEach(illust => {
            const item = document.createElement('div');
            item.className = 'illust-item';
            item.dataset.id = illust.id;

            // Thumbnail with proper fallback (preventing infinite loop)
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

                // Track which paths we've already tried to prevent infinite loop
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
                        // All attempts failed, show placeholder
                        thumbnail.style.display = 'none';
                        showPlaceholder();
                    }
                };

                thumbnail.onerror = () => {
                    tryLoad();
                };

                thumbnailWrap.appendChild(thumbnail);
                tryLoad(); // Start first attempt
            } else {
                // No image available - show placeholder immediately
                showPlaceholder();
            }

            const info = document.createElement('div');
            info.className = 'illust-info';

            const idEl = document.createElement('div');
            idEl.className = 'illust-id';
            idEl.textContent = `ID: ${illust.id}`;

            const dateEl = document.createElement('div');
            dateEl.className = 'illust-date';
            const date = new Date(illust.updated_at || illust.created_at);
            dateEl.textContent = date.toLocaleDateString('ja-JP');

            info.appendChild(idEl);
            info.appendChild(dateEl);

            item.appendChild(thumbnailWrap);
            item.appendChild(info);

            // Single click to select
            item.addEventListener('click', () => {
                selectIllust(illust.id);
            });

            // Double click to open immediately
            item.addEventListener('dblclick', () => {
                selectIllust(illust.id);
                loadSelectedIllust();
            });

            elements.illustGrid.appendChild(item);
        });
    }

    function selectIllust(id) {
        selectedIllustId = id;

        // Update UI
        elements.illustGrid.querySelectorAll('.illust-item').forEach(item => {
            item.classList.toggle('selected', item.dataset.id == id);
        });

        elements.openModalLoad.disabled = false;
    }

    function closeOpenModal() {
        elements.openModalOverlay.classList.remove('active');
        selectedIllustId = null;
    }

    async function loadSelectedIllust() {
        if (!selectedIllustId) {
            console.warn('loadSelectedIllust: No illustration selected');
            return;
        }

        // Store ID before closing modal (which resets selectedIllustId)
        const idToLoad = selectedIllustId;
        
        console.log('Loading illustration:', idToLoad);
        setStatus('イラストを読み込んでいます...');
        closeOpenModal();

        try {
            const resp = await fetch(`/admin/paint/api/load.php?id=${idToLoad}`, {
                credentials: 'same-origin'
            });

            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status}: ${resp.statusText}`);
            }

            const json = await resp.json();
            console.log('Load response:', json);

            if (!json.success || !json.data) {
                setStatus('イラストの読み込みに失敗しました: ' + (json.error || 'データがありません'));
                console.error('Load failed:', json);
                return;
            }

            const illust = json.data;

            // Parse illust_data
            let illustData = null;
            try {
                if (!illust.illust_data) {
                    throw new Error('illust_data is empty');
                }
                illustData = JSON.parse(illust.illust_data);
                console.log('Parsed illust_data:', illustData);
            } catch (e) {
                console.error('Failed to parse illust_data:', e, illust.illust_data);
                setStatus('イラストデータの解析に失敗しました');
                return;
            }

            // Clear current canvas and reset all layer properties
            state.layers.forEach((canvas, i) => {
                const ctx = state.contexts[i];
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                canvas.style.opacity = '1';
                canvas.style.display = 'block';
                state.layerNames[i] = `レイヤー ${i}`;
            });

            // Reset undo/redo stacks
            state.undoStacks = [[], [], [], []];
            state.redoStacks = [[], [], [], []];
            state.timelapseEvents = [];

            // Load layers
            if (illustData.layers && Array.isArray(illustData.layers)) {
                console.log('Loading', illustData.layers.length, 'layers');

                const loadPromises = illustData.layers.map((layerData, idx) => {
                    return new Promise((resolve, reject) => {
                        if (!layerData.data) {
                            console.log(`Layer ${idx}: no data, skipping`);
                            resolve();
                            return;
                        }

                        const img = new Image();
                        img.onload = () => {
                            // Use layer order from saved data if available
                            const targetIdx = layerData.order !== undefined ? layerData.order : idx;
                            
                            if (targetIdx < state.layers.length) {
                                const ctx = state.contexts[targetIdx];
                                ctx.clearRect(0, 0, state.layers[targetIdx].width, state.layers[targetIdx].height);
                                ctx.drawImage(img, 0, 0);

                                // Restore layer properties
                                state.layers[targetIdx].style.opacity = layerData.opacity !== undefined ? layerData.opacity.toString() : '1';
                                state.layers[targetIdx].style.display = layerData.visible !== false ? 'block' : 'none';

                                if (layerData.name) {
                                    state.layerNames[targetIdx] = layerData.name;
                                }

                                console.log(`Layer ${targetIdx} loaded: ${layerData.name || 'unnamed'}, visible: ${layerData.visible}, opacity: ${layerData.opacity}`);
                            }
                            resolve();
                        };
                        img.onerror = (e) => {
                            console.error(`Layer ${idx} image load error:`, e);
                            reject(new Error(`Failed to load layer ${idx} image`));
                        };
                        img.src = layerData.data;
                    });
                });

                await Promise.all(loadPromises);
                console.log('All layers loaded successfully');
            } else {
                console.warn('No layers found in illust_data');
            }

            // Set current ID
            setCurrentId(idToLoad);

            renderLayers();
            setStatus(`イラスト ID:${idToLoad} を読み込みました`);
            console.log('Illustration loaded successfully');

        } catch (e) {
            console.error('Failed to load illustration:', e);
            setStatus('イラストの読み込みに失敗しました: ' + e.message);
        }
    }

    // ===== Start Application =====
    init();

})();
