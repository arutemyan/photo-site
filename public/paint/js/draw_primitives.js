/**
 * Draw primitives shared by timelapse player and editor
 */
import { hexToRgb } from './color_utils.js';

export function drawStrokePrimitive(ctx, frame, layerStates = {}) {
    if (!frame.path || frame.path.length === 0) return;

    if (typeof frame.layer !== 'undefined') {
        const li = Number(frame.layer);
        const st = layerStates[li];
        if (st && st.visible === false) return;
        if (st && typeof st.opacity === 'number') {
            frame._originalOpacity = frame._originalOpacity === undefined ? (frame.opacity !== undefined ? frame.opacity : 1) : frame._originalOpacity;
            frame.opacity = (frame._originalOpacity !== undefined ? frame._originalOpacity : 1) * st.opacity;
        }
    }

    ctx.save();

    if (frame.tool === 'watercolor') {
        const maxRadius = (frame.size || 40) / 2;
        const hardness = (frame.watercolorHardness !== undefined ? frame.watercolorHardness : 50) / 100;
        const baseOpacity = frame.watercolorOpacity || 0.3;

        const colorRgb = hexToRgb(frame.color || '#000000');
        if (!colorRgb) {
            ctx.restore();
            return;
        }

        let totalRadius = 0;
        for (let i = 0; i < frame.path.length; i++) {
            const pt = frame.path[i];
            const pressure = (pt.pressure !== undefined ? pt.pressure : 1);
            const pressuredRadius = maxRadius * (0.5 + 0.5 * pressure);
            totalRadius += pressuredRadius;

            const gradient = ctx.createRadialGradient(pt.x, pt.y, 0, pt.x, pt.y, pressuredRadius);
            const solidStop = hardness * 0.8;

            gradient.addColorStop(0, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${baseOpacity})`);
            if (solidStop > 0) {
                gradient.addColorStop(solidStop, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${baseOpacity})`);
            }

            const midStop = solidStop + (1 - solidStop) * 0.5;
            const midOpacity = baseOpacity * 0.3;
            gradient.addColorStop(midStop, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${midOpacity})`);
            gradient.addColorStop(1, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, 0)`);

            ctx.fillStyle = gradient;
            ctx.globalCompositeOperation = 'source-over';
            ctx.beginPath();
            ctx.arc(pt.x, pt.y, pressuredRadius, 0, Math.PI * 2);
            ctx.fill();
        }

        if (frame.path.length > 1) {
            try {
                ctx.save();
                ctx.globalCompositeOperation = 'source-over';
                const connectorAlpha = Math.max(0.12, Math.min(0.9, baseOpacity * 0.55));
                ctx.strokeStyle = `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${connectorAlpha})`;
                const avgRadius = (frame.path.length > 0) ? (totalRadius / frame.path.length) : maxRadius;
                ctx.lineWidth = Math.max(1, avgRadius * 1.6);
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.beginPath();
                ctx.moveTo(frame.path[0].x, frame.path[0].y);
                for (let i = 1; i < frame.path.length; i++) {
                    ctx.lineTo(frame.path[i].x, frame.path[i].y);
                }
                ctx.stroke();
            } catch (e) {
                console.warn('Connector stroke render failed:', e);
            } finally {
                ctx.restore();
            }
        }
    } else {
        ctx.strokeStyle = frame.color || '#000000';
        ctx.lineWidth = frame.size || 5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.globalAlpha = frame.opacity !== undefined ? frame.opacity : 1;

        if (frame.tool === 'eraser') {
            ctx.globalCompositeOperation = 'destination-out';
        } else {
            ctx.globalCompositeOperation = 'source-over';
        }

        if (frame.path.length === 1) {
            const p = frame.path[0];
            const r = (frame.size || 5) / 2;
            ctx.beginPath();
            ctx.arc(p.x, p.y, Math.max(1, r), 0, Math.PI * 2);
            if (frame.tool === 'eraser') {
                ctx.globalCompositeOperation = 'destination-out';
                ctx.fill();
            } else {
                ctx.fillStyle = frame.color || ctx.strokeStyle;
                ctx.fill();
            }
        } else {
            ctx.beginPath();
            ctx.moveTo(frame.path[0].x, frame.path[0].y);
            for (let i = 1; i < frame.path.length; i++) {
                ctx.lineTo(frame.path[i].x, frame.path[i].y);
            }
            ctx.stroke();
        }
    }

    ctx.restore();
}

export function drawFillPrimitive(ctx, frame, canvasWidth, canvasHeight, layerStates = {}, options = {}) {
    if (frame.x === undefined || frame.y === undefined) return;
    if (typeof frame.layer !== 'undefined') {
        const li = Number(frame.layer);
        const st = layerStates[li];
        if (st && st.visible === false) return;
        if (st && typeof st.opacity === 'number') {
            frame._originalOpacity = frame._originalOpacity === undefined ? (frame.opacity !== undefined ? frame.opacity : 1) : frame._originalOpacity;
            frame.opacity = (frame._originalOpacity !== undefined ? frame._originalOpacity : 1) * st.opacity;
        }
    }

    // Flood fill implementation that operates on the provided context
    const imageData = ctx.getImageData(0, 0, canvasWidth, canvasHeight);
    const data = imageData.data;

    const startX = Math.floor(frame.x);
    const startY = Math.floor(frame.y);

    if (startX < 0 || startX >= canvasWidth || startY < 0 || startY >= canvasHeight) {
        return;
    }

    const startPos = (startY * canvasWidth + startX) * 4;
    const targetR = data[startPos];
    const targetG = data[startPos + 1];
    const targetB = data[startPos + 2];
    const targetA = data[startPos + 3];

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

    // tolerance: allow slight RGB differences (used by editor bucket tool)
    const tolerance = Number(options.tolerance) || 0;
    function colorMatchLocal(c1, c2, tol) {
        if (!c2) return false;
        return Math.abs(c1.r - c2.r) <= tol &&
            Math.abs(c1.g - c2.g) <= tol &&
            Math.abs(c1.b - c2.b) <= tol;
    }

    if (targetA === 255 && colorMatchLocal({ r: targetR, g: targetG, b: targetB }, { r: fillR, g: fillG, b: fillB }, tolerance)) {
        return;
    }

    const stack = [[startX, startY]];
    const visited = new Set();

    while (stack.length > 0) {
        const [x, y] = stack.pop();
        if (x < 0 || x >= canvasWidth || y < 0 || y >= canvasHeight) continue;
        const key = `${x},${y}`;
        if (visited.has(key)) continue;
        visited.add(key);

        const pos = (y * canvasWidth + x) * 4;
    const current = { r: data[pos], g: data[pos + 1], b: data[pos + 2], a: data[pos + 3] };
    if (!colorMatchLocal(current, { r: targetR, g: targetG, b: targetB }, tolerance)) continue;

        data[pos] = fillR;
        data[pos + 1] = fillG;
        data[pos + 2] = fillB;
        data[pos + 3] = 255;

        stack.push([x + 1, y]);
        stack.push([x - 1, y]);
        stack.push([x, y + 1]);
        stack.push([x, y - 1]);
    }

    ctx.putImageData(imageData, 0, 0);
}
