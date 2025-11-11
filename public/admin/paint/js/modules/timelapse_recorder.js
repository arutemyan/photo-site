/**
 * Timelapse Recorder Module
 * Admin専用のタイムラプス録画機能
 *
 * Features:
 * - Drawing event recording
 * - Snapshot management (every 200 events or 2 seconds)
 * - Memory-efficient storage
 */

import { state } from './state.js';

/**
 * Record a timelapse event
 */
export function recordTimelapse(event) {
    state.timelapseEvents.push(event);

    // Create snapshot every N events or every M ms
    const SNAPSHOT_EVENTS = 200;
    const SNAPSHOT_MS = 2000;
    const now = Date.now();
    const len = state.timelapseEvents.length;

    if (len % SNAPSHOT_EVENTS === 0 || (now - (state.lastSnapshotTime || 0)) > SNAPSHOT_MS) {
        try {
            createTimelapseSnapshot(len - 1);
            state.lastSnapshotTime = now;
        } catch (e) {
            console.warn('Snapshot creation failed:', e);
        }
    }
}

// Expose snapshot creation so other modules (e.g., layers) can force a snapshot
export function createTimelapseSnapshotPublic() {
    try {
        // createTimelapseSnapshot is defined below in this module
        createTimelapseSnapshot(state.timelapseEvents.length - 1);
        state.lastSnapshotTime = Date.now();
    } catch (e) {
        console.warn('Public snapshot creation failed:', e);
    }
}

/**
 * Create a snapshot of current canvas state
 */
function createTimelapseSnapshot(eventIndex) {
    const w = state.layers[0].width;
    const h = state.layers[0].height;
    const tmp = document.createElement('canvas');
    tmp.width = w;
    tmp.height = h;
    const tctx = tmp.getContext('2d');

    // White background
    tctx.fillStyle = '#FFFFFF';
    tctx.fillRect(0, 0, w, h);

    state.layers.forEach(c => {
        if (c.style.display !== 'none') {
            tctx.globalAlpha = parseFloat(c.style.opacity || '1');
            tctx.drawImage(c, 0, 0);
        }
    });

    const data = tmp.toDataURL('image/png');
    const ts = Date.now();
    state.timelapseSnapshots.push({ idx: eventIndex, t: ts, data });

    // Also insert a 'snapshot' event into the event stream so playback (which
    // converts events -> frames) includes the snapshot at the correct position.
    // This avoids having snapshots stored separately from events which would
    // otherwise be ignored during event-based playback.
    try {
        state.timelapseEvents.push({ t: ts, type: 'snapshot', data: data, width: w, height: h });
    } catch (e) {
        console.warn('Failed to append snapshot event to timelapseEvents:', e);
    }

    // Keep last few snapshots to limit memory
    if (state.timelapseSnapshots.length > 10) {
        state.timelapseSnapshots.shift();
    }
}

/**
 * Clear all recorded events and snapshots
 */
export function clearTimelapseData() {
    state.timelapseEvents = [];
    state.timelapseSnapshots = [];
    state.lastSnapshotTime = 0;
}

/**
 * Get recorded events
 */
export function getTimelapseEvents() {
    return state.timelapseEvents;
}

/**
 * Get snapshots
 */
export function getTimelapseSnapshots() {
    return state.timelapseSnapshots;
}
