/**
 * Timelapse Player - Shared Module
 * フレームベースのタイムラプス再生システム
 *
 * Features:
 * - Frame-based playback with accurate timing
 * - Fast seek to any frame
 * - Speed control (0.25x to 4x)
 * - Flood fill support
 * - CSV/JSON format support
 */

import { parseTimelapseCSV, parseCSVLine, convertEventsToStrokes, calculateFrameDurations, normalizeFrameDurations } from './timelapse_utils.js';
import { hexToRgb } from './color_utils.js';
import { drawStrokePrimitive, drawFillPrimitive } from './draw_primitives.js';

export class TimelapsePlayer {
    constructor(canvasId, timelapseData) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) {
            console.error('Canvas not found:', canvasId);
            return;
        }

        // アルファチャンネルなしのコンテキストを取得（常に不透明な白背景）
        this.ctx = this.canvas.getContext('2d', { alpha: false });
        this.frames = timelapseData;
        // Track per-layer state and create offscreen canvases for full compositing.
        // We'll infer layer count from timelapseData when possible.
        this.layerStates = {}; // { [layerIndex]: { visible: true/false, opacity: number } }
        this.layerOrder = null; // array of layer indexes in compositing order
        this.layerCanvases = []; // offscreen canvases per layer
        this.layerContexts = []; // their 2D contexts
        this.baseCanvas = null; // flattened snapshot canvas
        this.baseCtx = null;
        // -1 を初期値として、まだ何も描画していない状態を表す
        // これにより再生開始前はプログレスが0%のままになる
        this.currentFrame = -1;
        this.isPlaying = false;
        this.speed = 1;
        this.animationId = null;
        this.lastFrameTime = 0;
        this.frameInterval = 50; // 基本フレーム間隔(ms)
        this.ignoreTimestamps = true; // デフォルトで等間隔再生（安定性重視）
        this.realTime = false; // リアル時間再生（ただし中断時間は除外できる）
        this.pauseThreshold = 2000; // 中断とみなす閾値(ms)（これより大きいギャップは除外される）

    // TimelapsePlayer initialized

        this.setupCanvas();
    }

    // Helper: get duration (ms) for a given frame index
    getFrameDuration(index) {
        if (this.ignoreTimestamps) return this.frameInterval;
        if (!this.frames || this.frames.length === 0) return this.frameInterval;
        const f = this.frames[index];
        if (f && typeof f.durationMs === 'number' && f.durationMs >= 0) {
            return f.durationMs;
        }
        return this.frameInterval;
    }

    // Set whether to ignore timestamps (equal-interval playback)
    setIgnoreTimestamps(ignore) {
        this.ignoreTimestamps = ignore;
    }

    // Enable/disable real-time playback (uses recorded intervals but can exclude long pauses)
    setRealTime(enabled) {
        this.realTime = !!enabled;
        // Re-normalize frame durations if frames contain timing info
        if (this.frames && this.frames.length > 0) {
            // Use exported normalizer to recompute durations taking pauseThreshold into account
            try {
                // normalizeFrameDurations is exported below
                const normalized = normalizeFrameDurations(this.frames, { realTime: this.realTime, pauseThresholdMs: this.pauseThreshold });
                this.frames = normalized;
            } catch (e) {
                // If normalization fails, keep existing durations
                console.warn('Failed to normalize frame durations:', e);
            }
        }
    }

    setPauseThreshold(ms) {
        this.pauseThreshold = Number(ms) || this.pauseThreshold;
        if (this.realTime) this.setRealTime(true);
    }

    // Reset player to initial state
    reset() {
        this.pause();
        this.currentFrame = -1;
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        this.updateProgress();
        this.updatePlayButton();
    }

    setupCanvas() {
        if (this.frames.length === 0) return;

        // キャンバスサイズを設定（デフォルトは800x600）
        const firstFrame = this.frames[0];
        this.canvas.width = firstFrame.width || 800;
        this.canvas.height = firstFrame.height || 600;

        // Canvas size set

        // Prepare base canvas (for snapshots) and per-layer offscreen canvases.
        this.baseCanvas = document.createElement('canvas');
        this.baseCanvas.width = this.canvas.width;
        this.baseCanvas.height = this.canvas.height;
        this.baseCtx = this.baseCanvas.getContext('2d', { alpha: false });
        // initialize base white
        this.baseCtx.fillStyle = '#ffffff';
        this.baseCtx.fillRect(0, 0, this.baseCanvas.width, this.baseCanvas.height);

        // Infer layer count from frames (find max layer index)
        let maxLayer = -1;
        for (const f of this.frames) {
            if (f && typeof f.layer === 'number') maxLayer = Math.max(maxLayer, f.layer);
        }
        const layerCount = Math.max(1, maxLayer + 1);

        // create offscreen canvases for each layer
        this.layerCanvases = [];
        this.layerContexts = [];
        for (let i = 0; i < layerCount; i++) {
            const c = document.createElement('canvas');
            c.width = this.canvas.width;
            c.height = this.canvas.height;
            const ctx = c.getContext('2d', { willReadFrequently: true });
            // start with transparent content
            ctx.clearRect(0, 0, c.width, c.height);
            this.layerCanvases.push(c);
            this.layerContexts.push(ctx);
            // default state
            this.layerStates[i] = { visible: true, opacity: 1 };
        }

        // default layerOrder: bottom-to-top 0..n-1
        this.layerOrder = Array.from({ length: layerCount }, (_, i) => i);

        // Initialize main canvas to white background
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        // 初期状態の進捗を更新
        this.updateProgress();
    }

    /**
     * Sync layer count/order/visibility/opacity from editor state object.
     * The editor state is expected to have a `layers` array of canvas elements
     * and optionally `layerNames`. This will ensure the player's internal
     * per-layer canvases and layerStates match the editor so timelapse playback
     * reflects the current editor view when opened.
     */
    syncLayersFromEditor(editorState) {
        try {
            if (!editorState || !Array.isArray(editorState.layers)) return;

            const editorLayers = editorState.layers;
            const count = editorLayers.length;

            // Ensure we have at least as many offscreen canvases/contexts
            while (this.layerCanvases.length < count) {
                const c = document.createElement('canvas');
                c.width = this.canvas.width;
                c.height = this.canvas.height;
                const ctx = c.getContext('2d', { willReadFrequently: true });
                ctx.clearRect(0, 0, c.width, c.height);
                this.layerCanvases.push(c);
                this.layerContexts.push(ctx);
            }

            // Build layerStates from editor DOM canvas styles
            for (let i = 0; i < count; i++) {
                const el = editorLayers[i];
                if (!el) continue;
                const visible = el.style && el.style.display === 'none' ? false : true;
                const opacity = el.style && el.style.opacity ? parseFloat(el.style.opacity) : 1;
                this.layerStates[i] = { visible: !!visible, opacity: typeof opacity === 'number' ? opacity : 1 };
            }

            // Set layerOrder according to editor state. Assume editor.state.layers is
            // bottom-to-top; keep same order unless the editor exposes a different order.
            this.layerOrder = Array.from({ length: count }, (_, i) => i);
            
            // Copy current editor canvases into player offscreen canvases so the
            // initial composite matches the editor's visible content. This also
            // makes visibility/opacity/reorder frames meaningful immediately.
            try {
                for (let i = 0; i < count; i++) {
                    const src = editorLayers[i];
                    const dstCtx = this.layerContexts[i];
                    if (!src || !dstCtx) continue;
                    // Ensure destination canvas matches size
                    const dst = this.layerCanvases[i];
                    if (dst.width !== src.width || dst.height !== src.height) {
                        dst.width = src.width;
                        dst.height = src.height;
                    }
                    dstCtx.clearRect(0, 0, dst.width, dst.height);
                    try {
                        dstCtx.globalAlpha = 1;
                        dstCtx.drawImage(src, 0, 0, dst.width, dst.height);
                        dstCtx.globalAlpha = 1;
                    } catch (e) {
                        // drawImage can throw if source canvas is tainted; ignore
                    }
                }
            } catch (e) {
                // non-fatal
            }
        } catch (e) {
            console.warn('Failed to sync layers from editor state:', e);
        }
    }

    // Composite base + layers onto main canvas according to current layerOrder and states
    compositeToMain() {
        // clear main
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        // draw base (snapshot)
        if (this.baseCanvas) {
            this.ctx.drawImage(this.baseCanvas, 0, 0, this.canvas.width, this.canvas.height);
        }

        // draw layers in order
        if (this.layerOrder && this.layerOrder.length > 0) {
            for (const li of this.layerOrder) {
                const layerCanvas = this.layerCanvases[li];
                const st = this.layerStates[li] || { visible: true, opacity: 1 };
                if (!layerCanvas) continue;
                if (st.visible === false) continue;
                this.ctx.globalAlpha = typeof st.opacity === 'number' ? st.opacity : 1;
                this.ctx.drawImage(layerCanvas, 0, 0, this.canvas.width, this.canvas.height);
                this.ctx.globalAlpha = 1;
            }
        } else {
            // fallback: natural order
            for (let li = 0; li < this.layerCanvases.length; li++) {
                const layerCanvas = this.layerCanvases[li];
                const st = this.layerStates[li] || { visible: true, opacity: 1 };
                if (!layerCanvas) continue;
                if (st.visible === false) continue;
                this.ctx.globalAlpha = typeof st.opacity === 'number' ? st.opacity : 1;
                this.ctx.drawImage(layerCanvas, 0, 0, this.canvas.width, this.canvas.height);
                this.ctx.globalAlpha = 1;
            }
        }
    }

    play() {
        if (this.isPlaying) return;

        // 最後まで再生済みの場合は最初から再生
        if (this.currentFrame >= this.frames.length - 1) {
            this.seek(0);
        }

        // If not started yet, start from frame 0 and render it
        if (this.currentFrame < 0) {
            this.seek(0);
        }

        this.isPlaying = true;
        this.lastFrameTime = performance.now();
        this.animate();
        this.updatePlayButton();
    }

    pause() {
        this.isPlaying = false;
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
        this.updatePlayButton();
    }

    toggle() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    }

    animate() {
        if (!this.isPlaying) return;

        const now = performance.now();
        const deltaTime = now - this.lastFrameTime;

        // 次のフレームを表示するまでの待ち時間を取得
        // currentFrame が -1 の場合は 0 フレーム目の duration を使う
        const nextFrameIndex = this.currentFrame + 1;
        if (nextFrameIndex >= this.frames.length) {
            // 最後まで到達
            this.pause();
            return;
        }

        // 速度に応じたフレーム間隔
        const duration = this.getFrameDuration(nextFrameIndex) / this.speed;

        if (deltaTime >= duration) {
            this.nextFrame();
            this.lastFrameTime = now - (deltaTime % duration);
        }

        this.animationId = requestAnimationFrame(() => this.animate());
    }

    nextFrame() {
        this.currentFrame++;
        if (this.currentFrame >= this.frames.length) {
            // 最後まで到達したら停止（set to last index and pause)
            this.currentFrame = this.frames.length - 1;
            this.renderFrame(this.currentFrame);
            this.updateProgress();
            this.pause();
            return;
        }
        this.renderFrame(this.currentFrame);
        this.updateProgress();
    }

    renderFrame(frameIndex) {
        if (frameIndex < 0 || frameIndex >= this.frames.length) return;

        const frame = this.frames[frameIndex];
        // ストロークを描画
        if (frame.type === 'stroke') {
            const li = typeof frame.layer === 'number' ? frame.layer : 0;
            const targetCtx = (this.layerContexts[li] || this.ctx);
            this.drawStroke(frame, targetCtx);
            this.compositeToMain();
        } else if (frame.type === 'fill') {
            const li = typeof frame.layer === 'number' ? frame.layer : 0;
            const targetCtx = (this.layerContexts[li] || this.ctx);
            this.drawFill(frame, targetCtx);
            this.compositeToMain();
        } else if (frame.type === 'snapshot') {
            // snapshot: flattened image data URL - draw into baseCanvas and clear layer canvases
            try {
                const img = new Image();
                img.onload = () => {
                    if (!this.baseCanvas) {
                        this.baseCanvas = document.createElement('canvas');
                        this.baseCanvas.width = this.canvas.width;
                        this.baseCanvas.height = this.canvas.height;
                        this.baseCtx = this.baseCanvas.getContext('2d', { alpha: false });
                    }
                    this.baseCtx.clearRect(0, 0, this.baseCanvas.width, this.baseCanvas.height);
                    this.baseCtx.drawImage(img, 0, 0, this.baseCanvas.width, this.baseCanvas.height);
                    // clear per-layer canvases to avoid double-draw
                    for (const ctx of this.layerContexts) {
                        try { ctx.clearRect(0, 0, this.canvas.width, this.canvas.height); } catch (e) {}
                    }
                    this.compositeToMain();
                };
                img.onerror = (e) => {
                    console.warn('Failed to load snapshot image for timelapse frame', e);
                };
                img.src = frame.data;
            } catch (e) {
                console.warn('Error rendering snapshot frame:', e);
            }
        } else if (frame.type === 'reorder') {
            try {
                if (Array.isArray(frame.order)) {
                    this.layerOrder = frame.order.slice();
                } else if (typeof frame.from === 'number' && typeof frame.to === 'number') {
                    if (!this.layerOrder) {
                        let maxLayer = -1;
                        for (const f of this.frames) {
                            if (typeof f.layer === 'number') maxLayer = Math.max(maxLayer, f.layer);
                        }
                        this.layerOrder = Array.from({ length: Math.max(1, maxLayer + 1) }, (_, i) => i);
                    }
                    const from = frame.from;
                    const to = frame.to;
                    if (from >= 0 && to >= 0 && from < this.layerOrder.length && to < this.layerOrder.length) {
                        const item = this.layerOrder.splice(from, 1)[0];
                        this.layerOrder.splice(to, 0, item);
                    }
                }
            } catch (e) {
                console.warn('Failed to apply reorder frame:', e);
            }
            this.compositeToMain();
        } else if (frame.type === 'visibility') {
            try {
                const li = Number(frame.layer);
                if (!Number.isNaN(li)) {
                    if (!this.layerStates[li]) this.layerStates[li] = { visible: true, opacity: 1 };
                    this.layerStates[li].visible = !!frame.visible;
                }
            } catch (e) {
                console.warn('Failed to apply visibility frame:', e);
            }
            this.compositeToMain();
        } else if (frame.type === 'opacity') {
            try {
                const li = Number(frame.layer);
                if (!Number.isNaN(li)) {
                    if (!this.layerStates[li]) this.layerStates[li] = { visible: true, opacity: 1 };
                    const op = parseFloat(frame.opacity);
                    if (!Number.isNaN(op)) this.layerStates[li].opacity = op;
                }
            } catch (e) {
                console.warn('Failed to apply opacity frame:', e);
            }
            this.compositeToMain();
        } else {
            console.warn('Unexpected frame type:', frame.type);
        }
    }

    drawStroke(frame, targetCtx = null) {
        const ctx = targetCtx || this.ctx;
        drawStrokePrimitive(ctx, frame, this.layerStates);
    }

    /* hexToRgb moved to public/paint/js/color_utils.js */

    drawFill(frame, targetCtx = null) {
        if (frame.x === undefined || frame.y === undefined) return;
        if (typeof frame.layer !== 'undefined') {
            const li = Number(frame.layer);
            const st = this.layerStates[li];
            if (st && st.visible === false) return;
            if (st && typeof st.opacity === 'number') {
                frame._originalOpacity = frame._originalOpacity === undefined ? (frame.opacity !== undefined ? frame.opacity : 1) : frame._originalOpacity;
                frame.opacity = (frame._originalOpacity !== undefined ? frame._originalOpacity : 1) * st.opacity;
            }
        }
        const ctx = targetCtx || this.ctx;
        drawFillPrimitive(ctx, frame, this.canvas.width, this.canvas.height, this.layerStates);
    }

    seek(frameIndex) {
        if (frameIndex < 0) frameIndex = 0;
        if (frameIndex >= this.frames.length) frameIndex = this.frames.length - 1;

        // 白背景で初期化
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        // Reset base and per-layer canvases to rebuild state up to target frame
        if (this.baseCtx) {
            try { this.baseCtx.clearRect(0, 0, this.baseCanvas.width, this.baseCanvas.height); } catch (e) {}
            // ensure base white background
            try { this.baseCtx.fillStyle = '#ffffff'; this.baseCtx.fillRect(0, 0, this.baseCanvas.width, this.baseCanvas.height); } catch (e) {}
        }
        for (const ctx of this.layerContexts) {
            try { ctx.clearRect(0, 0, this.canvas.width, this.canvas.height); } catch (e) {}
        }
        // reset layerStates defaults
        for (let i = 0; i < this.layerCanvases.length; i++) {
            if (!this.layerStates[i]) this.layerStates[i] = { visible: true, opacity: 1 };
        }

        // フレーム0の場合も含め、指定フレームまで（inclusive）描画する
        if (frameIndex >= 0) {
            for (let i = 0; i <= frameIndex; i++) {
                this.renderFrame(i);
            }
        }

        this.currentFrame = frameIndex;
        this.updateProgress();
    }

    setSpeed(speed) {
        this.speed = speed;
        // 再生中の場合、新しい速度で即座に反映
        // animateループ内で自動的に新しい速度が適用される
        this.updateSpeedButtons();
    }

    updateProgress() {
        // 表示上は "現在のフレームを含めた割合" を用いる（最後のフレームで100%になるように）
        const progress = this.frames.length > 0 ? ((this.currentFrame + 1) / this.frames.length) * 100 : 0;
        const progressBar = document.getElementById('timelapseProgressBar');
        if (progressBar) {
            progressBar.style.width = progress + '%';
        }

        const timeDisplay = document.getElementById('timelapseTime');
        if (timeDisplay) {
            // フレーム番号を秒数に変換（50msごと = 20fps）
            const currentSeconds = Math.floor((this.currentFrame + 1) * this.frameInterval / 1000);
            const totalSeconds = Math.ceil(this.frames.length * this.frameInterval / 1000);
            timeDisplay.textContent = `${this.formatTime(currentSeconds)} / ${this.formatTime(totalSeconds)}`;
        }
    }

    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    updatePlayButton() {
        const playBtn = document.getElementById('timelapsePlayBtn');
        if (playBtn) {
            playBtn.innerHTML = this.isPlaying ? '⏸' : '▶';
        }
    }

    updateSpeedButtons() {
        document.querySelectorAll('.speed-btn').forEach(btn => {
            btn.classList.toggle('active', parseFloat(btn.dataset.speed) === this.speed);
        });
    }
}

/* CSV functions moved to ./timelapse_utils.js */
