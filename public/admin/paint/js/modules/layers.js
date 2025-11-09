/**
 * Layers Module
 * Handles layer management including creation, deletion, reordering, and properties
 */

import { CONFIG } from './config.js';
import { state, elements } from './state.js';
import { recordTimelapse, createTimelapseSnapshotPublic } from './timelapse_recorder.js';

// Context menu state
let contextMenuTargetLayer = -1;

/**
 * Initialize layer UI
 */
export function initLayers(updateStatusBar, addLayer) {
    // Add layer button
    if (elements.btnAddLayer) {
        elements.btnAddLayer.addEventListener('click', addLayer);
    }
    
    renderLayers(updateStatusBar, setStatus);
}

/**
 * Render all layers in the UI
 */
export function renderLayers(updateStatusBar, setStatus) {
    if (!elements.layersList) return;
    
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
                toggleLayerVisibility(layerIndex, updateStatusBar, setStatus);
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
                setActiveLayer(layerIndex, updateStatusBar, setStatus);
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
            upBtn.textContent = '↓';
            upBtn.title = '下へ';
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
                moveLayer(layerIndex, -1, updateStatusBar, setStatus);
            });

            const downBtn = document.createElement('button');
            downBtn.className = 'layer-control-btn';
            downBtn.textContent = '↑';
            downBtn.title = '上へ';
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
                moveLayer(layerIndex, 1, updateStatusBar, setStatus);
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
                setActiveLayer(layerIndex, updateStatusBar, setStatus);
            });

            elements.layersList.appendChild(layerItem);
        })(i);
    }

    updateStatusBar();
}

/**
 * Set active drawing layer
 */
export function setActiveLayer(index, updateStatusBar, setStatus) {
    state.activeLayer = index;
    renderLayers(updateStatusBar, setStatus);
}

/**
 * Toggle layer visibility
 */
export function toggleLayerVisibility(index, updateStatusBar, setStatus) {
    const layer = state.layers[index];
    const newDisplay = layer.style.display === 'none' ? 'block' : 'none';
    layer.style.display = newDisplay;
    // Record visibility change in timelapse so playback can reflect it
    try {
        if (typeof recordTimelapse === 'function') {
            recordTimelapse({ t: Date.now(), type: 'visibility', layer: index, visible: newDisplay !== 'none' });
            // Also create a snapshot to capture composite state immediately
            if (typeof createTimelapseSnapshotPublic === 'function') {
                createTimelapseSnapshotPublic();
            }
        }
    } catch (e) {
        console.warn('Failed to record visibility change for timelapse:', e);
    }
    renderLayers(updateStatusBar, setStatus);
}

/**
 * Set layer opacity
 */
export function setLayerOpacity(index, opacity) {
    state.layers[index].style.opacity = opacity.toString();
    // Record opacity change so timelapse playback can reflect it
    try {
        if (typeof recordTimelapse === 'function') {
            recordTimelapse({ t: Date.now(), type: 'opacity', layer: index, opacity: opacity });
            if (typeof createTimelapseSnapshotPublic === 'function') {
                createTimelapseSnapshotPublic();
            }
        }
    } catch (e) {
        console.warn('Failed to record opacity change for timelapse:', e);
    }
}

/**
 * Move layer up or down in stack
 */
export function moveLayer(index, delta, updateStatusBar, setStatus) {
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

    // Record a timelapse reorder event and force a snapshot so playback can reflect stacking changes
    try {
        if (typeof recordTimelapse === 'function') {
            recordTimelapse({ t: Date.now(), type: 'reorder', from: index, to: to });
        }
        if (typeof createTimelapseSnapshotPublic === 'function') {
            createTimelapseSnapshotPublic();
        }
    } catch (err) {
        // non-fatal
        console.warn('Failed to record reorder snapshot/event:', err);
    }

    renderLayers(updateStatusBar, setStatus);
}

/**
 * Add new layer
 */
export function addLayer(updateStatusBar, setStatus) {
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
    setActiveLayer(state.layers.length - 1, updateStatusBar, setStatus);

    renderLayers(updateStatusBar, setStatus);
    setStatus(`新規レイヤー ${state.layers.length - 1} を追加しました`);
}

/**
 * Remove layer
 */
export function removeLayer(layerIndex, updateStatusBar, setStatus) {
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

    renderLayers(updateStatusBar, setStatus);
    setStatus(`レイヤー ${layerIndex} を削除しました`);
}

/**
 * Show layer context menu
 */
export function showLayerContextMenu(x, y, layerIndex) {
    contextMenuTargetLayer = layerIndex;

    if (!elements.layerContextMenu) return;

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

/**
 * Initialize layer context menu
 */
export function initLayerContextMenu(duplicateLayer, mergeLayerDown, clearLayer, removeLayer) {
    if (!elements.layerContextMenu) return;

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

/**
 * Duplicate layer
 */
export function duplicateLayer(layerIndex, pushUndo, updateStatusBar, setStatus) {
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

    renderLayers(updateStatusBar, setStatus);
    setStatus(`レイヤー ${layerIndex} を複製しました`);
}

/**
 * Merge layer down
 */
export function mergeLayerDown(layerIndex, pushUndo, updateStatusBar, setStatus) {
    if (layerIndex <= 0) {
        setStatus('最下層のレイヤーは結合できません');
        return;
    }

    pushUndo(layerIndex - 1);

    const sourceCanvas = state.layers[layerIndex];
    const targetCtx = state.contexts[layerIndex - 1];

    // Merge down
    targetCtx.globalAlpha = parseFloat(sourceCanvas.style.opacity || '1');
    targetCtx.drawImage(sourceCanvas, 0, 0);
    targetCtx.globalAlpha = 1;

    // Clear source layer
    const sourceCtx = state.contexts[layerIndex];
    sourceCtx.clearRect(0, 0, sourceCanvas.width, sourceCanvas.height);

    renderLayers(updateStatusBar, setStatus);
    setStatus(`レイヤー ${layerIndex} を下のレイヤーと結合しました`);
}

/**
 * Clear layer
 */
export function clearLayer(layerIndex, pushUndo, updateStatusBar, setStatus) {
    if (!confirm(`レイヤー ${layerIndex} (${state.layerNames[layerIndex]}) をクリアしますか？`)) {
        return;
    }

    pushUndo(layerIndex);

    const ctx = state.contexts[layerIndex];
    const canvas = state.layers[layerIndex];

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    setStatus(`レイヤー ${layerIndex} をクリアしました`);
}
