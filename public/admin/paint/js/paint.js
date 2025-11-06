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

    // ===== Configuration =====
    const CONFIG = {
        CANVAS: {
            DEFAULT_WIDTH: 512,
            DEFAULT_HEIGHT: 512,
            LAYER_COUNT: 4,
            MAX_LAYER_COUNT: 8,
            MAX_UNDO_STEPS: 50
        },
        COLORS: {
            DEFAULT: '#000000',
            PALETTE_SIZE: 16,
            DEFAULT_PALETTE: [
                '#000000', '#FFFFFF', '#FF0000', '#00FF00',
                '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF',
                '#800000', '#008000', '#000080', '#808000',
                '#800080', '#008080', '#C0C0C0', '#808080'
            ]
        },
        TOOLS: {
            DEFAULT: 'pen',
            PEN_SIZE: 4,
            ERASER_SIZE: 10,
            BUCKET_TOLERANCE: 32
        },
        LAYER_NAMES: ['背景', '下書き', '清書', '着色'],
        ZOOM: {
            DEFAULT_LEVEL: 1,
            MIN_LEVEL: 0.1,
            MAX_LEVEL: 5.0
        }
    };

    // ===== State =====
    const state = {
        // Canvas
        layers: [],
        contexts: [],
        activeLayer: 3,

        // Tools
        currentTool: CONFIG.TOOLS.DEFAULT,
        currentColor: CONFIG.COLORS.DEFAULT,
        penSize: CONFIG.TOOLS.PEN_SIZE,
        eraserSize: CONFIG.TOOLS.ERASER_SIZE,
        bucketTolerance: CONFIG.TOOLS.BUCKET_TOLERANCE,
        isDrawing: false,

        // History
        undoStacks: Array(CONFIG.CANVAS.LAYER_COUNT).fill().map(() => []),
        redoStacks: Array(CONFIG.CANVAS.LAYER_COUNT).fill().map(() => []),

        // Timelapse
        timelapseEvents: [],
        timelapseSnapshots: [], // { idx, t, data }
        lastSnapshotTime: 0,

        // Illust
        currentIllustId: null,
        currentIllustTitle: '',
        currentIllustDescription: '',
        currentIllustTags: '',
        hasUnsavedChanges: false,

        // View
        zoomLevel: CONFIG.ZOOM.DEFAULT_LEVEL,
        isPanning: false,
        panStart: { x: 0, y: 0 },
        panOffset: { x: 0, y: 0 },
        spaceKeyPressed: false,
        layerNames: [...CONFIG.LAYER_NAMES],
        
        // Initialization flag to prevent saving during restore
        isInitializing: true
    };

    // ===== DOM Elements =====
    const elements = {
        // Canvas
        canvasWrap: document.getElementById('canvas-wrap'),
        layers: Array.from(document.querySelectorAll('canvas.layer')),

        // Header buttons
        btnSave: document.getElementById('btn-save'),
        btnSaveAs: document.getElementById('btn-save-as'),
        btnTimelapse: document.getElementById('btn-timelapse'),
        btnNew: document.getElementById('btn-new'),
        btnClear: document.getElementById('btn-clear'),
        btnResize: document.getElementById('btn-resize'),
        illustId: document.getElementById('illust-id'),
        illustTitleDisplay: document.getElementById('illust-title-display'),

        // Tools
        toolBtns: document.querySelectorAll('.tool-btn[data-tool]'),
        toolUndo: document.getElementById('tool-undo'),
        toolRedo: document.getElementById('tool-redo'),
        toolZoomIn: document.getElementById('tool-zoom-in'),
        toolZoomOut: document.getElementById('tool-zoom-out'),
        toolZoomFit: document.getElementById('tool-zoom-fit'),
        toolRotateCW: document.getElementById('tool-rotate-cw'),
        toolRotateCCW: document.getElementById('tool-rotate-ccw'),
        toolFlipH: document.getElementById('tool-flip-h'),
        toolFlipV: document.getElementById('tool-flip-v'),

        // Color palette
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
        btnAddLayer: document.getElementById('btn-add-layer'),

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
        timelapseIgnoreTime: document.getElementById('timelapse-ignore-time'),

        // Resize modal
        resizeModalOverlay: document.getElementById('resize-modal-overlay'),
        resizeModalClose: document.getElementById('resize-modal-close'),
        resizeModalCancel: document.getElementById('resize-modal-cancel'),
        resizeModalApply: document.getElementById('resize-modal-apply'),
        resizeWidth: document.getElementById('resize-width'),
        resizeHeight: document.getElementById('resize-height'),
        resizeKeepRatio: document.getElementById('resize-keep-ratio'),

        // Edit Color modal
        editColorModalOverlay: document.getElementById('edit-color-modal-overlay'),
        editColorModalClose: document.getElementById('edit-color-modal-close'),
        editColorModalCancel: document.getElementById('edit-color-modal-cancel'),
        editColorModalSave: document.getElementById('edit-color-modal-save'),
        editColorInput: document.getElementById('edit-color-input'),

        // Save modal
        saveModalOverlay: document.getElementById('save-modal-overlay'),
        saveModalCancel: document.getElementById('save-modal-cancel'),
        saveModalSave: document.getElementById('save-modal-save'),
        saveTitle: document.getElementById('save-title'),
        saveDescription: document.getElementById('save-description'),
        saveTags: document.getElementById('save-tags'),
        editColorPreview: document.getElementById('edit-color-preview'),
        editRgbR: document.getElementById('edit-rgb-r'),
        editRgbG: document.getElementById('edit-rgb-g'),
        editRgbB: document.getElementById('edit-rgb-b'),
        editRgbRValue: document.getElementById('edit-rgb-r-value'),
        editRgbGValue: document.getElementById('edit-rgb-g-value'),
        editRgbBValue: document.getElementById('edit-rgb-b-value'),
        editHsvH: document.getElementById('edit-hsv-h'),
        editHsvS: document.getElementById('edit-hsv-s'),
        editHsvV: document.getElementById('edit-hsv-v'),
        editHsvHValue: document.getElementById('edit-hsv-h-value'),
        editHsvSValue: document.getElementById('edit-hsv-s-value'),
        editHsvVValue: document.getElementById('edit-hsv-v-value'),
        hsvSliders: document.getElementById('hsv-sliders'),
        rgbSliders: document.getElementById('rgb-sliders'),
        colorModeTabs: document.querySelectorAll('.color-mode-tab')
    };

    // ===== Initialization =====
    // ===== Initialization =====
    async function init() {
        console.log('🚀🚀🚀 init() CALLED! 🚀🚀🚀');
        await initCanvas();
        initUI();

        // Warn on page unload if there are unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (state.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = ''; // Modern browsers require this
            }
        });

        // Mark initialization as complete - now auto-save can work
        state.isInitializing = false;
        console.log('Initialization complete, auto-save enabled');
        setStatus('準備完了');
    }

    async function initCanvas() {
        console.log('=== Initializing canvas ===');
        
        // Initialize contexts
        state.layers = elements.layers;
        state.contexts = state.layers.map(canvas => canvas.getContext('2d', { willReadFrequently: true }));

        console.log('Canvas layers found:', state.layers.length);
        console.log('Canvas contexts created:', state.contexts.length);
        
        // Verify canvas elements
        state.layers.forEach((canvas, i) => {
            console.log(`Layer ${i}: ${canvas.width}x${canvas.height}, visible: ${canvas.style.display !== 'none'}`);
        });

        // Set layer z-indexes
        state.layers.forEach((canvas, i) => {
            canvas.style.zIndex = i;
        });

        // Load persisted state (ID and canvas data)
        await loadPersistedState();
        
        console.log('=== Canvas initialization complete ===');
    }

    function initUI() {
        // Color and tools
        initColorPalette();
        initCurrentColorEdit();

        // Layers
        initLayers();
        initLayerContextMenu();

        // Tools and interactions
        initToolListeners();
        initCanvasListeners();
        initKeyboardShortcuts();
        initCanvasPan();

        // Modals
        initTimelapseModal();
        initOpenModal();
        initResizeModal();
        initTransformTools();
        initEditColorModal();
        initSaveModal();

        // Load data
        loadColorPalette();
    }

    // ===== Color Palette =====
    function initColorPalette() {
        // Generate 16-color palette (will be populated from DB)
        for (let i = 0; i < CONFIG.COLORS.PALETTE_SIZE; i++) {
            const swatch = document.createElement('div');
            swatch.className = 'color-swatch';
            swatch.style.background = CONFIG.COLORS.DEFAULT_PALETTE[i];
            swatch.title = CONFIG.COLORS.DEFAULT_PALETTE[i] + ' (ダブルクリックで編集)';
            swatch.dataset.slotIndex = i;
            
            let clickTimer = null;
            
            // Single click to select (with delay to avoid conflict with double-click)
            swatch.addEventListener('click', (e) => {
                if (e.detail === 1) {
                    clickTimer = setTimeout(() => {
                        setColor(swatch.style.background);
                    }, 200);
                }
            });
            
            // Double click to edit - open color picker
            swatch.addEventListener('dblclick', ((index, element) => {
                return (e) => {
                    e.preventDefault();
                    clearTimeout(clickTimer);
                    editPaletteColor(index, element);
                };
            })(i, swatch));
            
            elements.colorPaletteGrid.appendChild(swatch);
        }
    }

    // Edit palette color with native color picker
    let activeColorPicker = null;

    function editPaletteColor(slotIndex, swatchElement) {
        // Close any existing color picker
        if (activeColorPicker && document.body.contains(activeColorPicker)) {
            document.body.removeChild(activeColorPicker);
            activeColorPicker = null;
        }

        // Get current color and normalize to hex
        let currentColor = swatchElement.style.background;
        if (currentColor.startsWith('rgb')) {
            const rgb = currentColor.match(/\d+/g);
            if (rgb && rgb.length >= 3) {
                currentColor = '#' + rgb.map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
            }
        }
        
        // Store the original current color to restore later
        const originalColor = state.currentColor;
        
        // Temporarily set the palette color as current color
        setColor(currentColor);
        
        // Get the EDIT button and trigger it programmatically
        const editBtn = document.getElementById('current-color-edit-btn');
        if (!editBtn) {
            console.error('EDIT button not found');
            return;
        }
        
        // Create a one-time listener to handle the color change
        const handlePaletteColorChange = async () => {
            const newColor = state.currentColor;
            
            // Update the swatch
            swatchElement.style.background = newColor;
            swatchElement.title = newColor + ' (ダブルクリックで編集)';
            
            // Save to server
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) {
                    console.error('CSRF token not found');
                    return;
                }

                const response = await fetch('/admin/paint/api/palette.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        slot_index: slotIndex,
                        color: newColor
                    })
                });

                const result = await response.json();
                if (!result.success) {
                    console.error('Failed to save color:', result.error);
                    swatchElement.style.background = currentColor;
                    swatchElement.title = currentColor + ' (ダブルクリックで編集)';
                    alert('色の保存に失敗しました: ' + (result.error || ''));
                }
            } catch (error) {
                console.error('Error saving color:', error);
                swatchElement.style.background = currentColor;
                swatchElement.title = currentColor + ' (ダブルクリックで編集)';
                alert('色の保存中にエラーが発生しました');
            }
        };
        
        // Store the handler for cleanup
        swatchElement._paletteChangeHandler = handlePaletteColorChange;
        
        // Click the EDIT button
        editBtn.click();
        
        // Wait for color picker to close, then handle the change
        // We'll use a MutationObserver or polling to detect when the color picker closes
        const checkInterval = setInterval(() => {
            // Check if there's no active color input (picker closed)
            const colorInputs = document.querySelectorAll('input[type="color"]');
            if (colorInputs.length === 0) {
                clearInterval(checkInterval);
                
                // Only update if color actually changed
                if (state.currentColor !== currentColor) {
                    handlePaletteColorChange();
                } else {
                    // Restore original color if user cancelled
                    setColor(originalColor);
                }
            }
        }, 100);
    }

    function setColor(color) {
        // Normalize color format
        if (color.startsWith('rgb')) {
            // Convert rgb to hex
            const rgb = color.match(/\d+/g);
            color = '#' + rgb.map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
        }
        
        state.currentColor = color;
        elements.currentColor.style.background = color;
        elements.currentColorHex.textContent = color.toUpperCase();
        
        // Update RGB display
        const r = parseInt(color.slice(1, 3), 16);
        const g = parseInt(color.slice(3, 5), 16);
        const b = parseInt(color.slice(5, 7), 16);
        const rgbDisplay = document.getElementById('current-color-rgb');
        if (rgbDisplay) {
            rgbDisplay.textContent = `RGB(${r}, ${g}, ${b})`;
        }
        
        // Removed savePersistedState() call - too frequent during initialization
    }

    // Edit current color with color picker
    function initCurrentColorEdit() {
        const editBtn = document.getElementById('current-color-edit-btn');
        if (!editBtn) return;

        editBtn.addEventListener('click', (e) => {
            // Get the right panel position
            const rightPanel = document.querySelector('.right-panel');
            const panelRect = rightPanel ? rightPanel.getBoundingClientRect() : null;
            
            // Calculate position (left of the right panel)
            const inputRight = panelRect ? (window.innerWidth - panelRect.left + 10) : 320;
            const inputTop = panelRect ? panelRect.top + 50 : 100;
            
            // Create a small visible color input positioned left of the right panel
            const tempInput = document.createElement('input');
            tempInput.type = 'color';
            tempInput.value = state.currentColor;
            tempInput.style.position = 'fixed';
            tempInput.style.top = inputTop + 'px';
            tempInput.style.right = inputRight + 'px';
            tempInput.style.width = '40px';
            tempInput.style.height = '40px';
            tempInput.style.border = 'none';
            tempInput.style.padding = '0';
            tempInput.style.opacity = '0.01'; // Nearly invisible but browser can detect position
            tempInput.style.cursor = 'pointer';
            tempInput.style.zIndex = '9999';
            
            document.body.appendChild(tempInput);

            // Auto-click to open the picker dialog
            setTimeout(() => {
                tempInput.click();
            }, 10);

            // Handle color change
            const handleChange = (e) => {
                setColor(e.target.value);
                cleanup();
            };

            // Handle cancel/close
            const handleClose = () => {
                setTimeout(() => {
                    cleanup();
                }, 150);
            };

            // Cleanup function
            const cleanup = () => {
                if (tempInput && document.body.contains(tempInput)) {
                    tempInput.removeEventListener('change', handleChange);
                    tempInput.removeEventListener('blur', handleClose);
                    document.body.removeChild(tempInput);
                }
            };

            tempInput.addEventListener('change', handleChange);
            tempInput.addEventListener('blur', handleClose);
        });
    }

    // ===== Layers =====
    function initLayers() {
        // Add layer button
        if (elements.btnAddLayer) {
            elements.btnAddLayer.addEventListener('click', addLayer);
        }
        
        renderLayers();
    }

    function renderLayers() {
        elements.layersList.innerHTML = '';

        // Render in reverse order (top layer first)
        for (let i = state.layers.length - 1; i >= 0; i--) {
            // Use IIFE to capture the current index for all event handlers
            ((layerIndex) => {
                const layer = state.layers[layerIndex];
                const layerItem = document.createElement('div');
                layerItem.className = 'layer-item' + (layerIndex === state.activeLayer ? ' active' : '');
                layerItem.dataset.layer = layerIndex;

                // === Row 1: Visibility + Layer Name + Edit Button ===
                const row1 = document.createElement('div');
                row1.className = 'layer-row layer-row-1';
                row1.style.display = 'flex';
                row1.style.alignItems = 'center';
                row1.style.gap = '8px';
                row1.style.marginBottom = '4px';

                // Visibility toggle
                const visibility = document.createElement('span');
                visibility.className = 'layer-visibility';
                visibility.textContent = layer.style.display === 'none' ? '👁️‍🗨️' : '👁️';
                visibility.style.cursor = 'pointer';
                visibility.style.fontSize = '18px';
                visibility.style.userSelect = 'none';
                visibility.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleLayerVisibility(layerIndex);
                });

                // Layer name container (for display and edit)
                const nameContainer = document.createElement('div');
                nameContainer.style.flex = '1';
                nameContainer.style.position = 'relative';
                
                const nameDisplay = document.createElement('span');
                nameDisplay.className = 'layer-name-display';
                nameDisplay.textContent = state.layerNames[layerIndex] || `レイヤー ${layerIndex}`;
                nameDisplay.style.cursor = 'pointer';
                nameDisplay.addEventListener('click', (e) => {
                    e.stopPropagation();
                    setActiveLayer(layerIndex);
                });
                nameContainer.appendChild(nameDisplay);
                
                const nameInput = document.createElement('input');
                nameInput.type = 'text';
                nameInput.className = 'layer-name-input';
                nameInput.style.display = 'none';
                nameInput.style.width = '100%';
                nameInput.style.fontSize = '13px';
                nameInput.style.padding = '2px 4px';
                nameInput.style.border = '1px solid var(--accent-primary)';
                nameInput.style.borderRadius = '3px';
                nameInput.value = state.layerNames[layerIndex] || `レイヤー ${layerIndex}`;
                nameContainer.appendChild(nameInput);
                
                const startEditing = () => {
                    nameDisplay.style.display = 'none';
                    nameInput.style.display = 'block';
                    nameInput.focus();
                    nameInput.select();
                };
                
                const stopEditing = () => {
                    const newName = nameInput.value.trim();
                    if (newName && newName !== state.layerNames[layerIndex]) {
                        state.layerNames[layerIndex] = newName;
                        nameDisplay.textContent = newName;
                        updateStatusBar();
                    }
                    nameDisplay.style.display = 'block';
                    nameInput.style.display = 'none';
                    nameInput.value = state.layerNames[layerIndex] || `レイヤー ${layerIndex}`;
                };
                
                nameInput.addEventListener('blur', stopEditing);
                nameInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        stopEditing();
                    } else if (e.key === 'Escape') {
                        nameInput.value = state.layerNames[layerIndex] || `レイヤー ${layerIndex}`;
                        stopEditing();
                    }
                });

                // Edit button
                const editBtn = document.createElement('button');
                editBtn.className = 'layer-edit-btn';
                editBtn.textContent = '✎';
                editBtn.title = 'レイヤー名を編集';
                editBtn.style.padding = '4px 8px';
                editBtn.style.fontSize = '14px';
                editBtn.style.border = '1px solid var(--border-color)';
                editBtn.style.borderRadius = '6px';
                editBtn.style.background = 'white';
                editBtn.style.cursor = 'pointer';
                editBtn.style.minWidth = '32px';
                editBtn.style.display = 'flex';
                editBtn.style.alignItems = 'center';
                editBtn.style.justifyContent = 'center';
                editBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    startEditing();
                });

                row1.appendChild(visibility);
                row1.appendChild(nameContainer);
                row1.appendChild(editBtn);

                // === Row 2: Spacer + Opacity Slider ===
                const row2 = document.createElement('div');
                row2.className = 'layer-row layer-row-2';
                row2.style.display = 'flex';
                row2.style.alignItems = 'center';
                row2.style.gap = '8px';
                row2.style.marginBottom = '4px';

                const spacer1 = document.createElement('div');
                spacer1.style.width = '20px';
                spacer1.style.flexShrink = '0';

                const opacityLabel = document.createElement('span');
                opacityLabel.textContent = '不透明度:';
                opacityLabel.style.fontSize = '11px';
                opacityLabel.style.color = '#666';
                opacityLabel.style.flexShrink = '0';

                const opacity = document.createElement('input');
                opacity.type = 'range';
                opacity.className = 'layer-opacity';
                opacity.min = 0;
                opacity.max = 100;
                opacity.value = parseInt((parseFloat(layer.style.opacity || '1') * 100).toString());
                opacity.style.flex = '1';
                opacity.addEventListener('input', (e) => {
                    e.stopPropagation();
                    setLayerOpacity(layerIndex, parseInt(e.target.value) / 100);
                });

                const opacityValue = document.createElement('span');
                opacityValue.className = 'layer-opacity-value';
                opacityValue.textContent = opacity.value + '%';
                opacityValue.style.fontSize = '11px';
                opacityValue.style.color = '#666';
                opacityValue.style.minWidth = '40px';
                opacityValue.style.width = '40px';
                opacityValue.style.textAlign = 'right';
                opacityValue.style.flexShrink = '0';
                opacity.addEventListener('input', (e) => {
                    opacityValue.textContent = e.target.value + '%';
                });

                row2.appendChild(spacer1);
                row2.appendChild(opacityLabel);
                row2.appendChild(opacity);
                row2.appendChild(opacityValue);

                // === Row 3: Spacer + Layer Menu Button ===
                const row3 = document.createElement('div');
                row3.className = 'layer-row layer-row-3';
                row3.style.display = 'flex';
                row3.style.alignItems = 'center';
                row3.style.gap = '8px';

                const spacer2 = document.createElement('div');
                spacer2.style.width = '20px';
                spacer2.style.flexShrink = '0';

                const menuBtn = document.createElement('button');
                menuBtn.className = 'layer-menu-btn';
                menuBtn.textContent = '⋮';
                menuBtn.title = 'レイヤーメニュー';
                menuBtn.style.flex = '1';
                menuBtn.style.padding = '6px';
                menuBtn.style.fontSize = '18px';
                menuBtn.style.fontWeight = 'bold';
                menuBtn.style.border = '1px solid var(--border-color)';
                menuBtn.style.borderRadius = '6px';
                menuBtn.style.background = 'white';
                menuBtn.style.cursor = 'pointer';
                menuBtn.style.textAlign = 'center';
                menuBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const rect = menuBtn.getBoundingClientRect();
                    showLayerContextMenu(rect.left, rect.bottom, layerIndex);
                });

                // Move buttons
                const moveControls = document.createElement('div');
                moveControls.style.display = 'flex';
                moveControls.style.gap = '4px';

                const upBtn = document.createElement('button');
                upBtn.className = 'layer-control-btn';
                upBtn.textContent = '↑';
                upBtn.title = '上へ';
                upBtn.style.width = '32px';
                upBtn.style.height = '32px';
                upBtn.style.border = '1px solid var(--border-color)';
                upBtn.style.borderRadius = '6px';
                upBtn.style.background = 'white';
                upBtn.style.cursor = 'pointer';
                upBtn.style.fontSize = '14px';
                upBtn.style.display = 'flex';
                upBtn.style.alignItems = 'center';
                upBtn.style.justifyContent = 'center';
                upBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    moveLayer(layerIndex, -1);
                });

                const downBtn = document.createElement('button');
                downBtn.className = 'layer-control-btn';
                downBtn.textContent = '↓';
                downBtn.title = '下へ';
                downBtn.style.width = '32px';
                downBtn.style.height = '32px';
                downBtn.style.border = '1px solid var(--border-color)';
                downBtn.style.borderRadius = '6px';
                downBtn.style.background = 'white';
                downBtn.style.cursor = 'pointer';
                downBtn.style.fontSize = '14px';
                downBtn.style.display = 'flex';
                downBtn.style.alignItems = 'center';
                downBtn.style.justifyContent = 'center';
                downBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    moveLayer(layerIndex, 1);
                });

                moveControls.appendChild(upBtn);
                moveControls.appendChild(downBtn);

                row3.appendChild(spacer2);
                row3.appendChild(menuBtn);
                row3.appendChild(moveControls);

                // Assemble layer item
                layerItem.appendChild(row1);
                layerItem.appendChild(row2);
                layerItem.appendChild(row3);

                // Click on layer item to select (unless clicking on interactive elements)
                layerItem.addEventListener('click', (e) => {
                    // Ignore clicks on buttons, inputs, and sliders
                    if (e.target.tagName === 'BUTTON' || 
                        e.target.tagName === 'INPUT' || 
                        e.target.className.includes('layer-visibility') ||
                        e.target.className.includes('layer-menu-btn')) {
                        return;
                    }
                    setActiveLayer(layerIndex);
                });

                elements.layersList.appendChild(layerItem);
            })(i);
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
                    case 'delete':
                        removeLayer(contextMenuTargetLayer);
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
        // Removed savePersistedState() - too frequent
    }

    function setLayerOpacity(index, opacity) {
        state.layers[index].style.opacity = opacity.toString();
        // Removed savePersistedState() - too frequent
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
        [state.layerNames[index], state.layerNames[to]] = [state.layerNames[to], state.layerNames[index]];

        // Update active layer index if it was moved
        if (state.activeLayer === index) {
            state.activeLayer = to;
        } else if (state.activeLayer === to) {
            state.activeLayer = index;
        }

        // Update z-index
        state.layers.forEach((canvas, i) => {
            canvas.style.zIndex = i;
        });

        renderLayers();
        // Removed savePersistedState() - layer operations are not critical for persistence
    }

    function addLayer() {
        if (state.layers.length >= CONFIG.CANVAS.MAX_LAYER_COUNT) {
            setStatus('レイヤー数の上限に達しました');
            return;
        }

        // Create new canvas element
        const canvas = document.createElement('canvas');
        canvas.className = 'layer';
        canvas.width = state.layers[0].width;
        canvas.height = state.layers[0].height;
        canvas.style.width = `${state.layers[0].width}px`;
        canvas.style.height = `${state.layers[0].height}px`;
        canvas.style.zIndex = state.layers.length;

        // Add to DOM (before the canvas-wrap)
        const canvasWrap = elements.canvasWrap;
        canvasWrap.appendChild(canvas);

        // Add to state arrays
        state.layers.push(canvas);
        state.contexts.push(canvas.getContext('2d', { willReadFrequently: true }));
        state.undoStacks.push([]);
        state.redoStacks.push([]);
        state.layerNames.push(`レイヤー ${state.layers.length - 1}`);

        // Set active layer to the new one
        setActiveLayer(state.layers.length - 1);

        renderLayers();
        setStatus(`新規レイヤー ${state.layers.length - 1} を追加しました`);
    }

    function removeLayer(layerIndex) {
        if (state.layers.length <= 1) {
            setStatus('最後のレイヤーは削除できません');
            return;
        }

        // Remove from DOM
        const canvas = state.layers[layerIndex];
        canvas.remove();

        // Remove from state arrays
        state.layers.splice(layerIndex, 1);
        state.contexts.splice(layerIndex, 1);
        state.undoStacks.splice(layerIndex, 1);
        state.redoStacks.splice(layerIndex, 1);
        state.layerNames.splice(layerIndex, 1);

        // Update z-index
        state.layers.forEach((canvas, i) => {
            canvas.style.zIndex = i;
        });

        // Adjust active layer if necessary
        if (state.activeLayer >= state.layers.length) {
            state.activeLayer = state.layers.length - 1;
        }

        renderLayers();
        setStatus(`レイヤー ${layerIndex} を削除しました`);
    }

    // ===== Tools =====
    function initToolListeners() {
        initToolButtons();
        initToolSettings();
        initHeaderButtons();
    }

    function initToolButtons() {
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
    }

    function initToolSettings() {
        // Tool settings
        elements.penSize.addEventListener('input', (e) => {
            state.penSize = parseInt(e.target.value);
            elements.penSizeValue.textContent = state.penSize;
            // Removed savePersistedState() - tool settings are not critical
        });

        elements.eraserSize.addEventListener('input', (e) => {
            state.eraserSize = parseInt(e.target.value);
            elements.eraserSizeValue.textContent = state.eraserSize;
            // Removed savePersistedState() - tool settings are not critical
        });

        elements.bucketTolerance.addEventListener('input', (e) => {
            state.bucketTolerance = parseInt(e.target.value);
            elements.bucketToleranceValue.textContent = state.bucketTolerance;
            // Removed savePersistedState() - tool settings are not critical
        });
    }

    function initHeaderButtons() {
        // Header buttons
        elements.btnSave.addEventListener('click', handleSaveButton);
        elements.btnSaveAs.addEventListener('click', openSaveModal);
        elements.btnTimelapse.addEventListener('click', openTimelapseModal);
        elements.btnNew.addEventListener('click', newIllust);
        elements.btnClear.addEventListener('click', clearCurrentLayer);
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

        // Use the currently active layer, not the clicked canvas layer
        // This allows drawing on the active layer even when clicking on top layers
        const drawLayerIndex = state.activeLayer;
        const pos = getPointerPos(e, state.layers[drawLayerIndex]);
        const ctx = state.contexts[drawLayerIndex];

        if (state.currentTool === 'eyedropper') {
            pickColor(drawLayerIndex, pos);
            return;
        }

        if (state.currentTool === 'bucket') {
            floodFill(drawLayerIndex, pos);
            return;
        }

        // Save undo state
        pushUndo(drawLayerIndex);

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
            layer: drawLayerIndex,
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

        // Auto-save canvas state
        savePersistedState();

        // Mark as changed
        markAsChanged();
    }

    function getPointerPos(e, canvas) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.clientX ?? e.touches?.[0]?.clientX ?? 0;
        const clientY = e.clientY ?? e.touches?.[0]?.clientY ?? 0;

        // Get position relative to the bounding rect
        const x = clientX - rect.left;
        const y = clientY - rect.top;

        // The canvas is scaled and translated by canvas-wrap
        // Transform: scale(zoom) translate(panOffset.x/zoom, panOffset.y/zoom)
        // To get canvas pixel coordinates, we need to reverse this:
        // 1. Undo the scale: divide by zoom
        // 2. Undo the translate: subtract the pan offset
        
        const canvasX = (x / state.zoomLevel) - (state.panOffset.x / state.zoomLevel);
        const canvasY = (y / state.zoomLevel) - (state.panOffset.y / state.zoomLevel);

        return {
            x: canvasX,
            y: canvasY
        };
    }

    // ===== Drawing Tools =====
    function pickColor(layerIndex, pos) {
        const ctx = state.contexts[layerIndex];
        const imageData = ctx.getImageData(pos.x, pos.y, 1, 1);
        const [r, g, b] = imageData.data;

        const hex = ColorUtils.rgbToHex(r, g, b);

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
        const fillColor = ColorUtils.hexToRgb(state.currentColor);

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

        markAsChanged();
        setStatus('塗りつぶし完了');
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

        if (state.undoStacks[layerIndex].length > CONFIG.CANVAS.MAX_UNDO_STEPS) {
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

        // 再生状態を保存
        const wasPlaying = timelapseState.playing && !timelapseState.paused;

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
                
                // シーク後に再生状態を復元
                if (wasPlaying && timelapseState.events) {
                    timelapseState.idx = targetIdx;
                    const virtualT = timelapseState.events[targetIdx]?._virtualT || timelapseState.startT;
                    timelapseState.startReal = performance.now();
                    timelapseState.startT = virtualT;
                }
            };
            img.src = snap.data;
        } else {
            // no snapshot, replay from 0
            for (let i = 0; i <= targetIdx; i++) {
                applyTimelapseEventToCanvas(ctx, state.timelapseEvents[i]);
            }
            elements.timelapseSeek.value = Math.floor(((targetIdx+1) / (total || 1)) * 100);
            
            // シーク後に再生状態を復元
            if (wasPlaying && timelapseState.events) {
                timelapseState.idx = targetIdx;
                const virtualT = timelapseState.events[targetIdx]?._virtualT || timelapseState.startT;
                timelapseState.startReal = performance.now();
                timelapseState.startT = virtualT;
            }
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
            // 速度を即座に反映
            if (timelapseState.playing && !timelapseState.paused) {
                const newSpeed = parseFloat(e.target.value) || 1;
                const oldSpeed = timelapseState.speed;
                
                // 現在の仮想時間を計算
                const now = performance.now();
                const elapsedReal = now - timelapseState.startReal;
                const virtualT = timelapseState.startT + elapsedReal * oldSpeed;
                
                // 新しい速度で再計算
                timelapseState.speed = newSpeed;
                timelapseState.startReal = now;
                timelapseState.startT = virtualT;
            } else {
                timelapseState.speed = parseFloat(e.target.value) || 1;
            }
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
    async function loadPersistedState() {
        console.log('🔥🔥🔥 loadPersistedState() CALLED! 🔥🔥🔥');
        try {
            console.log('=== Starting persisted state restoration ===');
            
            // Debug: Check localStorage availability
            console.log('localStorage available:', typeof localStorage !== 'undefined');
            console.log('localStorage length:', localStorage.length);
            
            // List all localStorage keys for debugging
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('paint_')) {
                    console.log(`Found localStorage key: ${key}`);
                }
            }
            
            // Load current illust ID
            const savedId = localStorage.getItem('paint_current_illust_id');
            if (savedId) {
                state.currentIllustId = savedId;
                elements.illustId.textContent = savedId;
                console.log('✓ Loaded persisted ID:', savedId);
            } else {
                console.log('- No saved illust ID found');
            }

            // Load canvas state from localStorage (simple approach)
            const savedState = localStorage.getItem('paint_canvas_state');
            if (savedState) {
                const sizeKB = (savedState.length / 1024).toFixed(2);
                console.log(`✓ Found saved canvas state (${sizeKB} KB), parsing...`);
                const canvasState = JSON.parse(savedState);
                console.log('✓ Canvas state parsed, layers:', canvasState.layers?.length || 0);
                await restoreCanvasState(canvasState);
                setStatus('以前の作業を復元しました');
                console.log('=== Persisted state restoration complete ===');
            } else {
                console.log('- No saved canvas state found');
                console.log('=== Persisted state restoration skipped ===');
            }
        } catch (e) {
            console.error('✗ Failed to load persisted state:', e);
            console.error('Error details:', e.message, e.stack);
        }
    }

    function savePersistedState() {
        // Don't save during initialization - we're restoring state
        if (state.isInitializing) {
            console.log('Skipping save during initialization');
            return;
        }
        
        try {
            const canvasState = captureCanvasState();
            const stateJson = JSON.stringify(canvasState);
            const sizeKB = (stateJson.length / 1024).toFixed(2);
            console.log(`Saving canvas state (${sizeKB} KB)...`);
            localStorage.setItem('paint_canvas_state', stateJson);
            console.log('Canvas state saved to localStorage successfully');
        } catch (e) {
            console.error('Failed to save persisted state:', e);
            if (e.name === 'QuotaExceededError') {
                console.error('LocalStorage quota exceeded! Canvas state is too large.');
                alert('警告: キャンバス状態が大きすぎて保存できません。ブラウザのローカルストレージ制限を超えています。');
            }
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

    function captureCanvasState() {
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
            timestamp: Date.now()
        };
    }

    function restoreCanvasState(canvasState) {
        return new Promise((resolve) => {
            try {
                console.log('  → Starting canvas state restoration');
                console.log('  → Canvas size:', canvasState.canvasWidth, 'x', canvasState.canvasHeight);

                // Store canvas size for later
                const targetWidth = canvasState.canvasWidth || state.layers[0].width;
                const targetHeight = canvasState.canvasHeight || state.layers[0].height;

                // **CRITICAL: Resize canvas FIRST before loading images**
                if (targetWidth !== state.layers[0].width || targetHeight !== state.layers[0].height) {
                    console.log('  → Resizing canvas to', targetWidth, 'x', targetHeight, 'BEFORE restoring images');
                    
                    // Resize all canvases without trying to preserve content (we'll load it next)
                    state.layers.forEach((canvas, idx) => {
                        canvas.width = targetWidth;
                        canvas.height = targetHeight;
                        canvas.style.width = `${targetWidth}px`;
                        canvas.style.height = `${targetHeight}px`;
                        console.log('    - Layer', idx, 'resized');
                    });
                    
                    // Update canvas-wrap container size
                    if (elements.canvasWrap) {
                        elements.canvasWrap.style.width = `${targetWidth}px`;
                        elements.canvasWrap.style.height = `${targetHeight}px`;
                    }
                    
                    // Update canvas info
                    const canvasInfo = document.querySelector('.canvas-info');
                    if (canvasInfo) {
                        canvasInfo.textContent = `${targetWidth} x ${targetHeight} px`;
                    }
                    
                    // Update timelapse canvas
                    if (elements.timelapseCanvas) {
                        elements.timelapseCanvas.width = targetWidth;
                        elements.timelapseCanvas.height = targetHeight;
                    }
                }

                // Restore layer data
                if (canvasState.layers && canvasState.layers.length > 0) {
                    console.log('  → Restoring', canvasState.layers.length, 'layers...');
                    const loadPromises = canvasState.layers.map(layerInfo => {
                        return new Promise((resolveLayer) => {
                            if (layerInfo.index < state.layers.length) {
                                const canvas = state.layers[layerInfo.index];
                                console.log('    - Layer', layerInfo.index, '- visible:', layerInfo.visible, 'opacity:', layerInfo.opacity);

                                const img = new Image();
                                img.onload = () => {
                                    console.log('    ✓ Layer', layerInfo.index, 'image loaded, drawing to canvas...');
                                    const ctx = canvas.getContext('2d');
                                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                                    ctx.drawImage(img, 0, 0);
                                    console.log('    ✓ Layer', layerInfo.index, 'drawn successfully');
                                    resolveLayer();
                                };
                                img.onerror = (err) => {
                                    console.error('    ✗ Failed to load image for layer', layerInfo.index, err);
                                    resolveLayer(); // Resolve anyway to not block other layers
                                };
                                img.src = layerInfo.dataUrl;

                                // Restore visibility and opacity immediately
                                canvas.style.display = layerInfo.visible ? 'block' : 'none';
                                canvas.style.opacity = layerInfo.opacity;
                            } else {
                                console.warn('    ! Layer index', layerInfo.index, 'out of range, skipping');
                                resolveLayer();
                            }
                        });
                    });

                    // Wait for all images to load, then restore other state
                    Promise.all(loadPromises).then(() => {
                        console.log('  ✓ All layer images loaded');

                        // Restore other state
                        if (canvasState.activeLayer !== undefined) {
                            console.log('  → Restoring active layer:', canvasState.activeLayer);
                            state.activeLayer = canvasState.activeLayer;
                            setActiveLayer(canvasState.activeLayer);
                        }
                        if (canvasState.currentColor) {
                            console.log('  → Restoring color:', canvasState.currentColor);
                            state.currentColor = canvasState.currentColor;
                            // Update color display in UI
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
                            console.log('  → Restoring zoom level:', canvasState.zoomLevel);
                            state.zoomLevel = canvasState.zoomLevel;
                            applyZoom();
                        }
                        if (canvasState.panOffset) {
                            console.log('  → Restoring pan offset:', canvasState.panOffset);
                            state.panOffset = canvasState.panOffset;
                            applyZoom(); // Pan is also applied through applyZoom
                        }
                        if (canvasState.layerNames) {
                            state.layerNames = canvasState.layerNames;
                        }

                        // Restore timelapse events
                        if (canvasState.timelapseEvents && Array.isArray(canvasState.timelapseEvents)) {
                            console.log('  → Restoring', canvasState.timelapseEvents.length, 'timelapse events');
                            state.timelapseEvents = canvasState.timelapseEvents;
                        }
                        if (canvasState.timelapseSnapshots && Array.isArray(canvasState.timelapseSnapshots)) {
                            console.log('  → Restoring', canvasState.timelapseSnapshots.length, 'timelapse snapshots');
                            state.timelapseSnapshots = canvasState.timelapseSnapshots;
                        }
                        if (canvasState.lastSnapshotTime !== undefined) {
                            state.lastSnapshotTime = canvasState.lastSnapshotTime;
                        }

                        // Re-render layers UI
                        renderLayers();

                        // Update illust display
                        updateIllustDisplay();

                        console.log('  ✓ Canvas state restoration completed successfully');
                        resolve();
                    }).catch(err => {
                        console.error('  ✗ Error during layer restoration:', err);
                        resolve(); // Resolve anyway to not break initialization
                    });
                } else {
                    console.log('  - No layers to restore');
                    // No layers to restore, just restore other state
                    if (canvasState.activeLayer !== undefined) {
                        state.activeLayer = canvasState.activeLayer;
                        setActiveLayer(canvasState.activeLayer);
                    }
                    if (canvasState.currentColor) {
                        state.currentColor = canvasState.currentColor;
                        // Update color display in UI
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
                        applyZoom();
                    }
                    if (canvasState.panOffset) {
                        state.panOffset = canvasState.panOffset;
                        applyZoom(); // Pan is also applied through applyZoom
                    }
                    if (canvasState.layerNames) {
                        state.layerNames = canvasState.layerNames;
                    }

                    // Restore timelapse events
                    if (canvasState.timelapseEvents && Array.isArray(canvasState.timelapseEvents)) {
                        console.log('  → Restoring', canvasState.timelapseEvents.length, 'timelapse events');
                        state.timelapseEvents = canvasState.timelapseEvents;
                    }
                    if (canvasState.timelapseSnapshots && Array.isArray(canvasState.timelapseSnapshots)) {
                        console.log('  → Restoring', canvasState.timelapseSnapshots.length, 'timelapse snapshots');
                        state.timelapseSnapshots = canvasState.timelapseSnapshots;
                    }
                    if (canvasState.lastSnapshotTime !== undefined) {
                        state.lastSnapshotTime = canvasState.lastSnapshotTime;
                    }

                    // Re-render layers UI
                    renderLayers();

                    // Update illust display
                    updateIllustDisplay();

                    resolve();
                }
            } catch (e) {
                console.error('  ✗ Failed to restore canvas state:', e);
                console.error('  ✗ Error details:', e.message, e.stack);
                resolve(); // Resolve anyway to not break the flow
            }
        });
    }

    async function saveIllust(title, description = '', tags = '') {
        setStatus('保存中...');

        try {
            const compositeImage = createCompositeImage();
            const illustData = buildIllustData();
            const timelapseData = await compressTimelapseData();

            await sendSaveRequest(title, description, tags, compositeImage, illustData, timelapseData);
        } catch (error) {
            setStatus('保存エラー: ' + error.message);
            console.error('Save error:', error);
        }
    }

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
                    window._timelapseWorker = new Worker('/admin/paint/js/timelapse-worker.js');
                }
                return await new Promise((resolve, reject) => {
                    const worker = window._timelapseWorker;
                    const onmsg = (ev) => {
                        worker.removeEventListener('message', onmsg);
                        if (ev.data && ev.data.success) resolve(ev.data.payload); else reject(ev.data && ev.data.error ? ev.data.error : 'worker error');
                    };
                    worker.addEventListener('message', onmsg);
                    worker.postMessage({ events: eventsWithMeta });
                });
            } else if (typeof pako !== 'undefined') {
                // Fallback to main-thread compression
                const headers = [];
                eventsWithMeta.forEach(ev => { Object.keys(ev).forEach(k => { if (headers.indexOf(k) === -1) headers.push(k); }); });
                const lines = [headers.join(',')];
                eventsWithMeta.forEach(ev => { const row = headers.map(h => { let v = ev[h]; if (v === undefined || v === null) return ''; if (Array.isArray(v) || typeof v === 'object') v = JSON.stringify(v); v = String(v); if (v.indexOf(',') !== -1 || v.indexOf('"') !== -1 || v.indexOf('\n') !== -1) { v = '"' + v.replace(/"/g, '""') + '"'; } return v; }); lines.push(row.join(',')); });
                const csv = lines.join('\n');
                const gz = pako.gzip(csv);
                let bin = '';
                for (let i = 0; i < gz.length; i++) bin += String.fromCharCode(gz[i]);
                return 'data:application/octet-stream;base64,' + btoa(bin);
            }
        } catch (err) {
            console.error('Timelapse compression error:', err);
        }
        return null;
    }

    async function sendSaveRequest(title, description, tags, compositeImage, illustData, timelapseData) {
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

            const res = await fetch('/admin/paint/api/save.php', {
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
                setStatus(`保存完了 (ID: ${json.data.id})`);
                setCurrentId(json.data.id);

                // Update current illust info
                state.currentIllustTitle = title;
                state.currentIllustDescription = description;
                state.currentIllustTags = tags;
                state.hasUnsavedChanges = false;

                // Update UI
                updateIllustDisplay();

                // Clear timelapse events
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

    function newIllust() {
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
        state.undoStacks = [[], [], [], []];
        state.redoStacks = [[], [], [], []];
        state.timelapseEvents = [];
        state.currentIllustId = null;
        state.currentIllustTitle = '';
        state.currentIllustDescription = '';
        state.currentIllustTags = '';
        state.hasUnsavedChanges = false;

        try {
            localStorage.removeItem('paint_current_illust_id');
        } catch (e) {
            console.error('Failed to clear persisted ID:', e);
        }

        updateIllustDisplay();
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

        markAsChanged();
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

        // Confirm if there are unsaved changes
        if (state.hasUnsavedChanges) {
            if (!confirm('未保存の変更があります。イラストを開きますか？現在の作業内容は失われます。')) {
                return;
            }
        }

        const idToLoad = selectedIllustId;
        setStatus('イラストを読み込んでいます...');
        closeOpenModal();

        try {
            const illustData = await fetchIllustData(idToLoad);
            await loadIllustLayers(illustData);

            // Set current ID and metadata
            setCurrentId(idToLoad);
            state.currentIllustTitle = illustData.title || '';
            state.currentIllustDescription = illustData.description || '';
            state.currentIllustTags = illustData.tags || '';
            state.hasUnsavedChanges = false;

            // Update UI
            updateIllustDisplay();
            renderLayers();
            setStatus(`イラスト ID:${idToLoad} を読み込みました`);
            console.log('Illustration loaded successfully');

        } catch (error) {
            setStatus('イラストの読み込みに失敗しました: ' + error.message);
            console.error('Failed to load illustration:', error);
        }
    }

    // ===== Illust Data Management =====
    async function fetchIllustData(id) {
        const resp = await fetch(`/admin/paint/api/load.php?id=${id}`, {
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

        // Parse illust_data
        if (!illust.illust_data) {
            throw new Error('illust_data is empty');
        }

        try {
            const parsedData = JSON.parse(illust.illust_data);
            console.log('Parsed illust_data:', parsedData);

            // Merge metadata
            return {
                ...illust,
                ...parsedData
            };
        } catch (e) {
            console.error('Failed to parse illust_data:', e, illust.illust_data);
            throw new Error('イラストデータの解析に失敗しました');
        }
    }

    async function loadIllustLayers(illustData) {
        try {
            // Clear current canvas and reset all layer properties
            state.layers.forEach((canvas, i) => {
                const ctx = state.contexts[i];
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                canvas.style.opacity = '1';
                canvas.style.display = 'block';
                state.layerNames[i] = CONFIG.LAYER_NAMES[i] || `レイヤー ${i}`;
            });

            // Reset undo/redo stacks
            state.undoStacks = Array(CONFIG.CANVAS.LAYER_COUNT).fill().map(() => []);
            state.redoStacks = Array(CONFIG.CANVAS.LAYER_COUNT).fill().map(() => []);
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
                            try {
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
                            } catch (error) {
                                reject(new Error(`Failed to draw layer ${idx}: ${error.message}`));
                            }
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
        } catch (error) {
            console.error('Error loading illust layers:', error);
            throw new Error(`レイヤーの読み込みに失敗しました: ${error.message}`);
        }
    }

    // ===== Color Picker =====
    // ===== Canvas Resize Modal =====
    function initResizeModal() {
        if (!elements.btnResize) return;

        let aspectRatio = 1;

        elements.btnResize.addEventListener('click', () => {
            const currentWidth = state.layers[0].width;
            const currentHeight = state.layers[0].height;
            
            elements.resizeWidth.value = currentWidth;
            elements.resizeHeight.value = currentHeight;
            aspectRatio = currentWidth / currentHeight;
            
            elements.resizeModalOverlay.classList.add('active');
        });

        elements.resizeModalClose.addEventListener('click', closeResizeModal);
        elements.resizeModalCancel.addEventListener('click', closeResizeModal);

        elements.resizeModalOverlay.addEventListener('click', (e) => {
            if (e.target === elements.resizeModalOverlay) {
                closeResizeModal();
            }
        });

        // Width/Height input with aspect ratio lock
        elements.resizeWidth.addEventListener('input', () => {
            if (elements.resizeKeepRatio.checked) {
                const newWidth = parseInt(elements.resizeWidth.value) || 512;
                elements.resizeHeight.value = Math.round(newWidth / aspectRatio);
            }
        });

        elements.resizeHeight.addEventListener('input', () => {
            if (elements.resizeKeepRatio.checked) {
                const newHeight = parseInt(elements.resizeHeight.value) || 512;
                elements.resizeWidth.value = Math.round(newHeight * aspectRatio);
            }
        });

        // Preset buttons
        document.querySelectorAll('.preset-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const width = parseInt(btn.dataset.width);
                const height = parseInt(btn.dataset.height);
                elements.resizeWidth.value = width;
                elements.resizeHeight.value = height;
                aspectRatio = width / height;
            });
        });

        // Apply resize
        elements.resizeModalApply.addEventListener('click', () => {
            const newWidth = parseInt(elements.resizeWidth.value) || 512;
            const newHeight = parseInt(elements.resizeHeight.value) || 512;
            
            if (newWidth < 64 || newWidth > 2048 || newHeight < 64 || newHeight > 2048) {
                alert('サイズは64～2048pxの範囲で指定してください');
                return;
            }
            
            resizeCanvas(newWidth, newHeight);
            closeResizeModal();
        });

        function closeResizeModal() {
            elements.resizeModalOverlay.classList.remove('active');
        }
    }

    function resizeCanvas(newWidth, newHeight) {
        // Save current layer data
        const layerData = state.layers.map((canvas, idx) => {
            return canvas.toDataURL();
        });

        // Update canvas-wrap container size
        if (elements.canvasWrap) {
            elements.canvasWrap.style.width = `${newWidth}px`;
            elements.canvasWrap.style.height = `${newHeight}px`;
        }

        // Resize all layers
        state.layers.forEach((canvas, idx) => {
            const oldWidth = canvas.width;
            const oldHeight = canvas.height;
            
            // Update both canvas internal size and CSS size
            canvas.width = newWidth;
            canvas.height = newHeight;
            canvas.style.width = `${newWidth}px`;
            canvas.style.height = `${newHeight}px`;

            // Redraw layer content
            const img = new Image();
            img.onload = () => {
                state.contexts[idx].drawImage(img, 0, 0);
            };
            img.src = layerData[idx];
        });

        // Update canvas info
        const canvasInfo = document.querySelector('.canvas-info');
        if (canvasInfo) {
            canvasInfo.textContent = `${newWidth} x ${newHeight} px`;
        }

        // Update timelapse canvas
        if (elements.timelapseCanvas) {
            elements.timelapseCanvas.width = newWidth;
            elements.timelapseCanvas.height = newHeight;
        }

        savePersistedState();
    }

    // ===== Canvas Transform Tools =====
    function initTransformTools() {
        if (elements.toolRotateCW) {
            elements.toolRotateCW.addEventListener('click', () => rotateCanvas(90));
        }
        
        if (elements.toolRotateCCW) {
            elements.toolRotateCCW.addEventListener('click', () => rotateCanvas(-90));
        }
        
        if (elements.toolFlipH) {
            elements.toolFlipH.addEventListener('click', () => flipCanvas('horizontal'));
        }
        
        if (elements.toolFlipV) {
            elements.toolFlipV.addEventListener('click', () => flipCanvas('vertical'));
        }
    }

    function rotateCanvas(degrees) {
        const layer = state.layers[state.activeLayer];
        const ctx = state.contexts[state.activeLayer];
        
        // Save current state
        const imageData = layer.toDataURL();
        const img = new Image();
        
        img.onload = () => {
            const oldWidth = layer.width;
            const oldHeight = layer.height;
            
            // For 90/-90 degree rotation, swap width and height
            if (Math.abs(degrees) === 90) {
                layer.width = oldHeight;
                layer.height = oldWidth;
            }
            
            // Clear and setup transformation
            ctx.clearRect(0, 0, layer.width, layer.height);
            ctx.save();
            
            // Move to center, rotate, move back
            ctx.translate(layer.width / 2, layer.height / 2);
            ctx.rotate((degrees * Math.PI) / 180);
            ctx.drawImage(img, -img.width / 2, -img.height / 2);
            
            ctx.restore();
            
            // Update canvas info if dimensions changed
            if (Math.abs(degrees) === 90) {
                const canvasInfo = document.querySelector('.canvas-info');
                if (canvasInfo) {
                    canvasInfo.textContent = `${layer.width} x ${layer.height} px`;
                }
                
                // Resize all other layers to match
                state.layers.forEach((canvas, idx) => {
                    if (idx !== state.activeLayer) {
                        const tempData = canvas.toDataURL();
                        canvas.width = layer.width;
                        canvas.height = layer.height;
                        const tempImg = new Image();
                        tempImg.onload = () => {
                            state.contexts[idx].drawImage(tempImg, 0, 0);
                        };
                        tempImg.src = tempData;
                    }
                });
            }
            
            setStatus(`${degrees}度回転しました`);
            saveUndoState();
        };
        
        img.src = imageData;
    }

    function flipCanvas(direction) {
        const layer = state.layers[state.activeLayer];
        const ctx = state.contexts[state.activeLayer];
        
        // Save current state
        const imageData = layer.toDataURL();
        const img = new Image();
        
        img.onload = () => {
            ctx.clearRect(0, 0, layer.width, layer.height);
            ctx.save();
            
            if (direction === 'horizontal') {
                ctx.translate(layer.width, 0);
                ctx.scale(-1, 1);
            } else {
                ctx.translate(0, layer.height);
                ctx.scale(1, -1);
            }
            
            ctx.drawImage(img, 0, 0);
            ctx.restore();
            
            setStatus(`${direction === 'horizontal' ? '左右' : '上下'}反転しました`);
            saveUndoState();
        };
        
        img.src = imageData;
    }

    // ===== Color Palette Management =====
    
    // HSV <-> RGB conversion utilities
    // ===== Color Utilities =====
    const ColorUtils = {
        rgbToHsv(r, g, b) {
            r /= 255;
            g /= 255;
            b /= 255;
            
            const max = Math.max(r, g, b);
            const min = Math.min(r, g, b);
            const diff = max - min;
            
            let h = 0;
            let s = max === 0 ? 0 : diff / max;
            let v = max;
            
            if (diff !== 0) {
                if (max === r) {
                    h = 60 * (((g - b) / diff) % 6);
                } else if (max === g) {
                    h = 60 * ((b - r) / diff + 2);
                } else {
                    h = 60 * ((r - g) / diff + 4);
                }
            }
            
            if (h < 0) h += 360;
            
            return {
                h: Math.round(h),
                s: Math.round(s * 100),
                v: Math.round(v * 100)
            };
        },
        
        hsvToRgb(h, s, v) {
            s /= 100;
            v /= 100;
            
            const c = v * s;
            const x = c * (1 - Math.abs((h / 60) % 2 - 1));
            const m = v - c;
            
            let r = 0, g = 0, b = 0;
            
            if (h >= 0 && h < 60) {
                r = c; g = x; b = 0;
            } else if (h >= 60 && h < 120) {
                r = x; g = c; b = 0;
            } else if (h >= 120 && h < 180) {
                r = 0; g = c; b = x;
            } else if (h >= 180 && h < 240) {
                r = 0; g = x; b = c;
            } else if (h >= 240 && h < 300) {
                r = x; g = 0; b = c;
            } else if (h >= 300 && h < 360) {
                r = c; g = 0; b = x;
            }
            
            return {
                r: Math.round((r + m) * 255),
                g: Math.round((g + m) * 255),
                b: Math.round((b + m) * 255)
            };
        },

        rgbToHex(r, g, b) {
            return '#' + [r, g, b].map(x => {
                const hex = x.toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            }).join('');
        },

        hexToRgb(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? {
                r: parseInt(result[1], 16),
                g: parseInt(result[2], 16),
                b: parseInt(result[3], 16)
            } : null;
        }
    };
    
    async function loadColorPalette() {
        try {
            const response = await fetch('/admin/paint/api/palette.php');
            const data = await response.json();
            
            if (data.success && data.colors) {
                const swatches = elements.colorPaletteGrid.querySelectorAll('.color-swatch');
                data.colors.forEach((color, index) => {
                    if (swatches[index]) {
                        swatches[index].style.background = color;
                        swatches[index].title = color + ' (ダブルクリックで編集)';
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load color palette:', error);
        }
    }

    async function saveColorToSlot(slotIndex, color) {
        try {
            const response = await fetch('/admin/paint/api/palette.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN
                },
                body: JSON.stringify({
                    slot_index: slotIndex,
                    color: color
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update the swatch
                const swatches = elements.colorPaletteGrid.querySelectorAll('.color-swatch');
                if (swatches[slotIndex]) {
                    swatches[slotIndex].style.background = color;
                    swatches[slotIndex].title = color;
                }
                setStatus(`パレット色を更新しました`);
            } else {
                throw new Error(data.error || 'Failed to save color');
            }
        } catch (error) {
            console.error('Failed to save color:', error);
            setStatus('色の保存に失敗しました');
        }
    }

    // ===== Save Modal =====
    function initSaveModal() {
        if (!elements.saveModalOverlay) return;

        elements.saveModalCancel.addEventListener('click', closeSaveModal);
        elements.saveModalSave.addEventListener('click', handleSaveIllust);

        elements.saveModalOverlay.addEventListener('click', (e) => {
            if (e.target === elements.saveModalOverlay) {
                closeSaveModal();
            }
        });
    }

    async function handleSaveButton() {
        // If already saved (has ID), do quick save
        if (state.currentIllustId) {
            await saveIllust(
                state.currentIllustTitle,
                state.currentIllustDescription,
                state.currentIllustTags
            );
        } else {
            // Otherwise, open modal to get metadata
            openSaveModal();
        }
    }

    function updateIllustDisplay() {
        // Update ID display
        if (state.currentIllustId) {
            elements.illustId.textContent = state.currentIllustId;
        } else {
            elements.illustId.textContent = '(未保存)';
        }

        // Update title display with unsaved marker
        if (state.currentIllustTitle) {
            const unsavedMarker = state.hasUnsavedChanges ? ' *' : '';
            elements.illustTitleDisplay.textContent = state.currentIllustTitle + unsavedMarker;
        } else {
            elements.illustTitleDisplay.textContent = '(未保存)';
        }
    }

    function markAsChanged() {
        if (!state.isInitializing && !state.hasUnsavedChanges) {
            state.hasUnsavedChanges = true;
            updateIllustDisplay();
        }
    }

    function openSaveModal() {
        if (!elements.saveModalOverlay) return;

        // Pre-fill with current illust info
        elements.saveTitle.value = state.currentIllustTitle;
        elements.saveDescription.value = state.currentIllustDescription;
        elements.saveTags.value = state.currentIllustTags;

        elements.saveModalOverlay.classList.add('active');
        elements.saveTitle.focus();
    }

    function closeSaveModal() {
        elements.saveModalOverlay.classList.remove('active');
    }

    async function handleSaveIllust() {
        const title = elements.saveTitle.value.trim();
        const description = elements.saveDescription.value.trim();
        const tags = elements.saveTags.value.trim();

        if (!title) {
            alert('タイトルを入力してください。');
            elements.saveTitle.focus();
            return;
        }

        closeSaveModal();
        await saveIllust(title, description, tags);
    }

    function initEditColorModal() {
        if (!elements.editColorModalOverlay) return;

        let currentSlotIndex = 0;
        let currentMode = 'hsv';
        let isUpdating = false; // Prevent circular updates

        elements.editColorModalClose.addEventListener('click', closeEditColorModal);
        elements.editColorModalCancel.addEventListener('click', closeEditColorModal);

        elements.editColorModalOverlay.addEventListener('click', (e) => {
            if (e.target === elements.editColorModalOverlay) {
                closeEditColorModal();
            }
        });

        // Tab switching
        elements.colorModeTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const mode = tab.dataset.mode;
                switchMode(mode);
            });
        });

        function switchMode(mode) {
            currentMode = mode;
            
            elements.colorModeTabs.forEach(tab => {
                tab.classList.toggle('active', tab.dataset.mode === mode);
            });

            if (mode === 'hsv') {
                elements.hsvSliders.style.display = 'block';
                elements.rgbSliders.style.display = 'none';
            } else {
                elements.hsvSliders.style.display = 'none';
                elements.rgbSliders.style.display = 'block';
            }
        }

        // HSV sliders
        function updatePreviewFromHSV() {
            if (isUpdating) return;
            isUpdating = true;

            const h = parseInt(elements.editHsvH.value);
            const s = parseInt(elements.editHsvS.value);
            const v = parseInt(elements.editHsvV.value);
            
            const rgb = ColorUtils.hsvToRgb(h, s, v);
            const hex = ColorUtils.rgbToHex(rgb.r, rgb.g, rgb.b);
            
            elements.editColorPreview.style.background = hex;
            elements.editColorInput.value = hex.toUpperCase();
            
            // Update RGB sliders
            elements.editRgbR.value = rgb.r;
            elements.editRgbG.value = rgb.g;
            elements.editRgbB.value = rgb.b;
            elements.editRgbRValue.textContent = rgb.r;
            elements.editRgbGValue.textContent = rgb.g;
            elements.editRgbBValue.textContent = rgb.b;

            isUpdating = false;
        }

        function updateHSVFromRGB() {
            if (isUpdating) return;
            isUpdating = true;

            const r = parseInt(elements.editRgbR.value);
            const g = parseInt(elements.editRgbG.value);
            const b = parseInt(elements.editRgbB.value);
            
            const hex = ColorUtils.rgbToHex(r, g, b);
            const hsv = ColorUtils.rgbToHsv(r, g, b);
            
            elements.editColorPreview.style.background = hex;
            elements.editColorInput.value = hex.toUpperCase();
            
            // Update HSV sliders
            elements.editHsvH.value = hsv.h;
            elements.editHsvS.value = hsv.s;
            elements.editHsvV.value = hsv.v;
            elements.editHsvHValue.textContent = hsv.h + '°';
            elements.editHsvSValue.textContent = hsv.s + '%';
            elements.editHsvVValue.textContent = hsv.v + '%';

            isUpdating = false;
        }

        function updateAllFromHex(hex) {
            if (isUpdating) return;
            isUpdating = true;

            const rgb = ColorUtils.hexToRgb(hex);
            const hsv = ColorUtils.rgbToHsv(rgb.r, rgb.g, rgb.b);
            
            elements.editColorPreview.style.background = hex;
            
            // Update RGB
            elements.editRgbR.value = rgb.r;
            elements.editRgbG.value = rgb.g;
            elements.editRgbB.value = rgb.b;
            elements.editRgbRValue.textContent = rgb.r;
            elements.editRgbGValue.textContent = rgb.g;
            elements.editRgbBValue.textContent = rgb.b;
            
            // Update HSV
            elements.editHsvH.value = hsv.h;
            elements.editHsvS.value = hsv.s;
            elements.editHsvV.value = hsv.v;
            elements.editHsvHValue.textContent = hsv.h + '°';
            elements.editHsvSValue.textContent = hsv.s + '%';
            elements.editHsvVValue.textContent = hsv.v + '%';

            isUpdating = false;
        }

        // HSV slider events
        elements.editHsvH.addEventListener('input', () => {
            elements.editHsvHValue.textContent = elements.editHsvH.value + '°';
            updatePreviewFromHSV();
        });

        elements.editHsvS.addEventListener('input', () => {
            elements.editHsvSValue.textContent = elements.editHsvS.value + '%';
            updatePreviewFromHSV();
        });

        elements.editHsvV.addEventListener('input', () => {
            elements.editHsvVValue.textContent = elements.editHsvV.value + '%';
            updatePreviewFromHSV();
        });

        // RGB slider events
        elements.editRgbR.addEventListener('input', () => {
            elements.editRgbRValue.textContent = elements.editRgbR.value;
            updateHSVFromRGB();
        });

        elements.editRgbG.addEventListener('input', () => {
            elements.editRgbGValue.textContent = elements.editRgbG.value;
            updateHSVFromRGB();
        });

        elements.editRgbB.addEventListener('input', () => {
            elements.editRgbBValue.textContent = elements.editRgbB.value;
            updateHSVFromRGB();
        });

        // Text input
        elements.editColorInput.addEventListener('input', (e) => {
            const value = e.target.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                updateAllFromHex(value);
            }
        });

        // Save button
        elements.editColorModalSave.addEventListener('click', async () => {
            const color = elements.editColorInput.value.toUpperCase();
            if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                await saveColorToSlot(currentSlotIndex, color);
                closeEditColorModal();
            } else {
                alert('正しいカラーコードを入力してください（例: #FF0000）');
            }
        });

        function closeEditColorModal() {
            elements.editColorModalOverlay.classList.remove('active');
        }

        // Make openEditColorModal available in the scope
        window.openEditColorModal = (slotIndex, currentColor) => {
            currentSlotIndex = slotIndex;
            
            // Normalize color
            let color = currentColor;
            if (color.startsWith('rgb')) {
                const rgb = color.match(/\d+/g);
                color = '#' + rgb.map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
            }
            
            // Initialize with current color
            updateAllFromHex(color.toUpperCase());
            
            // Set to HSV mode by default
            switchMode('hsv');
            
            elements.editColorModalOverlay.classList.add('active');
        };
    }

    // ===== Start Application =====
    console.log('🎨 Paint.js loaded, calling init()...');
    init();

    // Debug functions for browser console
    window.debugPaintState = () => {
        console.log('=== Paint State Debug ===');
        console.log('Current state:', state);
        console.log('localStorage keys:', Object.keys(localStorage).filter(k => k.startsWith('paint_')));
        console.log('Canvas state from localStorage:', localStorage.getItem('paint_canvas_state'));
        console.log('Illust ID from localStorage:', localStorage.getItem('paint_current_illust_id'));
        return state;
    };

    window.clearPaintState = () => {
        localStorage.removeItem('paint_canvas_state');
        localStorage.removeItem('paint_current_illust_id');
        console.log('Paint state cleared from localStorage');
    };

})();
