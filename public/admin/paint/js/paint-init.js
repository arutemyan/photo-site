/**
 * Paint Application Initialization
 * Moved from inline scripts for CSP compliance (no unsafe-inline needed)
 */

// Get configuration from meta tags and data attributes
window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
window.PAINT_BASE_URL = document.body.dataset.paintBaseUrl || '';

// Shim Worker constructor for timelapse worker so relative "js/..." paths resolve under PAINT_BASE_URL
(function(){
    if (typeof window === 'undefined' || typeof window.Worker === 'undefined') return;
    try {
        const OrigWorker = window.Worker;
        window.Worker = function(scriptUrl) {
            try {
                if (typeof scriptUrl === 'string' && scriptUrl.indexOf('timelapse_worker.js') !== -1 && window.PAINT_BASE_URL) {
                    const base = String(window.PAINT_BASE_URL).replace(/\/$/, '');
                    return new OrigWorker(base + '/js/timelapse_worker.js');
                }
            } catch (e) {
                // ignore and fall back
            }
            return new OrigWorker(scriptUrl);
        };
        // preserve prototype
        window.Worker.prototype = OrigWorker.prototype;
    } catch (e) {
        // ignore
    }
})();

// Fetch wrapper: prefix relative "api/..." requests with PAINT_BASE_URL so JS can use simple relative paths
// Also: special-case timelapse API responses to normalize them into a gzipped binary payload
(function(){
    try {
        if (typeof window === 'undefined' || typeof window.fetch !== 'function') return;
        const _origFetch = window.fetch.bind(window);

        // helper to gzip a string (if pako available)
        const gzipString = async (str) => {
            try{
                if (typeof pako !== 'undefined' && typeof pako.gzip === 'function'){
                    const arr = pako.gzip(str);
                    return new Uint8Array(arr);
                }
            }catch(e){/* fallthrough */}
            return null;
        };

        // helper to detect if a value is a Uint8Array or ArrayBuffer
        const isBufferLike = (val) => {
            return (val instanceof Uint8Array) || (val instanceof ArrayBuffer);
        };

        // override fetch:
        window.fetch = async function(input, init) {
            // 1) if input is relative "api/...", prefix with PAINT_BASE_URL
            if (typeof input === 'string' && input.startsWith('api/') && window.PAINT_BASE_URL) {
                const base = window.PAINT_BASE_URL.replace(/\/$/, '');
                input = base + '/' + input;
            }

            // 2) call original fetch
            const resp = await _origFetch(input, init);

            // 3) if this is timelapse API returning JSON { success, ...data }, convert data -> gzipped buffer for worker
            //    paint.js typically sends Uint8Array payloads to the worker. The old code in PHP returns JSON with data field.
            //    We normalize by gzipping if we detect text, or pass-through if already binary.
            try{
                const contentType = resp.headers.get('content-type') || '';
                // if it's a timelapse endpoint (heuristic):
                if (typeof input === 'string' && input.includes('timelapse.php')) {
                    // clone resp so we can read body
                    const cloned = resp.clone();
                    // try to parse as JSON
                    const json = await cloned.json();
                    if (json.success && json.data) {
                        // data might be a string or an array (old JSON approach from the PHP)
                        let dataBytes = null;
                        if (typeof json.data === 'string') {
                            // gzip
                            dataBytes = await gzipString(json.data);
                        } else if (Array.isArray(json.data)) {
                            dataBytes = new Uint8Array(json.data);
                        }
                        // build a synthetic response
                        if (dataBytes) {
                            return new Response(dataBytes, {
                                status: 200,
                                statusText: 'OK',
                                headers: { 'Content-Type': 'application/octet-stream' }
                            });
                        } else {
                            return resp; // fallback
                        }
                    } else {
                        return resp; // fallback
                    }
                } else {
                    return resp; // not timelapse, pass orig response
                }
            }catch(e){
                // probably not JSON or invalid parse
                return resp; // just pass orig
            }
        };

        // pass-through for Response-returning fetch calls
        if (window.fetch.then) {
            // fetch already returns a Promise<Response>, so do nothing.
        } else {
            const orig = _origFetch;
            Object.defineProperty(window, 'fetch', {
                value: async function(...args) {
                    try {
                        return await orig(...args);
                    } catch (e) {
                        return orig; // fallback
                    }
                }
            });
        }
    } catch (e) {
        // ignore
    }
})();
