/**
 * Tools Module
 * Handles drawing tools, tool settings, and canvas drawing operations
 */

import { CONFIG } from './config.js';
import { state, elements } from './state.js';
import { hexToRgb as _hexToRgb, rgbToHex as _rgbToHex } from '../../../../paint/js/color_utils.js';
import { drawStrokePrimitive, drawFillPrimitive } from '../../../../paint/js/draw_primitives.js';
import { sampleInterpolatedPoints } from '../../../../paint/js/timelapse_utils.js';

// Render queue to batch draw calls and reduce immediate per-sample overhead.
// Stored on state to keep lifecycle with the canvas/session.
if (!state._renderQueue) state._renderQueue = [];
if (state._renderScheduled === undefined) state._renderScheduled = false;

function flushRenderQueue() {
    const q = state._renderQueue.splice(0, state._renderQueue.length);
    for (const item of q) {
        try {
            drawStrokePrimitive(item.ctx, item.frame, item.layerStates || {});
        } catch (e) {
            console.warn('render queue draw error:', e);
        }
    }
    state._renderScheduled = false;
}

function scheduleRenderFlush() {
    if (state._renderScheduled) return;
    state._renderScheduled = true;
    requestAnimationFrame(() => flushRenderQueue());
}

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
        return _rgbToHex(r, g, b);
    },

    hexToRgb(hex) {
        return _hexToRgb(hex);
    }
};

