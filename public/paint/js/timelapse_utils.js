/**
 * Timelapse Utilities
 * 共通化された CSV パース、イベント変換、フレーム期間計算など
 */

export function parseTimelapseCSV(csv) {
    const lines = csv.trim().split('\n');
    if (lines.length === 0) return [];

    const events = [];
    let headers = [];

    // ヘッダー行をパース
    const headerLine = lines[0].trim();
    headers = parseCSVLine(headerLine);

    const numericHeader = headers.length > 0 && headers.every(h => /^\d+$/.test(h));

    if (numericHeader) {
        for (let i = 1; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;

            const values = parseCSVLine(line);
            for (let v of values) {
                if (!v) continue;
                try {
                    const obj = JSON.parse(v);
                    events.push(obj);
                } catch (e) {
                    try {
                        const repaired = v.replace(/""/g, '"');
                        const obj2 = JSON.parse(repaired);
                        events.push(obj2);
                    } catch (e2) {
                        events.push({ raw: v });
                    }
                }
            }
        }
    } else {
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

            if (event.t) event.t = parseFloat(event.t);
            if (event.x) event.x = parseFloat(event.x);
            if (event.y) event.y = parseFloat(event.y);
            if (event.size) event.size = parseFloat(event.size);
            if (event.layer) event.layer = parseInt(event.layer);
            if (event.pressure !== undefined && event.pressure !== '') {
                const p = parseFloat(event.pressure);
                if (!Number.isNaN(p)) event.pressure = p;
            }

            events.push(event);
        }
    }

    return convertEventsToStrokes(events);
}

export function parseCSVLine(line) {
    const values = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];

        if (char === '"') {
            if (inQuotes && i + 1 < line.length && line[i + 1] === '"') {
                current += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
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

export function convertEventsToStrokes(events) {
    const strokes = [];
    let currentStroke = null;
    let lastEventTime = null;
    let canvasWidth = 800;
    let canvasHeight = 600;

    for (const event of events) {
        if (event.type === 'meta') {
            if (event.canvas_width) canvasWidth = parseInt(event.canvas_width);
            if (event.canvas_height) canvasHeight = parseInt(event.canvas_height);
            continue;
        } else if (event.type === 'start') {
            currentStroke = {
                type: 'stroke',
                color: event.color || '#000000',
                size: event.size || 5,
                tool: event.tool || 'pen',
                layer: event.layer || 0,
                path: [{ x: event.x, y: event.y, pressure: (event.pressure !== undefined ? parseFloat(event.pressure) : 1) }],
                startTime: event.t !== undefined ? event.t : null,
                endTime: null
            };
            if (event.tool === 'watercolor') {
                if (event.watercolorHardness !== undefined) currentStroke.watercolorHardness = event.watercolorHardness;
                if (event.watercolorOpacity !== undefined) currentStroke.watercolorOpacity = event.watercolorOpacity;
            }
            lastEventTime = event.t !== undefined ? event.t : lastEventTime;
        } else if (event.type === 'move' && currentStroke) {
            const lastPt = currentStroke.path.length > 0 ? currentStroke.path[currentStroke.path.length - 1] : { x: event.x, y: event.y, pressure: (event.pressure !== undefined ? event.pressure : 1) };
            const lastPressure = (lastPt.pressure !== undefined ? parseFloat(lastPt.pressure) : 1);
            const eventPressure = (event.pressure !== undefined ? parseFloat(event.pressure) : lastPressure);
            const samples = sampleInterpolatedPoints(lastPt, { x: event.x, y: event.y }, lastPressure, eventPressure, 2);
            for (const pt of samples) currentStroke.path.push(pt);
            if (event.t !== undefined) lastEventTime = event.t;
        } else if (event.type === 'end' && currentStroke) {
            if (event.x !== undefined && event.y !== undefined) {
                const lastPt = currentStroke.path.length > 0 ? currentStroke.path[currentStroke.path.length - 1] : { x: event.x, y: event.y, pressure: (event.pressure !== undefined ? event.pressure : 1) };
                const lastPressure = (lastPt.pressure !== undefined ? parseFloat(lastPt.pressure) : 1);
                const eventPressure = (event.pressure !== undefined ? parseFloat(event.pressure) : lastPressure);
                const samples = sampleInterpolatedPoints(lastPt, { x: event.x, y: event.y }, lastPressure, eventPressure, 2);
                for (const pt of samples) currentStroke.path.push(pt);
            }
            currentStroke.endTime = event.t !== undefined ? event.t : lastEventTime;

            strokes.push(currentStroke);
            currentStroke = null;
        } else if (event.type === 'fill') {
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
        } else if (event.type === 'reorder' || event.type === 'visibility' || event.type === 'opacity' || event.type === 'blend' || event.type === 'snapshot') {
            const ctrl = Object.assign({}, event);
            if (ctrl.layer !== undefined) ctrl.layer = Number(ctrl.layer);
            if (ctrl.opacity !== undefined) ctrl.opacity = parseFloat(ctrl.opacity);
            if (ctrl.visible !== undefined) ctrl.visible = !!ctrl.visible;
            if (ctrl.blend !== undefined) ctrl.blend = String(ctrl.blend);
            strokes.push(ctrl);
            if (event.t !== undefined) lastEventTime = event.t;
        }
    }

    if (currentStroke && currentStroke.path.length > 0) {
        strokes.push(currentStroke);
    }

    if (strokes.length > 0) {
        strokes[0].width = canvasWidth;
        strokes[0].height = canvasHeight;
    }

    return calculateFrameDurations(strokes);
}

export function calculateFrameDurations(strokes) {
    if (strokes.length === 0) return [];
    return normalizeFrameDurations(strokes, { realTime: false });
}

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
                if (intervalMs > pauseThresholdMs) {
                    intervalMs = 0;
                }
                durationMs = Math.max(0, Math.min(intervalMs, maxInterval));
            } else {
                durationMs = Math.max(minInterval, Math.min(intervalMs, maxInterval));
            }
        }

        strokes[i].durationMs = durationMs;
    }

    return strokes;
}

/**
 * Return interpolated sample points between two positions.
 * lastPt: { x, y, pressure }
 * nextPt: { x, y }
 * lastPressure, nextPressure: numbers
 * stepSize: pixel distance per sample (default 2)
 * Returns array of { x, y, pressure } excluding the starting point.
 */
export function sampleInterpolatedPoints(lastPt, nextPt, lastPressure, nextPressure, stepSize = 2) {
    const dx = nextPt.x - lastPt.x;
    const dy = nextPt.y - lastPt.y;
    const dist = Math.sqrt(dx * dx + dy * dy);
    const steps = Math.max(1, Math.ceil(dist / stepSize));
    const out = [];
    for (let i = 1; i <= steps; i++) {
        const t = i / steps;
        const ix = lastPt.x + dx * t;
        const iy = lastPt.y + dy * t;
        const p = (lastPressure !== undefined ? lastPressure : 1) + ((nextPressure !== undefined ? nextPressure : lastPressure) - (lastPressure !== undefined ? lastPressure : 1)) * t;
        out.push({ x: ix, y: iy, pressure: p });
    }
    return out;
}
