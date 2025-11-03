// Timelapse compression worker: accepts { events } and returns { payload }
// Uses pako via CDN importScripts
importScripts('https://cdn.jsdelivr.net/npm/pako@2.1.0/dist/pako.min.js');

self.onmessage = function (e) {
    const data = e.data;
    if (!data || !data.events) return;
    try {
        const events = data.events;
        // derive headers
        const headers = [];
        events.forEach(ev => {
            Object.keys(ev).forEach(k => { if (headers.indexOf(k) === -1) headers.push(k); });
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
        const csv = lines.join('\n');
        const gz = pako.gzip(csv);
        // convert Uint8Array to base64
        let bin = '';
        for (let i = 0; i < gz.length; i++) bin += String.fromCharCode(gz[i]);
        const b64 = btoa(bin);
        const payload = 'data:application/octet-stream;base64,' + b64;
        postMessage({ success: true, payload: payload });
    } catch (err) {
        postMessage({ success: false, error: String(err) });
    }
};