/**
 * Set current drawing tool
 * @param {string} tool - Tool name ('pen', 'eraser', 'bucket', 'eyedropper', 'watercolor')
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
    if (elements.watercolorSettings) {
        elements.watercolorSettings.classList.toggle('hidden', tool !== 'watercolor');
    }

    // Update cursor
    state.layers.forEach(canvas => {
        if (tool === 'pen' || tool === 'eraser' || tool === 'watercolor') {
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

    // Pen pressure controls
    if (elements.penPressureEnabled) {
        elements.penPressureEnabled.addEventListener('change', (e) => {
            state.penPressureEnabled = !!e.target.checked;
        });
    }
    if (elements.penPressureInfluence) {
        elements.penPressureInfluence.addEventListener('input', (e) => {
            const pct = parseInt(e.target.value, 10) || 0;
            state.penPressureInfluence = Math.max(0, Math.min(1, pct / 100));
            if (elements.penPressureValue) elements.penPressureValue.textContent = pct + '%';
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

    // Eraser pressure controls
    if (elements.eraserPressureEnabled) {
        elements.eraserPressureEnabled.addEventListener('change', (e) => {
            state.eraserPressureEnabled = !!e.target.checked;
        });
    }
    if (elements.eraserPressureInfluence) {
        elements.eraserPressureInfluence.addEventListener('input', (e) => {
            const pct = parseInt(e.target.value, 10) || 0;
            state.eraserPressureInfluence = Math.max(0, Math.min(1, pct / 100));
            if (elements.eraserPressureValue) elements.eraserPressureValue.textContent = pct + '%';
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

    // Watercolor settings
    if (elements.watercolorMaxSize) {
        elements.watercolorMaxSize.addEventListener('input', (e) => {
            state.watercolorMaxSize = parseInt(e.target.value);
            if (elements.watercolorMaxSizeValue) {
                elements.watercolorMaxSizeValue.textContent = state.watercolorMaxSize;
            }
        });
    }
    if (elements.watercolorHardness) {
        elements.watercolorHardness.addEventListener('input', (e) => {
            state.watercolorHardness = parseInt(e.target.value);
            if (elements.watercolorHardnessValue) {
                elements.watercolorHardnessValue.textContent = state.watercolorHardness;
            }
        });
    }
    if (elements.watercolorOpacity) {
        elements.watercolorOpacity.addEventListener('input', (e) => {
            state.watercolorOpacity = parseInt(e.target.value) / 100;
            if (elements.watercolorOpacityValue) {
                elements.watercolorOpacityValue.textContent = parseInt(e.target.value);
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
        // Prefer Pointer Events when available (unified mouse/touch/pen with pressure)
        if (window.PointerEvent) {
            canvas.addEventListener('pointerdown', (e) => {
                // capture pointer to continue receiving events outside canvas
                try { canvas.setPointerCapture(e.pointerId); } catch (err) {}
                e.preventDefault();
                handleDrawStart(e, i, recordTimelapse, pushUndo, setColor);
            });
            canvas.addEventListener('pointermove', (e) => {
                handleDrawMove(e, recordTimelapse);
            });
            canvas.addEventListener('pointerup', (e) => {
                try { canvas.releasePointerCapture(e.pointerId); } catch (err) {}
                handleDrawEnd(e, recordTimelapse, savePersistedState, markAsChanged);
            });
            canvas.addEventListener('pointercancel', (e) => {
                try { canvas.releasePointerCapture(e.pointerId); } catch (err) {}
                handleDrawEnd(e, recordTimelapse, savePersistedState, markAsChanged);
            });
        } else {
            // Fallback for older browsers: mouse + touch
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
        }
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
    if (state.currentTool === 'watercolor') {
        // Watercolor brush uses different drawing method
        const pressure = getPressure(e);
        drawWatercolorBrush(ctx, pos.x, pos.y, pressure);
        state.lastWatercolorPos = pos;
        state.lastWatercolorPressure = pressure;
    } else {
        // For pen/eraser live drawing, render per-segment using drawStrokePrimitive so
        // editor and timelapse rendering share the same algorithm.
        const pressure = getPressure(e);
        state.lastPressure = pressure;

        // initialize a small path buffer for the current live stroke
        state.currentStrokePath = [{ x: pos.x, y: pos.y, pressure }];
        // compute starting size (kept for potential use)
        state.currentStrokeSize = state.currentTool === 'pen'
            ? Math.max(1, state.penSize * (0.2 + 0.8 * pressure))
            : Math.max(1, state.eraserSize * (0.2 + 0.8 * pressure));
    }

    // Record timelapse
    if (recordTimelapse) {
        recordTimelapse({
            t: Date.now(),
            type: 'start',
            layer: drawLayerIndex,
            x: pos.x,
            y: pos.y,
            color: state.currentColor,
            size: state.currentTool === 'watercolor' ? state.watercolorMaxSize : ctx.lineWidth,
            pressure: state.lastPressure,
            tool: state.currentTool,
            ...(state.currentTool === 'watercolor' ? {
                watercolorHardness: state.watercolorHardness,
                watercolorOpacity: state.watercolorOpacity
            } : {})
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

    if (state.currentTool === 'watercolor') {
        // Interpolate between last position and current position
        const lastPos = state.lastWatercolorPos || pos;
        const dist = Math.sqrt(Math.pow(pos.x - lastPos.x, 2) + Math.pow(pos.y - lastPos.y, 2));
        const steps = Math.max(1, Math.ceil(dist / 2)); // Draw every 2 pixels for smooth stroke

        // Sample and draw every ~2px (matches live drawing)
        const samplePressure = getPressure(e);
        const lastPressure = state.lastWatercolorPressure !== undefined ? state.lastWatercolorPressure : samplePressure;
        const samples = sampleInterpolatedPoints(lastPos, pos, lastPressure, samplePressure, 2);
        for (const s of samples) {
            drawWatercolorBrush(ctx, s.x, s.y, s.pressure);
            if (recordTimelapse) {
                recordTimelapse({
                    t: Date.now(),
                    type: 'move',
                    layer: state.activeLayer,
                    x: s.x,
                    y: s.y,
                    pressure: s.pressure
                });
            }
        }

        state.lastWatercolorPos = pos;
        state.lastWatercolorPressure = samplePressure;
    } else {
        // apply dynamic pressure-based width and render a short segment using shared primitive
        const pressure = getPressure(e);
        // smoothing to avoid jitter
        const smooth = (state.lastPressure * 0.6) + (pressure * 0.4);
        // compute width for this sample
        const width = state.currentTool === 'pen'
            ? Math.max(1, state.penSize * (0.2 + 0.8 * smooth))
            : Math.max(1, state.eraserSize * (0.2 + 0.8 * smooth));

        // last recorded point for this live stroke
        const lastPt = (state.currentStrokePath && state.currentStrokePath.length)
            ? state.currentStrokePath[state.currentStrokePath.length - 1]
            : { x: pos.x, y: pos.y, pressure: smooth };

        // build a tiny frame segment and delegate rendering to shared primitive
        const segFrame = {
            type: 'stroke',
            tool: state.currentTool,
            color: state.currentColor,
            size: width,
            path: [
                { x: lastPt.x, y: lastPt.y, pressure: lastPt.pressure },
                { x: pos.x, y: pos.y, pressure: smooth }
            ]
        };

    // enqueue segment to be drawn on the next animation frame to batch work
    state._renderQueue.push({ ctx, frame: segFrame, layerStates: {} });
    scheduleRenderFlush();

        // append to current stroke buffer
        if (!state.currentStrokePath) state.currentStrokePath = [];
        state.currentStrokePath.push({ x: pos.x, y: pos.y, pressure: smooth });

        state.lastPressure = smooth;
    }

    // For watercolor, moves have already been recorded per-sample above.
    if (recordTimelapse && state.currentTool !== 'watercolor') {
        recordTimelapse({
            t: Date.now(),
            type: 'move',
            layer: state.activeLayer,
            x: pos.x,
            y: pos.y,
            pressure: state.lastPressure
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
 * Extract pressure from PointerEvent or TouchEvent
 * Returns a normalized value between 0 and 1. Defaults to 1 for mouse.
 */
