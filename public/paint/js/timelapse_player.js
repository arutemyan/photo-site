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

        // 白背景で初期化（何も描画しない）
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        // 初期状態の進捗を更新
        this.updateProgress();
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
            this.drawStroke(frame);
        } else if (frame.type === 'fill') {
            this.drawFill(frame);
        } else {
            console.warn('Unexpected frame type:', frame.type);
        }
    }

    drawStroke(frame) {
        if (!frame.path || frame.path.length === 0) return;

        this.ctx.save();
        this.ctx.strokeStyle = frame.color || '#000000';
        this.ctx.lineWidth = frame.size || 5;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.globalAlpha = frame.opacity !== undefined ? frame.opacity : 1;

        if (frame.tool === 'eraser') {
            this.ctx.globalCompositeOperation = 'destination-out';
        } else {
            this.ctx.globalCompositeOperation = 'source-over';
        }

        this.ctx.beginPath();
        this.ctx.moveTo(frame.path[0].x, frame.path[0].y);

        for (let i = 1; i < frame.path.length; i++) {
            this.ctx.lineTo(frame.path[i].x, frame.path[i].y);
        }

        this.ctx.stroke();
        this.ctx.restore();
    }

    drawFill(frame) {
        if (frame.x === undefined || frame.y === undefined) return;

        // Get image data for flood fill
        const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        const data = imageData.data;

        const startX = Math.floor(frame.x);
        const startY = Math.floor(frame.y);

        // Check if coordinates are within bounds
        if (startX < 0 || startX >= this.canvas.width || startY < 0 || startY >= this.canvas.height) {
            return;
        }

        // Get target color
        const startPos = (startY * this.canvas.width + startX) * 4;
        const targetR = data[startPos];
        const targetG = data[startPos + 1];
        const targetB = data[startPos + 2];
        const targetA = data[startPos + 3];

        // Parse fill color
        const fillColor = frame.color || '#000000';
        let fillR, fillG, fillB;
        if (fillColor.startsWith('#')) {
            const hex = fillColor.substring(1);
            fillR = parseInt(hex.substring(0, 2), 16);
            fillG = parseInt(hex.substring(2, 4), 16);
            fillB = parseInt(hex.substring(4, 6), 16);
        } else {
            fillR = fillG = fillB = 0;
        }

        // Check if target and fill colors are the same
        if (targetR === fillR && targetG === fillG && targetB === fillB && targetA === 255) {
            return; // Nothing to fill
        }

        // Flood fill using stack
        const stack = [[startX, startY]];
        const visited = new Set();

        while (stack.length > 0) {
            const [x, y] = stack.pop();

            // Check bounds
            if (x < 0 || x >= this.canvas.width || y < 0 || y >= this.canvas.height) {
                continue;
            }

            // Check if already visited
            const key = `${x},${y}`;
            if (visited.has(key)) {
                continue;
            }
            visited.add(key);

            // Check if pixel matches target color
            const pos = (y * this.canvas.width + x) * 4;
            if (data[pos] !== targetR || data[pos + 1] !== targetG ||
                data[pos + 2] !== targetB || data[pos + 3] !== targetA) {
                continue;
            }

            // Fill pixel
            data[pos] = fillR;
            data[pos + 1] = fillG;
            data[pos + 2] = fillB;
            data[pos + 3] = 255;

            // Add neighbors
            stack.push([x + 1, y]);
            stack.push([x - 1, y]);
            stack.push([x, y + 1]);
            stack.push([x, y - 1]);
        }

        // Put the modified image data back
        this.ctx.putImageData(imageData, 0, 0);
    }

    seek(frameIndex) {
        if (frameIndex < 0) frameIndex = 0;
        if (frameIndex >= this.frames.length) frameIndex = this.frames.length - 1;

        // 白背景で初期化
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

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

/**
 * CSV形式のタイムラプスをパース（イベントベース）
 */
export function parseTimelapseCSV(csv) {
    const lines = csv.trim().split('\n');
    if (lines.length === 0) return [];

    const events = [];
    let headers = [];

    // ヘッダー行をパース
    const headerLine = lines[0].trim();
    headers = parseCSVLine(headerLine);

    // データ行をパース
    for (let i = 1; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line) continue;

        const values = parseCSVLine(line);
        const event = {};

        headers.forEach((header, index) => {
            const value = values[index];
            if (value !== undefined && value !== '') {
                event[header] = value;
            }
        });

        // 数値型に変換
        if (event.t) event.t = parseFloat(event.t);
        if (event.x) event.x = parseFloat(event.x);
        if (event.y) event.y = parseFloat(event.y);
        if (event.size) event.size = parseFloat(event.size);
        if (event.layer) event.layer = parseInt(event.layer);

        events.push(event);
    }

    // Parsed drawing events

    // イベントをストロークに変換
    return convertEventsToStrokes(events);
}

