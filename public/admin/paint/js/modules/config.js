/**
 * Configuration Module
 * Centralized configuration for the paint application
 */

export const CONFIG = {
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
