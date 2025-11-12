/**
 * State Management Module
 * Centralized state management for the paint application
 */

import { CONFIG } from './config.js';

// ===== State =====
export const state = {
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
    watercolorMaxSize: 40,
    watercolorHardness: 50, // 0-100: 0=soft/gradual decay, 100=hard/sharp edge
    watercolorOpacity: 0.3,
    isDrawing: false,
    lastWatercolorPos: null,

    // History
    undoStacks: Array(CONFIG.CANVAS.LAYER_COUNT).fill().map(() => []),
    redoStacks: Array(CONFIG.CANVAS.LAYER_COUNT).fill().map(() => []),

    // Timelapse
    timelapseEvents: [],
    timelapseSnapshots: [], // { idx, t, data }
    lastSnapshotTime: 0,
    // Monotonic sequence for timelapse events (debug/instrumentation)
    timelapseSeq: 0,

    // Paint
    currentIllustId: null,
    currentIllustTitle: '',
    currentIllustDescription: '',
    currentIllustTags: '',
    hasUnsavedChanges: false,

    // Pressure smoothing (value 0..1)
    lastPressure: 1.0,
    // Pressure settings
    penPressureEnabled: true,
    penPressureInfluence: 1.0, // 0..1
    eraserPressureEnabled: true,
    eraserPressureInfluence: 1.0, // 0..1

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

// User preferences / feature flags
// `localAutoSave` controls whether the app attempts to persist full canvas state
// into localStorage automatically. Default: false (avoid filling localStorage).
state.localAutoSave = false;

// ===== DOM Elements =====
export const elements = {
    // Canvas
    canvasWrap: null,
    layers: [],

    // Header buttons
    btnSave: null,
    btnSaveAs: null,
    btnTimelapse: null,
    btnNew: null,
    btnOpen: null,
    btnClear: null,
    btnResize: null,
    btnExport: null,
    importFileInput: null,
    illustId: null,
    illustTitleDisplay: null,

    // Tools
    toolBtns: [],
    toolUndo: null,
    toolRedo: null,
    toolZoomIn: null,
    toolZoomOut: null,
    toolZoomFit: null,
    toolRotateCW: null,
    toolRotateCCW: null,
    toolFlipH: null,
    toolFlipV: null,

    // Color palette
    currentColor: null,
    currentColorHex: null,
    currentColorRgb: null,
    colorPaletteGrid: null,
    currentColorEditBtn: null,

    // Tool settings
    penSize: null,
    penSizeValue: null,
    penAntialias: null,
    penPressureEnabled: null,
    penPressureInfluence: null,
    penPressureValue: null,
    eraserSize: null,
    eraserSizeValue: null,
    bucketTolerance: null,
    bucketToleranceValue: null,
    watercolorMaxSize: null,
    watercolorMaxSizeValue: null,
    watercolorHardness: null,
    watercolorHardnessValue: null,
    watercolorOpacity: null,
    watercolorOpacityValue: null,
    penSettings: null,
    eraserSettings: null,
    bucketSettings: null,
    watercolorSettings: null,

    // Layers
    layersList: null,
    btnAddLayer: null,

    // Context menu
    layerContextMenu: null,

    // Open modal
    openModalOverlay: null,
    openModalClose: null,
    openModalCancel: null,
    openModalLoad: null,
    illustGrid: null,
    openModalEmpty: null,

    // New modal
    newModalOverlay: null,
    newModalConfirm: null,
    newModalCancel: null,

    // Save modal
    saveModalOverlay: null,
    saveModalCancel: null,
    saveModalSave: null,
    saveTitle: null,
    saveDescription: null,
    saveTags: null,
    saveArtistName: null,

    // Resize modal
    resizeModalOverlay: null,
    resizeModalClose: null,
    resizeModalCancel: null,
    resizeModalApply: null,
    resizeWidth: null,
    resizeHeight: null,
    resizeKeepRatio: null,

    // Timelapse modal
    timelapseOverlay: null,
    timelapseCanvas: null,
    timelapseClose: null,
    timelapsePlay: null,
    timelapseStop: null,
    timelapseRestart: null,
    timelapseSeek: null,
    timelapseCurrentTime: null,
    timelapseTotalTime: null,
    timelapseSpeed: null,
    timelapseSpeedValue: null,
    timelapseIgnoreTime: null,

    // Edit Color modal
    editColorModalOverlay: null,
    editColorModalClose: null,
    editColorModalCancel: null,
    editColorModalSave: null,
    editColorInput: null,
    editColorPreview: null,

    // Status bar
    statusText: null,
    statusTool: null,
    statusLayer: null
};

/**
 * Initialize DOM elements
 * Must be called after DOM is loaded
 */
export function initializeElements() {
    // Canvas
    elements.canvasWrap = document.getElementById('canvas-wrap');
    elements.layers = Array.from(document.querySelectorAll('canvas.layer'));

    // Header buttons
    elements.btnSave = document.getElementById('btn-save');
    elements.btnSaveAs = document.getElementById('btn-save-as');
    elements.btnTimelapse = document.getElementById('btn-timelapse');
    elements.btnNew = document.getElementById('btn-new');
    elements.btnOpen = document.getElementById('btn-open');
    elements.btnClear = document.getElementById('btn-clear');
    elements.btnResize = document.getElementById('btn-resize');
    elements.btnExport = document.getElementById('btn-export');
    elements.importFileInput = document.getElementById('import-file-input');
    elements.illustId = document.getElementById('illust-id');
    elements.illustTitleDisplay = document.getElementById('illust-title-display');

    // Tools
    elements.toolBtns = document.querySelectorAll('.tool-btn[data-tool]');
    elements.toolUndo = document.getElementById('tool-undo');
    elements.toolRedo = document.getElementById('tool-redo');
    elements.toolZoomIn = document.getElementById('tool-zoom-in');
    elements.toolZoomOut = document.getElementById('tool-zoom-out');
    elements.toolZoomFit = document.getElementById('tool-zoom-fit');
    elements.toolRotateCW = document.getElementById('tool-rotate-cw');
    elements.toolRotateCCW = document.getElementById('tool-rotate-ccw');
    elements.toolFlipH = document.getElementById('tool-flip-h');
    elements.toolFlipV = document.getElementById('tool-flip-v');

    // Color palette
    elements.currentColor = document.getElementById('current-color');
    elements.currentColorHex = document.getElementById('current-color-hex');
    elements.currentColorRgb = document.getElementById('current-color-rgb');
    elements.colorPaletteGrid = document.getElementById('color-palette-grid');
    elements.currentColorEditBtn = document.getElementById('current-color-edit-btn');

    // Tool settings
    elements.penSize = document.getElementById('pen-size');
    elements.penSizeValue = document.getElementById('pen-size-value');
    elements.penAntialias = document.getElementById('pen-antialias');
    elements.penPressureEnabled = document.getElementById('pen-pressure-enabled');
    elements.penPressureInfluence = document.getElementById('pen-pressure-influence');
    elements.penPressureValue = document.getElementById('pen-pressure-value');
    elements.eraserSize = document.getElementById('eraser-size');
    elements.eraserSizeValue = document.getElementById('eraser-size-value');
    elements.eraserPressureEnabled = document.getElementById('eraser-pressure-enabled');
    elements.eraserPressureInfluence = document.getElementById('eraser-pressure-influence');
    elements.eraserPressureValue = document.getElementById('eraser-pressure-value');
    elements.bucketTolerance = document.getElementById('bucket-tolerance');
    elements.bucketToleranceValue = document.getElementById('bucket-tolerance-value');
    elements.watercolorMaxSize = document.getElementById('watercolor-max-size');
    elements.watercolorMaxSizeValue = document.getElementById('watercolor-max-size-value');
    elements.watercolorHardness = document.getElementById('watercolor-hardness');
    elements.watercolorHardnessValue = document.getElementById('watercolor-hardness-value');
    elements.watercolorOpacity = document.getElementById('watercolor-opacity');
    elements.watercolorOpacityValue = document.getElementById('watercolor-opacity-value');
    elements.penSettings = document.getElementById('pen-settings');
    elements.eraserSettings = document.getElementById('eraser-settings');
    elements.bucketSettings = document.getElementById('bucket-settings');
    elements.watercolorSettings = document.getElementById('watercolor-settings');

    // Layers
    elements.layersList = document.getElementById('layers-list');
    elements.btnAddLayer = document.getElementById('btn-add-layer');

    // Context menu
    elements.layerContextMenu = document.getElementById('layer-context-menu');

    // Open modal
    elements.openModalOverlay = document.getElementById('open-modal-overlay');
    elements.openModalClose = document.getElementById('open-modal-close');
    elements.openModalCancel = document.getElementById('open-modal-cancel');
    elements.openModalLoad = document.getElementById('open-modal-load');
    elements.illustGrid = document.getElementById('illust-grid');
    elements.openModalEmpty = document.getElementById('open-modal-empty');

    // New modal
    elements.newModalOverlay = document.getElementById('new-modal-overlay');
    elements.newModalConfirm = document.getElementById('new-modal-confirm');
    elements.newModalCancel = document.getElementById('new-modal-cancel');

    // Save modal
    elements.saveModalOverlay = document.getElementById('save-modal-overlay');
    elements.saveModalCancel = document.getElementById('save-modal-cancel');
    elements.saveModalSave = document.getElementById('save-modal-save');
    elements.saveTitle = document.getElementById('save-title');
    elements.saveDescription = document.getElementById('save-description');
    elements.saveTags = document.getElementById('save-tags');
    elements.saveArtistName = document.getElementById('save-artist-name');
    elements.saveNsfw = document.getElementById('save-nsfw');
    elements.saveVisible = document.getElementById('save-visible');
    elements.saveModeGroup = document.getElementById('save-mode-group');
    elements.saveModeNew = document.getElementById('save-mode-new');
    elements.saveModeOverwrite = document.getElementById('save-mode-overwrite');
    elements.saveTitle = document.getElementById('save-title');
    elements.saveDescription = document.getElementById('save-description');
    elements.saveTags = document.getElementById('save-tags');

    // Resize modal
    elements.resizeModalOverlay = document.getElementById('resize-modal-overlay');
    elements.resizeModalClose = document.getElementById('resize-modal-close');
    elements.resizeModalCancel = document.getElementById('resize-modal-cancel');
    elements.resizeModalApply = document.getElementById('resize-modal-apply');
    elements.resizeWidth = document.getElementById('resize-width');
    elements.resizeHeight = document.getElementById('resize-height');
    elements.resizeKeepRatio = document.getElementById('resize-keep-ratio');

    // Timelapse modal
    elements.timelapseOverlay = document.getElementById('timelapse-overlay');
    elements.timelapseCanvas = document.getElementById('timelapse-canvas');
    elements.timelapseClose = document.getElementById('timelapse-close');
    elements.timelapsePlay = document.getElementById('timelapse-play');
    elements.timelapseStop = document.getElementById('timelapse-stop');
    elements.timelapseRestart = document.getElementById('timelapse-restart');
    elements.timelapseSeek = document.getElementById('timelapse-seek');
    elements.timelapseCurrentTime = document.getElementById('timelapse-current-time');
    elements.timelapseTotalTime = document.getElementById('timelapse-total-time');
    elements.timelapseSpeed = document.getElementById('timelapse-speed');
    elements.timelapseSpeedValue = document.getElementById('timelapse-speed-value');
    elements.timelapseIgnoreTime = document.getElementById('timelapse-ignore-time');
        elements.timelapseRealTime = document.getElementById('timelapse-real-time');

    // Edit Color modal
    elements.editColorModalOverlay = document.getElementById('edit-color-modal-overlay');
    elements.editColorModalClose = document.getElementById('edit-color-modal-close');
    elements.editColorModalCancel = document.getElementById('edit-color-modal-cancel');
    elements.editColorModalSave = document.getElementById('edit-color-modal-save');
    elements.editColorInput = document.getElementById('edit-color-input');
    elements.editColorPreview = document.getElementById('edit-color-preview');

    // Status bar
    elements.statusText = document.getElementById('status-text');
    elements.statusTool = document.getElementById('status-tool');
    elements.statusLayer = document.getElementById('status-layer');
}
