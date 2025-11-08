/**
 * Timelapse Module (Admin)
 * タイムラプスの再生・UI制御（共通プレイヤーを利用）
 *
 * 録画機能は timelapse_recorder.js で提供
 */

import { state, elements } from './state.js';
import { TimelapsePlayer, parseTimelapseCSV } from '/paint/js/timelapse_player.js';

let timelapsePlayer = null;

/**
 * Initialize timelapse modal event listeners
 */
export function initTimelapseModal(closeModal) {
    elements.timelapseClose.addEventListener('click', closeModal);
    elements.timelapseOverlay.addEventListener('click', (e) => {
        if (e.target === elements.timelapseOverlay) {
            closeModal();
        }
    });

    elements.timelapsePlay.addEventListener('click', toggleTimelapsePlayback);
    elements.timelapseStop.addEventListener('click', stopTimelapsePlayback);
    elements.timelapseRestart.addEventListener('click', restartTimelapse);

    elements.timelapseSpeed.addEventListener('input', (e) => {
        elements.timelapseSpeedValue.textContent = e.target.value + 'x';
        if (timelapsePlayer) {
            timelapsePlayer.setSpeed(parseFloat(e.target.value) || 1);
        }
    });

    elements.timelapseIgnoreTime.addEventListener('change', (e) => {
        if (timelapsePlayer) {
            timelapsePlayer.setIgnoreTimestamps(e.target.checked);
        }
    });

    // Real-time playback option: use recorded intervals but exclude long pauses
    if (elements.timelapseRealTime) {
        elements.timelapseRealTime.addEventListener('change', (e) => {
            if (timelapsePlayer) {
                const enabled = e.target.checked;
                // Real-time mode should not be combined with "ignore timestamps"; prefer real-time
                if (enabled && elements.timelapseIgnoreTime) elements.timelapseIgnoreTime.checked = false;
                timelapsePlayer.setIgnoreTimestamps(false);
                timelapsePlayer.setRealTime(enabled);
            }
        });
    }

    // Seek range handlers
    const seekBar = elements.timelapseSeek;
    if (seekBar) {
        seekBar.addEventListener('click', (e) => {
            if (!timelapsePlayer) return;
            const rect = seekBar.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const percent = x / rect.width;
            const frameIndex = Math.floor(percent * timelapsePlayer.frames.length);
            timelapsePlayer.seek(frameIndex);
        });
    }
}

/**
 * Open timelapse modal
 */
export function openTimelapseModal(setStatus) {
    if (state.timelapseEvents.length === 0 && !state.currentIllustId) {
        if (setStatus) {
            setStatus('タイムラプスデータがありません');
        }
        return;
    }

    elements.timelapseOverlay.classList.add('active');

    if (state.timelapseEvents.length > 0) {
        playTimelapse(state.timelapseEvents);
    } else if (state.currentIllustId) {
        loadAndPlayTimelapse(state.currentIllustId, setStatus);
    }
}

/**
 * Close timelapse modal
 */
export function closeTimelapseModal() {
    elements.timelapseOverlay.classList.remove('active');
    stopTimelapsePlayback();
}

/**
 * Load and play timelapse from server
 */
async function loadAndPlayTimelapse(id, setStatus) {
    if (setStatus) {
        setStatus('タイムラプスを読み込んでいます...');
    }

    try {
        const resp = await fetch(`/admin/paint/api/timelapse.php?id=${encodeURIComponent(id)}`, {
            credentials: 'same-origin'
        });

        if (!resp.ok) {
            if (setStatus) {
                setStatus('タイムラプスの読み込みに失敗しました');
            }
            return;
        }

        const ab = await resp.arrayBuffer();

        if (typeof pako !== 'undefined') {
            const uint8 = new Uint8Array(ab);
            const out = pako.ungzip(uint8, { to: 'string' });

            let frames = null;
            try {
                // Try JSON first
                frames = JSON.parse(out);
            } catch (jsonError) {
                // Fallback: parse as CSV timelapse format
                frames = parseTimelapseCSV(out);
            }

            if (frames && frames.length > 0) {
                playTimelapse(frames);
                if (setStatus) {
                    setStatus('タイムラプス再生中');
                }
            } else {
                if (setStatus) {
                    setStatus('タイムラプスデータが空です');
                }
            }
        }
    } catch (err) {
        console.error('Failed to load timelapse:', err);
        if (setStatus) {
            setStatus('タイムラプスの読み込みに失敗しました');
        }
    }
}

/**
 * Play timelapse using shared TimelapsePlayer
 */