function getPressure(e) {
    // PointerEvent (pen/mouse/touch) provides pressure
    if (typeof e.pressure === 'number') {
        // some mice report 0 when not pressed; ensure a minimum
        return Math.max(0, Math.min(1, e.pressure));
    }

    // Touch events may include touches[0].force (0..1) on some devices
    if (e.touches && e.touches[0] && typeof e.touches[0].force === 'number') {
        return Math.max(0, Math.min(1, e.touches[0].force));
    }

    // Fallback: mouse (no pressure) assume full pressure
    return 1.0;
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
    const startX = Math.floor(pos.x);
    const startY = Math.floor(pos.y);

    // Use shared primitive with tolerance option (bucket tool requires tolerance)
    const frame = {
        type: 'fill',
        x: startX,
        y: startY,
        color: state.currentColor,
        layer: layerIndex
    };

    drawFillPrimitive(ctx, frame, canvas.width, canvas.height, {}, { tolerance: state.bucketTolerance });

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
    if (!c2) return false;
    return Math.abs(c1.r - c2.r) <= tolerance &&
        Math.abs(c1.g - c2.g) <= tolerance &&
        Math.abs(c1.b - c2.b) <= tolerance;
}

/**
 * Draw watercolor brush with alpha gradient
 * @param {CanvasRenderingContext2D} ctx - Canvas context
 * @param {number} x - X position
 * @param {number} y - Y position
 * @param {number} pressure - Pen pressure (0-1)
 */
function drawWatercolorBrush(ctx, x, y, pressure) {
    // Build a one-sample frame and delegate to shared primitive for consistency
    const frame = {
        type: 'stroke',
        tool: 'watercolor',
        color: state.currentColor,
        size: state.watercolorMaxSize,
        watercolorHardness: state.watercolorHardness,
        watercolorOpacity: state.watercolorOpacity,
        path: [{ x, y, pressure }]
    };

    drawStrokePrimitive(ctx, frame, {});
}
