/* Simple multi-layer paint app (minimal features for integration testing)
 * - 4 stacked canvases
 * - draw with mouse/touch, eraser, undo/redo (per-layer limited history)
 * - save: composite to PNG dataURL and POST to save API with CSRF
 */
(function () {
    const layers = Array.from(document.querySelectorAll('canvas.layer'));
    const ctxs = layers.map(c => c.getContext('2d'));
    const colorInput = document.getElementById('color');
    const sizeInput = document.getElementById('size');
    const eraserBtn = document.getElementById('eraser');
    const undoBtn = document.getElementById('undo');
    const redoBtn = document.getElementById('redo');
    const saveBtn = document.getElementById('save');
    const status = document.getElementById('status');

    let active = 3; // top layer by default
    let drawing = false;
    let erasing = false;
    const undoStacks = layers.map(() => []);
    const redoStacks = layers.map(() => []);
    // timelapse events and current id for updates
    const timelapseEvents = [];
    let currentIllustId = null;

    function setStatus(msg) { status.textContent = msg; }

    function pushUndo(layerIndex) {
        const c = layers[layerIndex];
        undoStacks[layerIndex].push(c.toDataURL());
        // keep small history
        if (undoStacks[layerIndex].length > 20) undoStacks[layerIndex].shift();
        // clear redo
        redoStacks[layerIndex] = [];
    }

    function doUndo() {
        const s = undoStacks[active];
        if (!s || s.length === 0) return;
        const last = s.pop();
        redoStacks[active].push(layers[active].toDataURL());
        const img = new Image();
        img.onload = () => {
            ctxs[active].clearRect(0,0,layers[active].width, layers[active].height);
            ctxs[active].drawImage(img,0,0);
        };
        img.src = last;
    }

    function doRedo() {
        const s = redoStacks[active];
        if (!s || s.length === 0) return;
        const last = s.pop();
        pushUndo(active);
        const img = new Image();
        img.onload = () => {
            ctxs[active].clearRect(0,0,layers[active].width, layers[active].height);
            ctxs[active].drawImage(img,0,0);
        };
        img.src = last;
    }

    function pointerPos(e, canvas) {
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX ?? (e.touches && e.touches[0].clientX)) - rect.left;
        const y = (e.clientY ?? (e.touches && e.touches[0].clientY)) - rect.top;
        return {x, y};
    }

    layers.forEach((c, i) => {
        c.style.zIndex = i;
        c.addEventListener('mousedown', (ev) => { active = i; startDraw(ev); });
        c.addEventListener('touchstart', (ev) => { active = i; startDraw(ev); }, {passive:false});
        c.addEventListener('mousemove', (ev) => moveDraw(ev));
        c.addEventListener('touchmove', (ev) => moveDraw(ev), {passive:false});
        c.addEventListener('mouseup', endDraw);
        c.addEventListener('mouseleave', endDraw);
        c.addEventListener('touchend', endDraw);
    });

    function startDraw(e) {
        e.preventDefault();
        pushUndo(active);
        drawing = true;
        const p = pointerPos(e, layers[active]);
        const ctx = ctxs[active];
        ctx.beginPath();
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = parseInt(sizeInput.value, 10);
        ctx.strokeStyle = erasing ? 'rgba(0,0,0,1)' : colorInput.value;
        if (erasing) ctx.globalCompositeOperation = 'destination-out'; else ctx.globalCompositeOperation = 'source-over';
        ctx.moveTo(p.x, p.y);
        // record timelapse start
        try { timelapseEvents.push({ t: Date.now(), type: 'start', layer: active, x: p.x, y: p.y, color: colorInput.value, size: parseInt(sizeInput.value,10), erasing: !!erasing }); } catch (e) {}
    }

    function moveDraw(e) {
        if (!drawing) return;
        e.preventDefault();
        const p = pointerPos(e, layers[active]);
        const ctx = ctxs[active];
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        try { timelapseEvents.push({ t: Date.now(), type: 'move', layer: active, x: p.x, y: p.y }); } catch (e) {}
    }

    function endDraw() { drawing = false; try { timelapseEvents.push({ t: Date.now(), type: 'end', layer: active }); } catch (e) {} }

    eraserBtn.addEventListener('click', () => { erasing = !erasing; eraserBtn.textContent = erasing ? '描画' : '消しゴム'; });
    undoBtn.addEventListener('click', doUndo);
    redoBtn.addEventListener('click', doRedo);

    // composite and save
    saveBtn.addEventListener('click', async () => {
        setStatus('保存中...');
        // composite into offscreen canvas
        const w = layers[0].width, h = layers[0].height;
        const off = document.createElement('canvas');
        off.width = w; off.height = h;
        const octx = off.getContext('2d');
        // background white
        octx.fillStyle = '#FFFFFF'; octx.fillRect(0,0,w,h);
        layers.forEach(c => octx.drawImage(c, 0, 0));
        const dataUrl = off.toDataURL('image/png');

        // build minimal illust JSON
        const illust = {
            version: '1.0',
            metadata: { canvas_width: w, canvas_height: h, background_color: '#FFFFFF' },
            layers: layers.map((c, idx) => ({ id: 'layer_' + idx, name: 'layer_' + idx, order: idx, visible: true, opacity: 1.0, type: 'raster', data: '', width: w, height: h })),
            timelapse: { enabled: false }
        };

        // prepare timelapse payload (gzipped base64) if pako available
        let timelapsePayload = null;
        if (timelapseEvents.length > 0 && typeof pako !== 'undefined') {
            try {
                const jsonStr = JSON.stringify(timelapseEvents);
                const gz = pako.gzip(jsonStr);
                // convert gz (Uint8Array) to binary string for btoa
                let bin = '';
                for (let i = 0; i < gz.length; i++) bin += String.fromCharCode(gz[i]);
                timelapsePayload = 'data:application/octet-stream;base64,' + btoa(bin);
            } catch (err) {
                console.error('timelapse gzip failed', err);
            }
        }

        const payload = {
            title: 'canvas save',
            canvas_width: w,
            canvas_height: h,
            background_color: '#FFFFFF',
            illust_data: JSON.stringify(illust),
            image_data: dataUrl,
            timelapse_data: timelapsePayload,
            csrf_token: window.CSRF_TOKEN,
            id: currentIllustId
        };

        try {
            const res = await fetch('/admin/paint/api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (json.success) {
                setStatus('保存完了 (id=' + json.data.id + ')');
                // persist id for subsequent saves
                if (json.data && json.data.id) currentIllustId = json.data.id;
                // clear events on successful save
                timelapseEvents.length = 0;
            } else {
                setStatus('保存失敗: ' + (json.error || 'unknown'));
            }
        } catch (e) {
            setStatus('通信エラー');
            console.error(e);
        }
    });

})();