function playTimelapse(events) {
    if (!events || events.length === 0) return;

    stopTimelapsePlayback();

    // Convert events to strokes if needed
    let frames = events;
    if (events[0] && events[0].type !== 'stroke' && events[0].type !== 'fill') {
        // These are drawing events, need to convert to frames
        frames = parseTimelapseCSV(eventsToCSV(events));
    }

    // Create TimelapsePlayer
    const canvasId = 'timelapse-canvas';
    timelapsePlayer = new TimelapsePlayer(canvasId, frames);

    // Apply initial settings
    timelapsePlayer.setSpeed(parseFloat(elements.timelapseSpeed.value) || 1);
    timelapsePlayer.setIgnoreTimestamps(elements.timelapseIgnoreTime.checked);
    if (elements.timelapseRealTime && elements.timelapseRealTime.checked) {
        // Real-time overrides ignore timestamps setting
        timelapsePlayer.setIgnoreTimestamps(false);
        timelapsePlayer.setRealTime(true);
    }

    // Override updateProgress to work with admin UI
    timelapsePlayer.updateProgress = function() {
        const progress = this.frames.length > 0 ? ((this.currentFrame + 1) / this.frames.length) * 100 : 0;
        if (elements.timelapseSeek) {
            elements.timelapseSeek.value = progress;
        }

        if (elements.timelapseCurrentTime) {
            // Prefer actual frame durations when available (real-time mode), otherwise fall back to equal-interval display
            let currentMs = 0;
            let totalMs = 0;
            if (this.frames && this.frames.length > 0) {
                for (let i = 0; i <= Math.min(this.currentFrame, this.frames.length - 1); i++) {
                    currentMs += (this.frames[i].durationMs || this.frameInterval);
                }
                for (let i = 0; i < this.frames.length; i++) {
                    totalMs += (this.frames[i].durationMs || this.frameInterval);
                }
            }

            const currentSeconds = Math.floor(currentMs / 1000);
            const totalSeconds = Math.ceil(totalMs / 1000);
            elements.timelapseCurrentTime.textContent = formatTime(currentSeconds);
            if (elements.timelapseTotalTime) {
                elements.timelapseTotalTime.textContent = formatTime(totalSeconds);
            }
        }
    };

    // Override updatePlayButton to work with admin UI
    timelapsePlayer.updatePlayButton = function() {
        if (elements.timelapsePlay) {
            elements.timelapsePlay.textContent = this.isPlaying ? '⏸' : '▶️';
        }
    };

    // Override updateSpeedButtons (not needed for admin)
    timelapsePlayer.updateSpeedButtons = function() {};

    // Start playback
    timelapsePlayer.play();
}

/**
 * Convert events array to CSV string
 */
function eventsToCSV(events) {
    if (!events || events.length === 0) return '';

    // Derive headers
    const headers = [];
    events.forEach(ev => {
        Object.keys(ev).forEach(k => {
            if (headers.indexOf(k) === -1) headers.push(k);
        });
    });

    const lines = [];
    lines.push(headers.join(','));

    events.forEach(ev => {
        const row = headers.map(h => {
            let v = ev[h];
            if (v === undefined || v === null) return '';
            if (Array.isArray(v) || typeof v === 'object') v = JSON.stringify(v);
            v = String(v);
            if (v.indexOf(',') !== -1 || v.indexOf('"') !== -1 || v.indexOf('\n') !== -1) {
                v = '"' + v.replace(/"/g, '""') + '"';
            }
            return v;
        });
        lines.push(row.join(','));
    });

    return lines.join('\n');
}

/**
 * Toggle timelapse playback (play/pause)
 */
function toggleTimelapsePlayback() {
    if (!timelapsePlayer) {
        if (state.timelapseEvents && state.timelapseEvents.length > 0) {
            playTimelapse(state.timelapseEvents);
        } else if (state.currentIllustId) {
            loadAndPlayTimelapse(state.currentIllustId);
        }
        return;
    }

    timelapsePlayer.toggle();
}

/**
 * Stop timelapse playback
 */
function stopTimelapsePlayback() {
    if (timelapsePlayer) {
        timelapsePlayer.pause();
        timelapsePlayer = null;
    }

    if (elements.timelapsePlay) {
        elements.timelapsePlay.textContent = '▶️';
    }
    if (elements.timelapseSeek) {
        elements.timelapseSeek.value = 0;
    }
    if (elements.timelapseCurrentTime) {
        elements.timelapseCurrentTime.textContent = '0:00';
    }

    const canvas = elements.timelapseCanvas;
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
}

/**
 * Restart timelapse
 */
function restartTimelapse() {
    if (timelapsePlayer) {
        timelapsePlayer.reset();
        timelapsePlayer.play();
    } else if (state.timelapseEvents.length > 0) {
        playTimelapse(state.timelapseEvents);
    } else if (state.currentIllustId) {
        loadAndPlayTimelapse(state.currentIllustId);
    }
}

/**
 * Format seconds to MM:SS
 */
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// Re-export recordTimelapse from timelapse_recorder for convenience
export { recordTimelapse } from './timelapse_recorder.js';
