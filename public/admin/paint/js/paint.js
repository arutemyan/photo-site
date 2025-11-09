/**
 * Paint Application - Main Entry Point
 * Modularized and refactored version
 *
 * Features:
 * - Multiple drawing tools (pen, eraser, bucket, eyedropper)
 * - Multi-layer system with full controls
 * - Color palette with customization
 * - Timelapse recording and playback
 * - Undo/Redo system
 * - Zoom and pan controls
 * - Keyboard shortcuts
 */

'use strict';

// ===== Module Imports =====
import { state, elements, initializeElements } from './modules/state.js';
import { initColorPalette, setColor, initCurrentColorEdit } from './modules/colors.js';
import {
    renderLayers,
    setActiveLayer,
    addLayer,
    removeLayer,
    duplicateLayer,
    mergeLayerDown,
    clearLayer,
    initLayerContextMenu
} from './modules/layers.js';
import { pushUndo, undo, redo } from './modules/history.js';
import {
    initCanvasPan,
    initTransformTools,
    resizeCanvas
} from './modules/canvas_transform.js';
import {
    setTool,
    initToolListeners,
    initCanvasListeners
} from './modules/tools.js';
import {
    loadPersistedState,
    savePersistedState,
    setCurrentId,
    restoreCanvasState,
    exportWorkingData,
    importWorkingFile,
    saveIllust,
    newIllust,
    markAsChanged,
    fetchIllustData,
    loadIllustLayers
} from './modules/storage.js';
import {
    recordTimelapse,
    initTimelapseModal,
    openTimelapseModal,
    closeTimelapseModal
} from './modules/timelapse.js';
import {
    initModals,
    openOpenModal,
    closeOpenModal,
    getSelectedIllustId,
    openSaveModal,
    openResizeModal
} from './modules/modals.js';
import { initPanels } from './modules/panels.js';

// ===== Initialization =====
async function init() {

    // Initialize DOM elements
    initializeElements();

    // Initialize canvas
    await initCanvas();

    // Initialize UI components
    initUI();

    // Warn on page unload if unsaved changes
    window.addEventListener('beforeunload', (e) => {
        if (state.hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Mark initialization as complete
    state.isInitializing = false;
    setStatus('準備完了');
}

async function initCanvas() {

    // Initialize contexts
    state.layers = elements.layers;
    state.contexts = state.layers.map(canvas =>
        canvas.getContext('2d', { willReadFrequently: true })
    );

    // Set layer z-indexes
    state.layers.forEach((canvas, i) => {
        canvas.style.zIndex = i;
    });

    // Load persisted state (do not auto-restore; no manual restore button present)
    await loadPersistedState(restoreCanvasStateWrapper, setStatus);
}

function initUI() {
    // Panel controls (collapse/expand, resize)
    initPanels();

    // Color palette
    initColorPalette();
    initCurrentColorEdit();
    loadColorPalette();

    // Layers
    initLayersUI();

    // Tools
    initToolListeners(updateStatusBar, undoWrapper, redoWrapper);
    initCanvasListeners(
        recordTimelapse,
        pushUndo,
        savePersistedState,
        markAsChanged,
        setColor
    );

    // Transform tools
    initTransformTools(setStatus, pushUndo);
    initCanvasPan(setToolWrapper);

    // Timelapse
    initTimelapseModal(closeTimelapseModal);

    // Modals
    initModals(
        () => openOpenModal(setStatus),
        saveIllustWrapper,
        resizeCanvasWrapper,
        setColor
    );

    // Keyboard shortcuts
    initKeyboardShortcuts();

    // Header buttons
    initHeaderButtons();

    // Resize modal
    initResizeModal();
}

function initLayersUI() {
    // Add layer button
    if (elements.btnAddLayer) {
        elements.btnAddLayer.addEventListener('click', () => {
            addLayer(updateStatusBar, setStatus);
        });
    }

    // Layer context menu
    initLayerContextMenu(
        (layerIndex) => duplicateLayer(layerIndex, pushUndo, renderLayersWrapper, setStatus),
        (layerIndex) => mergeLayerDown(layerIndex, pushUndo, renderLayersWrapper, setStatus),
        (layerIndex) => clearLayer(layerIndex, pushUndo, renderLayersWrapper, setStatus),
        (layerIndex) => removeLayer(layerIndex, updateStatusBar, setStatus)
    );

    renderLayersWrapper();
}

function initHeaderButtons() {
    if (elements.btnSave) {
        elements.btnSave.addEventListener('click', handleSaveButton);
    }
    if (elements.btnSaveAs) {
        elements.btnSaveAs.addEventListener('click', openSaveModal);
    }
    if (elements.btnTimelapse) {
        elements.btnTimelapse.addEventListener('click', () => openTimelapseModal(setStatus));
    }
    if (elements.btnNew) {
        elements.btnNew.addEventListener('click', () => {
            newIllust(renderLayersWrapper, updateIllustDisplay, setStatus);
        });
    }
    
    if (elements.btnClear) {
        elements.btnClear.addEventListener('click', clearCurrentLayer);
    }
    if (elements.btnResize) {
        elements.btnResize.addEventListener('click', openResizeModal);
    }

    // Export / Import
    if (elements.btnExport) {
        elements.btnExport.addEventListener('click', () => {
            exportWorkingData();
        });
    }
    if (elements.importFileInput) {
        elements.importFileInput.addEventListener('change', async (ev) => {
            const file = ev.target.files && ev.target.files[0];
            if (!file) return;
            // Confirm overwrite if unsaved changes
            if (state.hasUnsavedChanges) {
                if (!confirm('未保存の変更があります。インポートすると現在の作業は上書きされます。続行しますか？')) {
                    elements.importFileInput.value = '';
                    return;
                }
            }
            await importWorkingFile(file);
            elements.importFileInput.value = '';
            // After import, update UI
            updateIllustDisplay();
            renderLayersWrapper();
        });
    }

    // Open modal (load illustration)
    if (elements.openModalLoad) {
        elements.openModalLoad.addEventListener('click', loadSelectedIllust);
    }
}

function initResizeModal() {
    // Already handled by modals.js, but we need to provide the resize callback
    // This is done in initModals above
}

function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Ignore shortcuts when typing in input fields
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }

        // Ctrl+Z: Undo
        if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
            e.preventDefault();
            undoWrapper();
        }

        // Ctrl+Y or Ctrl+Shift+Z: Redo
        if ((e.ctrlKey && e.key === 'y') || (e.ctrlKey && e.shiftKey && e.key === 'z')) {
            e.preventDefault();
            redoWrapper();
        }

        // Ctrl+S: Save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            handleSaveButton();
        }

        // Tool shortcuts (only if not pressing modifiers)
        if (!e.ctrlKey && !e.altKey && !e.metaKey) {
            switch (e.key) {
                case 'p':
                    setTool('pen', updateStatusBar);
                    break;
                case 'e':
                    setTool('eraser', updateStatusBar);
                    break;
                case 'b':
                    setTool('bucket', updateStatusBar);
                    break;
                case 'i':
                    setTool('eyedropper', updateStatusBar);
                    break;
                case 'w':
                    setTool('watercolor', updateStatusBar);
                    break;
            }
        }
    });
}

