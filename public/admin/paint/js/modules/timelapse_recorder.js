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
    // Attach a monotonic sequence id for debugging/ordering checks
    if (typeof state.timelapseSeq === 'undefined') state.timelapseSeq = 0;
    state.timelapseSeq += 1;
    try {
        // mutate event in-place so callers/consumers see _seq
        event._seq = state.timelapseSeq;
        event._recordedAt = Date.now();
    } catch (e) {
        // non-fatal
    }
    state.timelapseEvents.push(event);
}

/**
 * Clear all recorded events
 */
export function clearTimelapseData() {
    state.timelapseEvents = [];
    state.timelapseSnapshots = [];
}

/**
 * Get recorded events
 */
export function getTimelapseEvents() {
    return state.timelapseEvents;
}
