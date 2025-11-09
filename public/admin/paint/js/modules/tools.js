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
        drawWatercolorBrush(ctx, pos.x, pos.y, getPressure(e));
        state.lastWatercolorPos = pos;
    } else {
        ctx.beginPath();
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (state.currentTool === 'pen') {
            // apply pressure to line width if available
            const pressure = getPressure(e);
            state.lastPressure = pressure;
            ctx.lineWidth = Math.max(1, state.penSize * (0.2 + 0.8 * pressure));
            ctx.strokeStyle = state.currentColor;
            ctx.globalCompositeOperation = 'source-over';
        } else if (state.currentTool === 'eraser') {
            const pressure = getPressure(e);
            state.lastPressure = pressure;
            ctx.lineWidth = Math.max(1, state.eraserSize * (0.2 + 0.8 * pressure));
            ctx.globalCompositeOperation = 'destination-out';
        }

        ctx.moveTo(pos.x, pos.y);
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
        for (let i = 0; i <= steps; i++) {
            const t = i / steps;
            const x = lastPos.x + (pos.x - lastPos.x) * t;
            const y = lastPos.y + (pos.y - lastPos.y) * t;
            drawWatercolorBrush(ctx, x, y, samplePressure);

            // Record each sampled point so timelapse has the same density and pressure
            // as the live drawing. We include pressure so playback can reproduce
            // pressured radius per-sample.
            if (recordTimelapse) {
                recordTimelapse({
                    t: Date.now(),
                    type: 'move',
                    layer: state.activeLayer,
                    x: x,
                    y: y,
                    pressure: samplePressure
                });
            }
        }

        state.lastWatercolorPos = pos;
    } else {
        // apply dynamic pressure-based width
        const pressure = getPressure(e);
        // smoothing to avoid jitter
        const smooth = (state.lastPressure * 0.6) + (pressure * 0.4);
        state.lastPressure = smooth;
        if (state.currentTool === 'pen') {
            ctx.lineWidth = Math.max(1, state.penSize * (0.2 + 0.8 * smooth));
        } else if (state.currentTool === 'eraser') {
            ctx.lineWidth = Math.max(1, state.eraserSize * (0.2 + 0.8 * smooth));
        }

        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
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

    // Parse fill color (normalize and validate)
    const fillColor = ColorUtils.hexToRgb(state.currentColor);
    if (!fillColor) {
        // Invalid current color, nothing to do
        return;
    }

    // Only skip filling when the target pixel is fully opaque and rgb matches exactly.
    // If the target pixel is transparent (alpha !== 255) we should still fill.
    const fillAlpha = 255;
    if (targetColor.a === fillAlpha && colorMatch(targetColor, fillColor, 0)) {
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
    const maxRadius = state.watercolorMaxSize / 2;
    const hardness = state.watercolorHardness / 100; // 0-1: 0=soft, 1=hard
    const baseOpacity = state.watercolorOpacity || 0.3;

    // Apply pressure to size if pressure is enabled
    const pressuredMaxRadius = maxRadius * (0.5 + 0.5 * pressure);

    // Parse current color to get RGB values
    const colorRgb = ColorUtils.hexToRgb(state.currentColor);
    if (!colorRgb) return;

    // Create radial gradient from center to edge
    const gradient = ctx.createRadialGradient(x, y, 0, x, y, pressuredMaxRadius);

    // Hardness controls where the opacity starts to decay
    // hardness = 1.0 (100%): maintain opacity until 80% of radius (sharp edge)
    // hardness = 0.0 (0%): start decaying immediately from center (soft edge)
    const solidStop = hardness * 0.8;

    // Center to solidStop: maintain base opacity
    gradient.addColorStop(0, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${baseOpacity})`);
    if (solidStop > 0) {
        gradient.addColorStop(solidStop, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${baseOpacity})`);
    }

    // Add a mid-point for smoother transition
    const midStop = solidStop + (1 - solidStop) * 0.5;
    const midOpacity = baseOpacity * 0.3;
    gradient.addColorStop(midStop, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${midOpacity})`);

    // Edge: transparent
    gradient.addColorStop(1, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, 0)`);

    // Draw circle with gradient
    ctx.save();
    ctx.fillStyle = gradient;
    ctx.globalCompositeOperation = 'source-over';
    ctx.beginPath();
    ctx.arc(x, y, pressuredMaxRadius, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
}