/**
 * CSV行をパース
 */
export function parseCSVLine(line) {
    const values = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];

        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            values.push(current);
            current = '';
        } else {
            current += char;
        }
    }
    values.push(current);

    return values;
}

/**
 * 描画イベントをストロークフレームに変換
 */
export function convertEventsToStrokes(events) {
    const strokes = [];
    let currentStroke = null;
    let lastEventTime = null;
    let canvasWidth = 800;
    let canvasHeight = 600;

    for (const event of events) {
        if (event.type === 'meta') {
            // Extract canvas metadata
            if (event.canvas_width) canvasWidth = parseInt(event.canvas_width);
            if (event.canvas_height) canvasHeight = parseInt(event.canvas_height);
            // Canvas metadata found
            continue; // Skip meta events in stroke generation
        } else if (event.type === 'start') {
            // 新しいストロークを開始
            currentStroke = {
                type: 'stroke',
                color: event.color || '#000000',
                size: event.size || 5,
                tool: event.tool || 'pen',
                layer: event.layer || 0,
                path: [{ x: event.x, y: event.y }],
                // 開始時刻と終了時刻を保持
                startTime: event.t !== undefined ? event.t : null,
                endTime: null
            };
            lastEventTime = event.t !== undefined ? event.t : lastEventTime;
        } else if (event.type === 'move' && currentStroke) {
            // ストロークにポイントを追加
            currentStroke.path.push({ x: event.x, y: event.y });
            if (event.t !== undefined) lastEventTime = event.t;
        } else if (event.type === 'end' && currentStroke) {
            // ストロークを完成
            if (event.x !== undefined && event.y !== undefined) {
                currentStroke.path.push({ x: event.x, y: event.y });
            }
            currentStroke.endTime = event.t !== undefined ? event.t : lastEventTime;

            strokes.push(currentStroke);
            currentStroke = null;
        } else if (event.type === 'fill') {
            // 塗りつぶしイベントを追加
            strokes.push({
                type: 'fill',
                x: event.x,
                y: event.y,
                color: event.color || '#000000',
                layer: event.layer || 0,
                startTime: event.t !== undefined ? event.t : lastEventTime,
                endTime: event.t !== undefined ? event.t : lastEventTime
            });
            if (event.t !== undefined) lastEventTime = event.t;
        }
    }

    // 未完のストロークがあれば追加
    if (currentStroke && currentStroke.path.length > 0) {
        strokes.push(currentStroke);
    }

    // Add canvas size to the first stroke
    if (strokes.length > 0) {
        strokes[0].width = canvasWidth;
        strokes[0].height = canvasHeight;
    // Canvas size added to first stroke
    }

    // フレーム間のdurationを計算
    return calculateFrameDurations(strokes);
}

/**
 * ストローク間の時間間隔を考慮したフレームdurationを計算
 */
export function calculateFrameDurations(strokes) {
    if (strokes.length === 0) return [];

    // Default behavior: compute durations based on timestamps but do not assume "real-time excluding pauses".
    return normalizeFrameDurations(strokes, { realTime: false });
}

/**
 * Recompute frame durations from startTime/endTime.
 * Options:
 *  - realTime: if true, use recorded intervals but exclude long pauses (set them to 0)
 *  - pauseThresholdMs: gap (ms) above which an interval is considered a pause and excluded
 *  - minInterval / maxInterval: clamps for durations when not excluded
 */
export function normalizeFrameDurations(strokes, options = {}) {
    const realTime = !!options.realTime;
    const pauseThresholdMs = options.pauseThresholdMs || 2000;
    const minInterval = options.minInterval || 10;
    const maxInterval = options.maxInterval || 10000;

    if (!strokes || strokes.length === 0) return strokes;

    for (let i = 0; i < strokes.length; i++) {
        let durationMs = 0;

        if (i === 0) {
            durationMs = 0;
        } else {
            const prevStroke = strokes[i - 1];
            const currentStroke = strokes[i];

            let intervalMs = 0;
            if (prevStroke && prevStroke.endTime !== null && currentStroke && currentStroke.startTime !== null) {
                const dt = currentStroke.startTime - prevStroke.endTime;
                if (dt > 0) intervalMs = dt;
            }

            if (realTime) {
                // If the gap is larger than the pause threshold, treat it as pause and exclude it
                if (intervalMs > pauseThresholdMs) {
                    intervalMs = 0;
                }
                // Allow zero-interval (immediate) or small real intervals
                durationMs = Math.max(0, Math.min(intervalMs, maxInterval));
            } else {
                // Legacy: clamp into reasonable range
                durationMs = Math.max(minInterval, Math.min(intervalMs, maxInterval));
            }
        }

        strokes[i].durationMs = durationMs;
    }

    return strokes;
}
