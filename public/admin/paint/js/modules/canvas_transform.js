/**
 * Canvas Transform Module
 * Handles zoom, pan, rotate, flip, and resize operations
 */

import { state, elements } from './state.js';

/**
 * Zoom in/out by delta
 * @param {number} delta - Amount to zoom (positive = in, negative = out)
 * @param {Function} setStatus - Callback to update status bar
 */
export function zoom(delta, setStatus) {
    state.zoomLevel = Math.max(0.25, Math.min(4, state.zoomLevel + delta));
    applyZoom();
    setStatus(`ズーム: ${Math.round(state.zoomLevel * 100)}%`);
}

/**
 * Reset zoom to 1:1
 */
export function zoomFit() {
    state.zoomLevel = 1;
    applyZoom();
}

/**
 * Apply current zoom level to canvas
 */
function applyZoom() {
    const scale = state.zoomLevel;
    const transform = `scale(${scale}) translate(${state.panOffset.x / scale}px, ${state.panOffset.y / scale}px)`;
    elements.canvasWrap.style.transform = transform;
}

/**
 * Apply current pan offset to canvas
 */
function applyPan() {
    const transform = `scale(${state.zoomLevel}) translate(${state.panOffset.x / state.zoomLevel}px, ${state.panOffset.y / state.zoomLevel}px)`;
    elements.canvasWrap.style.transform = transform;
}

/**
 * Initialize canvas panning with spacebar
 * @param {Function} setTool - Callback to set current tool
 */
export function initCanvasPan(setTool) {
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

/**
 * Initialize zoom and transform tools
 * @param {Function} setStatus - Callback to update status bar
 * @param {Function} pushUndo - Callback to push undo state
 */
export function initTransformTools(setStatus, pushUndo) {
    // Zoom controls
    if (elements.toolZoomIn) {
        elements.toolZoomIn.addEventListener('click', () => zoom(0.25, setStatus));
    }

    if (elements.toolZoomOut) {
        elements.toolZoomOut.addEventListener('click', () => zoom(-0.25, setStatus));
    }

    if (elements.toolZoomFit) {
        elements.toolZoomFit.addEventListener('click', zoomFit);
    }

    // Rotation and flip
    if (elements.toolRotateCW) {
        elements.toolRotateCW.addEventListener('click', () => rotateCanvas(90, setStatus, pushUndo));
    }

    if (elements.toolRotateCCW) {
        elements.toolRotateCCW.addEventListener('click', () => rotateCanvas(-90, setStatus, pushUndo));
    }

    if (elements.toolFlipH) {
        elements.toolFlipH.addEventListener('click', () => flipCanvas('horizontal', setStatus, pushUndo));
    }

    if (elements.toolFlipV) {
        elements.toolFlipV.addEventListener('click', () => flipCanvas('vertical', setStatus, pushUndo));
    }
}

/**
 * Rotate the active canvas layer
 * @param {number} degrees - Degrees to rotate (90, -90, 180, etc.)
 * @param {Function} setStatus - Callback to update status bar
 * @param {Function} pushUndo - Callback to push undo state
 */
export function rotateCanvas(degrees, setStatus, pushUndo) {
    const layer = state.layers[state.activeLayer];
    const ctx = state.contexts[state.activeLayer];

    // Save for undo
    pushUndo(state.activeLayer);

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
                    canvas.style.width = `${layer.width}px`;
                    canvas.style.height = `${layer.height}px`;
                    const tempImg = new Image();
                    tempImg.onload = () => {
                        state.contexts[idx].drawImage(tempImg, 0, 0);
                    };
                    tempImg.src = tempData;
                }
            });
        }

        setStatus(`${degrees}度回転しました`);
    };

    img.src = imageData;
}

/**
 * Flip the active canvas layer
 * @param {string} direction - 'horizontal' or 'vertical'
 * @param {Function} setStatus - Callback to update status bar
 * @param {Function} pushUndo - Callback to push undo state
 */
export function flipCanvas(direction, setStatus, pushUndo) {
    const layer = state.layers[state.activeLayer];
    const ctx = state.contexts[state.activeLayer];

    // Save for undo
    pushUndo(state.activeLayer);

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
    };

    img.src = imageData;
}

/**
 * Resize all canvas layers
 * @param {number} newWidth - New canvas width
 * @param {number} newHeight - New canvas height
 * @param {Function} savePersistedState - Callback to save state
 */
export function resizeCanvas(newWidth, newHeight, savePersistedState) {
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

    if (savePersistedState) {
        savePersistedState();
    }
}
