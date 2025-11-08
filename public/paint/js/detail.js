/**
 * Paint Detail JavaScript
 * イラスト詳細ページのタイムラプス再生機能
 */

import { TimelapsePlayer, parseTimelapseCSV } from './timelapse_player.js';

// グローバル変数
let timelapsePlayer = null;

/**
 * タイムラプスを読み込んで初期化
 */
export async function initTimelapse(illustId) {
    try {
    // Fetching timelapse for given ID
        const response = await fetch(`/paint/api/timelapse.php?id=${illustId}`);
        const data = await response.json();
        
    // Timelapse API response received
        
        if (!data.success) {
            console.error('Timelapse load error:', data.error);
            hideTimelapseSection();
            return;
        }
        
        let frames = [];
        
        // CSVまたはJSONのパース
        if (data.format === 'csv' && data.csv) {
            // Parsing CSV timelapse data
            frames = parseTimelapseCSV(data.csv);
        } else if (data.timelapse) {
            // Using JSON timelapse data
            frames = data.timelapse;
        }
        
    // Parsed frames count
        
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

// Make functions available globally for onclick handlers in HTML
if (typeof window !== 'undefined') {
    window.togglePlayback = togglePlayback;
    window.changeSpeed = changeSpeed;
    window.toggleIgnoreTimestamps = toggleIgnoreTimestamps;
    window.openTimelapseOverlay = openTimelapseOverlay;
    window.closeTimelapseOverlay = closeTimelapseOverlay;
}
