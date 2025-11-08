/**
 * Tools Module
 * Handles drawing tools, tool settings, and canvas drawing operations
 */

import { CONFIG } from './config.js';
import { state, elements } from './state.js';

/**
 * Color conversion utilities
 */
export const ColorUtils = {
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

/**
 * Set current drawing tool
 * @param {string} tool - Tool name ('pen', 'eraser', 'bucket', 'eyedropper')
 * @param {Function} updateStatusBar - Callback to update status bar
 */
export function setTool(tool, updateStatusBar) {
    state.currentTool = tool;

    // Update active button
    elements.toolBtns.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tool === tool);
    });

    // Show/hide tool settings
    if (elements.penSettings) {
        elements.penSettings.classList.toggle('hidden', tool !== 'pen');
    }
    if (elements.eraserSettings) {
        elements.eraserSettings.classList.toggle('hidden', tool !== 'eraser');
    }
    if (elements.bucketSettings) {
        elements.bucketSettings.classList.toggle('hidden', tool !== 'bucket');
    }

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

    if (updateStatusBar) {
        updateStatusBar();
    }
}

/**
 * Initialize tool buttons and settings
 * @param {Function} updateStatusBar - Callback to update status bar
 * @param {Function} undo - Undo callback
 * @param {Function} redo - Redo callback
 */
export function initToolListeners(updateStatusBar, undo, redo) {
    // Tool buttons
    elements.toolBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tool = btn.dataset.tool;
            setTool(tool, updateStatusBar);
        });
    });

    // Undo/Redo
    if (elements.toolUndo) {
        elements.toolUndo.addEventListener('click', undo);
    }
    if (elements.toolRedo) {
        elements.toolRedo.addEventListener('click', redo);
    }

    // Tool settings
    initToolSettings();
}

/**
 * Initialize tool settings UI
 */
function initToolSettings() {
    // Pen settings
    if (elements.penSize) {
        elements.penSize.addEventListener('input', (e) => {
            state.penSize = parseInt(e.target.value);
            if (elements.penSizeValue) {
                elements.penSizeValue.textContent = state.penSize;
            }
        });
    }

    if (elements.penAntialias) {
        elements.penAntialias.addEventListener('change', (e) => {
            state.contexts.forEach(ctx => {
                ctx.imageSmoothingEnabled = e.target.checked;
            });
        });
    }

    // Eraser settings
    if (elements.eraserSize) {
        elements.eraserSize.addEventListener('input', (e) => {
            state.eraserSize = parseInt(e.target.value);
            if (elements.eraserSizeValue) {
                elements.eraserSizeValue.textContent = state.eraserSize;
            }
        });
    }

    // Bucket settings
    if (elements.bucketTolerance) {
        elements.bucketTolerance.addEventListener('input', (e) => {
            state.bucketTolerance = parseInt(e.target.value);
            if (elements.bucketToleranceValue) {
                elements.bucketToleranceValue.textContent = state.bucketTolerance;
            }
        });
    }
}

/**
 * Initialize canvas drawing event listeners
 * @param {Function} recordTimelapse - Callback to record timelapse event
 * @param {Function} pushUndo - Callback to push undo state
 * @param {Function} savePersistedState - Callback to save canvas state
 * @param {Function} markAsChanged - Callback to mark canvas as changed
 */
export function initCanvasListeners(recordTimelapse, pushUndo, savePersistedState, markAsChanged, setColor) {
    state.layers.forEach((canvas, i) => {
        canvas.addEventListener('mousedown', (e) => handleDrawStart(e, i, recordTimelapse, pushUndo, setColor));
        canvas.addEventListener('mousemove', (e) => handleDrawMove(e, recordTimelapse));
        canvas.addEventListener('mouseup', (e) => handleDrawEnd(e, recordTimelapse, savePersistedState, markAsChanged));
        canvas.addEventListener('mouseleave', (e) => handleDrawEnd(e, recordTimelapse, savePersistedState, markAsChanged));

        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            handleDrawStart(e, i, recordTimelapse, pushUndo, setColor);
        }, { passive: false });
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            handleDrawMove(e, recordTimelapse);
        }, { passive: false });
        canvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            handleDrawEnd(e, recordTimelapse, savePersistedState, markAsChanged);
        }, { passive: false });
    });
}

/**
 * Handle draw start event
 */
function handleDrawStart(e, layerIndex, recordTimelapse, pushUndo, setColor) {
    // Don't draw when panning
    if (state.spaceKeyPressed || state.isPanning) {
        return;
    }

    // Use the currently active layer
    const drawLayerIndex = state.activeLayer;
    const pos = getPointerPos(e, state.layers[drawLayerIndex]);
    const ctx = state.contexts[drawLayerIndex];

    if (state.currentTool === 'eyedropper') {
        pickColor(drawLayerIndex, pos, setColor);
        return;
    }

    if (state.currentTool === 'bucket') {
        floodFill(drawLayerIndex, pos, pushUndo, recordTimelapse);
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
    if (recordTimelapse) {
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
}

/**
 * Handle draw move event
 */
function handleDrawMove(e, recordTimelapse) {
    if (!state.isDrawing) return;

    const pos = getPointerPos(e, state.layers[state.activeLayer]);
    const ctx = state.contexts[state.activeLayer];

    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();

    if (recordTimelapse) {
        recordTimelapse({
            t: Date.now(),
            type: 'move',
            layer: state.activeLayer,
            x: pos.x,
            y: pos.y
        });
    }
}

/**
 * Handle draw end event
 */
function handleDrawEnd(e, recordTimelapse, savePersistedState, markAsChanged) {
    if (!state.isDrawing) return;

    state.isDrawing = false;

    if (recordTimelapse) {
        recordTimelapse({
            t: Date.now(),
            type: 'end',
            layer: state.activeLayer
        });
    }

    // Auto-save canvas state
    if (savePersistedState) {
        savePersistedState();
    }

    // Mark as changed
    if (markAsChanged) {
        markAsChanged();
    }
}

/**
 * Get pointer position relative to canvas
 */
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

/**
 * Pick color from canvas (eyedropper tool)
 */
function pickColor(layerIndex, pos, setColor) {
    const ctx = state.contexts[layerIndex];
    const imageData = ctx.getImageData(pos.x, pos.y, 1, 1);
    const [r, g, b] = imageData.data;

    const hex = ColorUtils.rgbToHex(r, g, b);

    if (setColor) {
        setColor(hex);
    }
    setTool('pen');
}

/**
 * Flood fill tool
 */
function floodFill(layerIndex, pos, pushUndo, recordTimelapse) {
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

    if (recordTimelapse) {
        recordTimelapse({
            t: Date.now(),
            type: 'fill',
            layer: layerIndex,
            x: startX,
            y: startY,
            color: state.currentColor
        });
    }
}

/**
 * Check if two colors match within tolerance
 */
function colorMatch(c1, c2, tolerance) {
    return Math.abs(c1.r - c2.r) <= tolerance &&
        Math.abs(c1.g - c2.g) <= tolerance &&
        Math.abs(c1.b - c2.b) <= tolerance;
}
