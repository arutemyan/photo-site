/**
 * Colors Module
 * Handles color palette, color selection, and color editing
 */

import { CONFIG } from './config.js';
import { state, elements } from './state.js';

// Active color picker reference
let activeColorPicker = null;

/**
 * Initialize color palette
 * Creates 16 color swatches with click and double-click handlers
 */
export function initColorPalette() {
    if (!elements.colorPaletteGrid) {
        console.error('Color palette grid element not found');
        return;
    }

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

/**
 * Edit palette color by using the EDIT button functionality
 * This ensures consistent color picker positioning
 */
export function editPaletteColor(slotIndex, swatchElement) {
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

/**
 * Set current drawing color
 */
export function setColor(color) {
    // Normalize color format
    if (color.startsWith('rgb')) {
        // Convert rgb to hex
        const rgb = color.match(/\d+/g);
        color = '#' + rgb.map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
    }
    
    state.currentColor = color;
    
    if (elements.currentColor) {
        elements.currentColor.style.background = color;
    }
    if (elements.currentColorHex) {
        elements.currentColorHex.textContent = color.toUpperCase();
    }
    
    // Update RGB display
    const r = parseInt(color.slice(1, 3), 16);
    const g = parseInt(color.slice(3, 5), 16);
    const b = parseInt(color.slice(5, 7), 16);
    const rgbDisplay = document.getElementById('current-color-rgb');
    if (rgbDisplay) {
        rgbDisplay.textContent = `RGB(${r}, ${g}, ${b})`;
    }
}

/**
 * Initialize current color edit functionality
 * Sets up the EDIT button for changing the current color
 */
export function initCurrentColorEdit() {
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
