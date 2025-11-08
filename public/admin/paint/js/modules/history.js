/**
 * History Module
 * Handles Undo/Redo functionality for canvas layers
 */

import { CONFIG } from './config.js';
import { state } from './state.js';

/**
 * Push current canvas state to undo stack
 * @param {number} layerIndex - Index of layer to save
 */
export function pushUndo(layerIndex) {
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

/**
 * Undo last action on active layer
 * @param {Function} renderLayers - Callback to re-render layers
 * @param {Function} setStatus - Callback to update status bar
 */
export function undo(renderLayers, setStatus) {
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
    restoreSnapshot(state.activeLayer, snapshot, renderLayers);

    setStatus('元に戻しました');
}

/**
 * Redo last undone action on active layer
 * @param {Function} renderLayers - Callback to re-render layers
 * @param {Function} setStatus - Callback to update status bar
 */
export function redo(renderLayers, setStatus) {
    const stack = state.redoStacks[state.activeLayer];
    if (stack.length === 0) {
        setStatus('やり直せません');
        return;
    }

    const snapshot = stack.pop();
    pushUndo(state.activeLayer);
    restoreSnapshot(state.activeLayer, snapshot, renderLayers);

    setStatus('やり直しました');
}

/**
 * Restore canvas layer from snapshot
 * @param {number} layerIndex - Index of layer to restore
 * @param {Object} snapshot - Snapshot data containing image and properties
 * @param {Function} renderLayers - Callback to re-render layers
 */
function restoreSnapshot(layerIndex, snapshot, renderLayers) {
    const img = new Image();
    img.onload = () => {
        const ctx = state.contexts[layerIndex];
        const canvas = state.layers[layerIndex];

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0);

        canvas.style.display = snapshot.visible ? 'block' : 'none';
        canvas.style.opacity = snapshot.opacity;

        if (renderLayers) {
            renderLayers();
        }
    };
    img.src = snapshot.img;
}