// ===== UI Helper Functions =====

function setStatus(text) {
    if (elements.statusText) {
        elements.statusText.textContent = text;
    }
}

function updateStatusBar() {
    const toolNames = {
        pen: 'ペン',
        eraser: '消しゴム',
        bucket: '塗りつぶし',
        eyedropper: 'スポイト',
        watercolor: '水彩ブラシ'
    };

    if (elements.statusTool) {
        elements.statusTool.textContent = `ツール: ${toolNames[state.currentTool] || state.currentTool}`;
    }
    if (elements.statusLayer) {
        const layerName = state.layerNames[state.activeLayer] || `レイヤー ${state.activeLayer}`;
        elements.statusLayer.textContent = `レイヤー: ${layerName}`;
    }

    // Update illust display when status changes
    updateIllustDisplay();
}

function updateIllustDisplay() {
    // Update ID display
    if (elements.illustId) {
        elements.illustId.textContent = state.currentIllustId || '(未保存)';
    }

    // Update title display with unsaved marker
    if (elements.illustTitleDisplay) {
        if (state.currentIllustTitle) {
            const unsavedMarker = state.hasUnsavedChanges ? ' *' : '';
            elements.illustTitleDisplay.textContent = state.currentIllustTitle + unsavedMarker;
        } else {
            elements.illustTitleDisplay.textContent = '(未保存)';
        }
    }
}

async function handleSaveButton() {
    // If already saved (has ID), do quick save
    if (state.currentIllustId) {
        await saveIllustWrapper(
            state.currentIllustTitle,
            state.currentIllustDescription,
            state.currentIllustTags
        );
    } else {
        // Otherwise, open modal to get metadata
        openSaveModal();
    }
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

async function loadSelectedIllust() {
    const illustId = getSelectedIllustId();
    if (!illustId) {
        console.warn('No illustration selected');
        return;
    }

    // Confirm if there are unsaved changes
    if (state.hasUnsavedChanges) {
        if (!confirm('未保存の変更があります。イラストを開きますか？現在の作業内容は失われます。')) {
            return;
        }
    }

    setStatus('イラストを読み込んでいます...');
    closeOpenModal();

    try {
        const illustData = await fetchIllustData(illustId);
        await loadIllustLayers(illustData, renderLayersWrapper);

        // Set current ID and metadata
        setCurrentId(illustId);
        state.currentIllustTitle = illustData.title || '';
        state.currentIllustDescription = illustData.description || '';
        state.currentIllustTags = illustData.tags || '';
        state.hasUnsavedChanges = false;

        // Update UI
        updateIllustDisplay();
        renderLayersWrapper();
        setStatus(`イラスト ID:${illustId} を読み込みました`);
    } catch (error) {
        console.error('Failed to load illustration:', error);
        setStatus('イラストの読み込みに失敗しました: ' + error.message);
        alert('イラストの読み込みに失敗しました。\n' + error.message);
    }
}

async function loadColorPalette() {
    try {
        const response = await fetch('api/palette.php');
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

// ===== Wrapper Functions for Module Callbacks =====

function renderLayersWrapper() {
    renderLayers(updateStatusBar, setStatus);
}

function setToolWrapper(tool) {
    setTool(tool, updateStatusBar);
}

function undoWrapper() {
    undo(renderLayersWrapper, setStatus);
}

function redoWrapper() {
    redo(renderLayersWrapper, setStatus);
}

async function saveIllustWrapper(title, description, tags) {
    await saveIllust(title, description, tags, setStatus, setCurrentId, updateIllustDisplay);
}

function resizeCanvasWrapper(width, height) {
    resizeCanvas(width, height, savePersistedState);
}

function restoreCanvasStateWrapper(canvasState) {
    return restoreCanvasState(
        canvasState,
        renderLayersWrapper,
        (index) => setActiveLayer(index, updateStatusBar, setStatus),
        updateIllustDisplay,
        () => {} // applyZoom - handled internally by canvas-transform
    );
}

// ===== Start Application =====
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
