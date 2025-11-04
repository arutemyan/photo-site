/**
 * Paint Detail JavaScript
 * イラスト詳細ページのタイムラプス再生機能
 */

class TimelapsePlayer {
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
        
        console.log('TimelapsePlayer initialized with', timelapseData.length, 'frames');
        
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
    
    // Reset player to initial state
    reset() {
        this.pause();
        this.currentFrame = -1;
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        this.updateProgress();
        this.updatePlayButton();
    }    setupCanvas() {
        if (this.frames.length === 0) return;
        
        // キャンバスサイズを設定（デフォルトは800x600）
        const firstFrame = this.frames[0];
        this.canvas.width = firstFrame.width || 800;
        this.canvas.height = firstFrame.height || 600;
        
        console.log('Canvas size:', this.canvas.width, 'x', this.canvas.height);
        
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

// グローバル変数
let timelapsePlayer = null;

/**
 * タイムラプスを読み込んで初期化
 */
async function initTimelapse(illustId) {
    try {
        console.log('Fetching timelapse for illust ID:', illustId);
        const response = await fetch(`/paint/api/timelapse.php?id=${illustId}`);
        const data = await response.json();
        
        console.log('Timelapse API response:', data);
        
        if (!data.success) {
            console.error('Timelapse load error:', data.error);
            hideTimelapseSection();
            return;
        }
        
        let frames = [];
        
        // CSVまたはJSONのパース
        if (data.format === 'csv' && data.csv) {
            console.log('Parsing CSV timelapse data...');
            frames = parseTimelapseCSV(data.csv);
        } else if (data.timelapse) {
            console.log('Using JSON timelapse data...');
            frames = data.timelapse;
        }
        
        console.log('Parsed', frames.length, 'frames');
        
        if (frames.length === 0) {
            console.warn('No frames found');
            hideTimelapseSection();
            return;
        }
        
        // 重いタイムラプスの警告（1000フレーム以上）
        if (frames.length > 1000) {
            console.warn('Large timelapse detected:', frames.length, 'frames. Playback may be slow.');
            // ユーザーに通知（オプション）
            const warningDiv = document.createElement('div');
            warningDiv.style.cssText = 'position: fixed; top: 80px; left: 50%; transform: translateX(-50%); background: #fff3cd; color: #856404; padding: 12px 20px; border: 1px solid #ffeeba; border-radius: 4px; z-index: 10000; box-shadow: 0 2px 8px rgba(0,0,0,0.15);';
            warningDiv.innerHTML = `⚠️ このタイムラプスは${frames.length}フレームあり、再生が重くなる可能性があります。`;
            document.body.appendChild(warningDiv);
            setTimeout(() => warningDiv.remove(), 5000);
        }
        
        showTimelapseSection();
        timelapsePlayer = new TimelapsePlayer('timelapseCanvas', frames);
        
    } catch (error) {
        console.error('Timelapse initialization error:', error);
        hideTimelapseSection();
    }
}

/**
 * CSV形式のタイムラプスをパース（イベントベース）
 */
function parseTimelapseCSV(csv) {
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
    
    console.log('Parsed', events.length, 'drawing events');
    
    // イベントをストロークに変換
    return convertEventsToStrokes(events);
}

/**
 * CSV行をパース
 */
function parseCSVLine(line) {
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
function convertEventsToStrokes(events) {
    const strokes = [];
    let currentStroke = null;
    let lastEventTime = null;
    for (const event of events) {
        if (event.type === 'start') {
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
        }
    }
    
    // 未完のストロークがあれば追加
    if (currentStroke && currentStroke.path.length > 0) {
        strokes.push(currentStroke);
    }
    
    // フレーム間のdurationを計算
    return calculateFrameDurations(strokes);
}

/**
 * ストローク間の時間間隔を考慮したフレームdurationを計算
 */
function calculateFrameDurations(strokes) {
    if (strokes.length === 0) return [];
    
    for (let i = 0; i < strokes.length; i++) {
        let durationMs;
        
        if (i === 0) {
            // 最初のフレーム：即座に描画
            durationMs = 0;
        } else {
            // ストローク間の時間間隔（ミリ秒単位）
            const prevStroke = strokes[i - 1];
            const currentStroke = strokes[i];
            
            let intervalMs = 0;
            if (prevStroke.endTime !== null && currentStroke.startTime !== null) {
                const dt = currentStroke.startTime - prevStroke.endTime;
                if (dt > 0) {
                    intervalMs = dt;
                }
            }
            
            // 間隔を適切な範囲に制限
            const minInterval = 10; // 最小10ms
            const maxInterval = 10000; // 最大10秒
            durationMs = Math.max(minInterval, Math.min(intervalMs, maxInterval));
        }
        
        strokes[i].durationMs = durationMs;
    }
    
    return strokes;
}

/**
 * タイムラプスセクションを表示
 */
function showTimelapseSection() {
    const section = document.getElementById('timelapseSection');
    if (section) {
        section.style.display = 'block';
    }
}

/**
 * タイムラプスセクションを非表示
 */
function hideTimelapseSection() {
    const section = document.getElementById('timelapseSection');
    if (section) {
        section.style.display = 'none';
    }
}

/**
 * 再生/一時停止
 */
function togglePlayback() {
    if (timelapsePlayer) {
        timelapsePlayer.toggle();
    }
}

/**
 * 速度変更
 */
function changeSpeed(speed) {
    if (timelapsePlayer) {
        timelapsePlayer.setSpeed(speed);
    }
}

/**
 * タイムスタンプ無視切り替え
 */
function toggleIgnoreTimestamps(ignore) {
    if (timelapsePlayer) {
        timelapsePlayer.setIgnoreTimestamps(ignore);
    }
}

/**
 * オーバーレイを開く
 */
function openTimelapseOverlay() {
    const overlay = document.getElementById('timelapseOverlay');
    if (overlay) {
        overlay.classList.add('show');
        if (timelapsePlayer) {
            // プレイヤーをリセット（最初から再生できるように）
            timelapsePlayer.reset();
            // キャンバスの表示サイズを調整
            resizeCanvas();
        }
    }
}

/**
 * キャンバスのサイズを調整（オーバーレイ内で適切に表示）
 */
function resizeCanvas() {
    if (!timelapsePlayer) return;
    
    const canvas = timelapsePlayer.canvas;
    const container = canvas.parentElement;
    
    if (!container) return;
    
    // コンテナの幅に合わせてキャンバスを表示
    const containerWidth = container.clientWidth;
    const canvasAspect = canvas.width / canvas.height;
    
    // CSSで表示サイズを設定（実際のキャンバスサイズは変更しない）
    canvas.style.width = '100%';
    canvas.style.height = 'auto';
    canvas.style.maxHeight = '70vh';
    canvas.style.objectFit = 'contain';
}

/**
 * オーバーレイを閉じる
 */
function closeTimelapseOverlay(event) {
    if (event && event.target !== event.currentTarget) return;
    
    const overlay = document.getElementById('timelapseOverlay');
    if (overlay) {
        overlay.classList.remove('show');
        if (timelapsePlayer) {
            timelapsePlayer.pause();
        }
    }
}

/**
 * プログレスバークリックでシーク（即時反映）
 */
document.addEventListener('DOMContentLoaded', () => {
    const progressBar = document.getElementById('timelapseProgress');
    if (progressBar) {
        progressBar.addEventListener('click', (e) => {
            if (!timelapsePlayer) return;
            
            const rect = progressBar.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const percent = x / rect.width;
            let frameIndex = Math.floor(percent * timelapsePlayer.frames.length);
            // Clamp to valid range
            if (frameIndex < 0) frameIndex = 0;
            if (frameIndex >= timelapsePlayer.frames.length) frameIndex = timelapsePlayer.frames.length - 1;

            // シーク実行（即座に反映）
            timelapsePlayer.seek(frameIndex);
        });
    }
    
    // ESCキーでオーバーレイを閉じる
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeTimelapseOverlay();
        }
    });
});
