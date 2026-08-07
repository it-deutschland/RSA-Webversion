/**
 * Sonka Bau & Sonnenimmobilien - Multi Administration – Plan Editor
 * Built on Fabric.js 5.x  |  No build process required
 * @license MIT
 */

'use strict';

// ── State ────────────────────────────────────────────────────
const Editor = {
    canvas:        null,
    planId:        null,
    projectId:     null,
    modified:      false,
    gridEnabled:   false,
    snapEnabled:   true,
    snapThreshold: 10,
    gridSize:      20,
    rulers:        { h: null, v: null },
    activeTool:    'select',
    scale:         '1:500',
    history: {
        stack:   [],
        pointer: -1,
        maxSize: 50,
    },
    drawingLine:   null,
    isDrawing:     false,
};

// ── Initialise ───────────────────────────────────────────────
function initEditor(planId, projectId, canvasData) {
    Editor.planId    = planId;
    Editor.projectId = projectId;

    // Create Fabric canvas
    const wrapper = document.getElementById('canvas-wrapper');
    Editor.canvas = new fabric.Canvas('plan-canvas', {
        backgroundColor: '#ffffff',
        preserveObjectStacking: true,
        selection: true,
        controlsAboveOverlay: true,
    });

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Load existing canvas data
    if (canvasData && canvasData !== 'null' && canvasData !== '') {
        try {
            Editor.canvas.loadFromJSON(canvasData, () => {
                Editor.canvas.renderAll();
                pushHistory();
            });
        } catch (e) {
            console.warn('Could not load canvas data:', e);
            pushHistory();
        }
    } else {
        drawGrid();
        pushHistory();
    }

    bindCanvasEvents();
    bindToolbar();
    bindProperties();
    bindSymbolPanel();
    bindLayerPanel();
    bindKeyboard();
    loadSymbolLibrary();
    initRulers();
    startAutoSave();
}

// ── Canvas resize ─────────────────────────────────────────────
function resizeCanvas() {
    const area = document.getElementById('canvas-area');
    if (!area) return;
    const w = area.offsetWidth;
    const h = area.offsetHeight;
    Editor.canvas.setWidth(w);
    Editor.canvas.setHeight(h);
    Editor.canvas.renderAll();
    updateRulers();
}

// ── Grid ─────────────────────────────────────────────────────
function drawGrid() {
    if (!Editor.gridEnabled) return;
    const w = Editor.canvas.width;
    const h = Editor.canvas.height;
    const gs = Editor.gridSize;
    const lines = [];

    for (let x = 0; x <= w; x += gs) {
        lines.push(new fabric.Line([x, 0, x, h], {
            stroke: '#ddd', strokeWidth: 0.5, selectable: false,
            evented: false, excludeFromExport: true, data: { type: 'grid' },
        }));
    }
    for (let y = 0; y <= h; y += gs) {
        lines.push(new fabric.Line([0, y, w, y], {
            stroke: '#ddd', strokeWidth: 0.5, selectable: false,
            evented: false, excludeFromExport: true, data: { type: 'grid' },
        }));
    }
    lines.forEach(l => { Editor.canvas.add(l); Editor.canvas.sendToBack(l); });
    Editor.canvas.renderAll();
}

function clearGrid() {
    const objs = Editor.canvas.getObjects().filter(o => o.data?.type === 'grid');
    objs.forEach(o => Editor.canvas.remove(o));
}

function toggleGrid() {
    Editor.gridEnabled = !Editor.gridEnabled;
    clearGrid();
    if (Editor.gridEnabled) drawGrid();
    document.getElementById('btn-grid')?.classList.toggle('active', Editor.gridEnabled);
    Editor.canvas.renderAll();
}

// ── Snap to grid ─────────────────────────────────────────────
function snapToGrid(value) {
    if (!Editor.snapEnabled || !Editor.gridEnabled) return value;
    return Math.round(value / Editor.gridSize) * Editor.gridSize;
}

// ── Canvas events ─────────────────────────────────────────────
function bindCanvasEvents() {
    const c = Editor.canvas;

    c.on('object:moving', (e) => {
        if (Editor.snapEnabled && Editor.gridEnabled) {
            e.target.set({
                left: snapToGrid(e.target.left),
                top:  snapToGrid(e.target.top),
            });
        }
        Editor.modified = true;
    });

    c.on('object:modified', () => {
        Editor.modified = true;
        pushHistory();
        updateProperties();
    });

    c.on('object:added', () => {
        Editor.modified = true;
    });

    c.on('selection:created', updateProperties);
    c.on('selection:updated', updateProperties);
    c.on('selection:cleared', clearProperties);

    c.on('mouse:move', (e) => {
        const ptr = c.getPointer(e.e);
        updateStatusBar(ptr.x, ptr.y);
        updateRulerCursor(ptr.x, ptr.y);
    });

    // Line drawing tool
    c.on('mouse:down', (e) => {
        if (Editor.activeTool === 'line') {
            const ptr = c.getPointer(e.e);
            Editor.isDrawing = true;
            Editor.drawingLine = new fabric.Line(
                [ptr.x, ptr.y, ptr.x, ptr.y],
                { stroke: '#333', strokeWidth: 2, selectable: false }
            );
            c.add(Editor.drawingLine);
        } else if (Editor.activeTool === 'arrow') {
            const ptr = c.getPointer(e.e);
            Editor.isDrawing = true;
            Editor.drawingLine = new fabric.Line(
                [ptr.x, ptr.y, ptr.x, ptr.y],
                { stroke: '#333', strokeWidth: 2, selectable: false }
            );
            c.add(Editor.drawingLine);
        }
    });

    c.on('mouse:move', (e) => {
        if (!Editor.isDrawing || !Editor.drawingLine) return;
        const ptr = c.getPointer(e.e);
        Editor.drawingLine.set({ x2: ptr.x, y2: ptr.y });
        c.renderAll();
    });

    c.on('mouse:up', () => {
        if (Editor.isDrawing && Editor.drawingLine) {
            Editor.drawingLine.set({ selectable: true });
            Editor.isDrawing = false;
            Editor.drawingLine = null;
            setTool('select');
            pushHistory();
        }
    });
}

// ── Tools ─────────────────────────────────────────────────────
function setTool(tool) {
    Editor.activeTool = tool;
    const c = Editor.canvas;

    document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tool === tool);
    });

    switch (tool) {
        case 'select':
            c.isDrawingMode = false;
            c.selection = true;
            c.defaultCursor = 'default';
            break;
        case 'pan':
            c.isDrawingMode = false;
            c.selection = false;
            c.defaultCursor = 'grab';
            break;
        case 'pencil':
            c.isDrawingMode = true;
            c.freeDrawingBrush.color = getActiveColor();
            c.freeDrawingBrush.width = 2;
            break;
        case 'line':
        case 'arrow':
            c.isDrawingMode = false;
            c.selection = false;
            c.defaultCursor = 'crosshair';
            break;
        case 'rect':
            addShape('rect');
            setTool('select');
            break;
        case 'circle':
            addShape('circle');
            setTool('select');
            break;
        case 'text':
            addText();
            setTool('select');
            break;
    }
}

function addShape(type) {
    const c = Editor.canvas;
    const cx = c.width / 2;
    const cy = c.height / 2;
    let shape;

    if (type === 'rect') {
        shape = new fabric.Rect({
            left: cx - 50, top: cy - 30, width: 100, height: 60,
            fill: 'rgba(13,110,253,.1)', stroke: '#0d6efd', strokeWidth: 2,
        });
    } else if (type === 'circle') {
        shape = new fabric.Circle({
            left: cx - 40, top: cy - 40, radius: 40,
            fill: 'rgba(25,135,84,.1)', stroke: '#198754', strokeWidth: 2,
        });
    }

    if (shape) {
        c.add(shape);
        c.setActiveObject(shape);
        pushHistory();
    }
}

function addText() {
    const c = Editor.canvas;
    const t = new fabric.IText('Text', {
        left: c.width / 2 - 40, top: c.height / 2 - 12,
        fontFamily: 'Helvetica', fontSize: 16, fill: '#333',
    });
    c.add(t);
    c.setActiveObject(t);
    t.enterEditing();
    pushHistory();
}

// ── Zoom ──────────────────────────────────────────────────────
function zoomIn()  { setZoom(Editor.canvas.getZoom() * 1.2); }
function zoomOut() { setZoom(Editor.canvas.getZoom() / 1.2); }
function zoomReset() { setZoom(1); Editor.canvas.viewportTransform = [1,0,0,1,0,0]; Editor.canvas.renderAll(); }

function setZoom(zoom) {
    zoom = Math.min(Math.max(zoom, 0.05), 20);
    Editor.canvas.setZoom(zoom);
    Editor.canvas.renderAll();
    document.getElementById('zoom-level').textContent = Math.round(zoom * 100) + '%';
}

// Handle scroll-to-zoom
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('canvas-area')?.addEventListener('wheel', (e) => {
        e.preventDefault();
        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        setZoom(Editor.canvas.getZoom() * delta);
    }, { passive: false });

    // Show note after credits field is filled
    const creditsInput = document.getElementById('exportCredits');
    const creditsNote  = document.getElementById('exportCreditsNote');
    if (creditsInput && creditsNote) {
        creditsInput.addEventListener('input', function () {
            creditsNote.classList.toggle('d-none', this.value.trim() === '');
        });
    }

    // Handle export buttons in export modal
    document.getElementById('btn-export-svg')?.addEventListener('click', exportSvg);
    document.getElementById('btn-export-png')?.addEventListener('click', exportPng);
    document.getElementById('btn-export-pdf')?.addEventListener('click', exportPdf);
});

// ── Align ─────────────────────────────────────────────────────
function alignObjects(direction) {
    const objs = Editor.canvas.getActiveObjects();
    if (objs.length < 2) return;

    const bounds = objs.map(o => o.getBoundingRect());
    const minX = Math.min(...bounds.map(b => b.left));
    const minY = Math.min(...bounds.map(b => b.top));
    const maxX = Math.max(...bounds.map(b => b.left + b.width));
    const maxY = Math.max(...bounds.map(b => b.top + b.height));

    objs.forEach((o, i) => {
        const b = bounds[i];
        switch (direction) {
            case 'left':   o.set('left', minX); break;
            case 'right':  o.set('left', maxX - b.width); break;
            case 'top':    o.set('top',  minY); break;
            case 'bottom': o.set('top',  maxY - b.height); break;
            case 'centerH': o.set('left', (minX + maxX) / 2 - b.width / 2); break;
            case 'centerV': o.set('top',  (minY + maxY) / 2 - b.height / 2); break;
        }
        o.setCoords();
    });

    Editor.canvas.renderAll();
    pushHistory();
}

// ── Group/Ungroup ─────────────────────────────────────────────
function groupSelected() {
    const sel = Editor.canvas.getActiveObject();
    if (!sel || sel.type !== 'activeSelection') return;
    const group = sel.toGroup();
    Editor.canvas.setActiveObject(group);
    pushHistory();
}

function ungroupSelected() {
    const sel = Editor.canvas.getActiveObject();
    if (!sel || sel.type !== 'group') return;
    sel.toActiveSelection();
    pushHistory();
}

// ── Copy / Paste ──────────────────────────────────────────────
let _clipboard = null;

function copySelected() {
    Editor.canvas.getActiveObject()?.clone(cloned => { _clipboard = cloned; });
}

function pasteClipboard() {
    if (!_clipboard) return;
    _clipboard.clone(cloned => {
        Editor.canvas.discardActiveObject();
        cloned.set({ left: cloned.left + 20, top: cloned.top + 20, evented: true });
        if (cloned.type === 'activeSelection') {
            cloned.canvas = Editor.canvas;
            cloned.forEachObject(o => Editor.canvas.add(o));
            cloned.setCoords();
        } else {
            Editor.canvas.add(cloned);
        }
        Editor.canvas.setActiveObject(cloned);
        Editor.canvas.requestRenderAll();
        pushHistory();
        _clipboard.set({ left: cloned.left, top: cloned.top });
    });
}

// ── Mirror ────────────────────────────────────────────────────
function mirrorH() {
    const obj = Editor.canvas.getActiveObject();
    if (!obj) return;
    obj.set('flipX', !obj.flipX);
    Editor.canvas.renderAll();
    pushHistory();
}

function mirrorV() {
    const obj = Editor.canvas.getActiveObject();
    if (!obj) return;
    obj.set('flipY', !obj.flipY);
    Editor.canvas.renderAll();
    pushHistory();
}

// ── Delete ────────────────────────────────────────────────────
function deleteSelected() {
    const obj = Editor.canvas.getActiveObject();
    if (!obj) return;
    if (obj.type === 'activeSelection') {
        obj.forEachObject(o => Editor.canvas.remove(o));
        Editor.canvas.discardActiveObject();
    } else {
        Editor.canvas.remove(obj);
    }
    Editor.canvas.renderAll();
    pushHistory();
}

// ── Undo / Redo ───────────────────────────────────────────────
function pushHistory() {
    const json = JSON.stringify(Editor.canvas.toJSON(['data', 'id']));
    // Trim future if we branched
    Editor.history.stack = Editor.history.stack.slice(0, Editor.history.pointer + 1);
    Editor.history.stack.push(json);
    if (Editor.history.stack.length > Editor.history.maxSize) {
        Editor.history.stack.shift();
    }
    Editor.history.pointer = Editor.history.stack.length - 1;
    updateHistoryButtons();
}

function undo() {
    if (Editor.history.pointer <= 0) return;
    Editor.history.pointer--;
    restoreHistory(Editor.history.stack[Editor.history.pointer]);
}

function redo() {
    if (Editor.history.pointer >= Editor.history.stack.length - 1) return;
    Editor.history.pointer++;
    restoreHistory(Editor.history.stack[Editor.history.pointer]);
}

function restoreHistory(json) {
    Editor.canvas.loadFromJSON(json, () => {
        Editor.canvas.renderAll();
        updateHistoryButtons();
        Editor.modified = true;
    });
}

function updateHistoryButtons() {
    const undoBtn = document.getElementById('btn-undo');
    const redoBtn = document.getElementById('btn-redo');
    if (undoBtn) undoBtn.disabled = Editor.history.pointer <= 0;
    if (redoBtn) redoBtn.disabled = Editor.history.pointer >= Editor.history.stack.length - 1;
}

// ── Properties panel ─────────────────────────────────────────
function updateProperties() {
    const obj = Editor.canvas.getActiveObject();
    if (!obj) { clearProperties(); return; }

    const panel = document.getElementById('props-panel');
    if (!panel) return;
    panel.style.display = 'block';

    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = val ?? '';
    };

    set('prop-x',       Math.round(obj.left));
    set('prop-y',       Math.round(obj.top));
    set('prop-w',       Math.round(obj.getScaledWidth()));
    set('prop-h',       Math.round(obj.getScaledHeight()));
    set('prop-rot',     Math.round(obj.angle));
    set('prop-opacity', Math.round((obj.opacity ?? 1) * 100));

    const colorEl = document.getElementById('prop-fill');
    if (colorEl && obj.fill && typeof obj.fill === 'string' && obj.fill.startsWith('#')) {
        colorEl.value = obj.fill;
    }

    const strokeEl = document.getElementById('prop-stroke');
    if (strokeEl && obj.stroke && typeof obj.stroke === 'string' && obj.stroke.startsWith('#')) {
        strokeEl.value = obj.stroke;
    }
}

function clearProperties() {
    const panel = document.getElementById('props-panel');
    if (panel) panel.style.display = 'none';
}

function bindProperties() {
    const c = Editor.canvas;
    const bindProp = (id, setProp, transform = v => v) => {
        document.getElementById(id)?.addEventListener('input', function () {
            const obj = c.getActiveObject();
            if (!obj) return;
            obj.set(setProp, transform(parseFloat(this.value)));
            c.renderAll();
        });
    };

    bindProp('prop-x',       'left');
    bindProp('prop-y',       'top');
    bindProp('prop-rot',     'angle');
    bindProp('prop-opacity', 'opacity', v => v / 100);

    document.getElementById('prop-w')?.addEventListener('input', function () {
        const obj = c.getActiveObject();
        if (!obj) return;
        const scale = parseFloat(this.value) / obj.width;
        obj.scaleX = scale;
        c.renderAll();
    });

    document.getElementById('prop-h')?.addEventListener('input', function () {
        const obj = c.getActiveObject();
        if (!obj) return;
        const scale = parseFloat(this.value) / obj.height;
        obj.scaleY = scale;
        c.renderAll();
    });

    document.getElementById('prop-fill')?.addEventListener('input', function () {
        const obj = c.getActiveObject();
        if (!obj) return;
        obj.set('fill', this.value);
        c.renderAll();
    });

    document.getElementById('prop-stroke')?.addEventListener('input', function () {
        const obj = c.getActiveObject();
        if (!obj) return;
        obj.set('stroke', this.value);
        c.renderAll();
    });
}

// ── Toolbar binding ───────────────────────────────────────────
function bindToolbar() {
    // Tool buttons
    document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
        btn.addEventListener('click', () => setTool(btn.dataset.tool));
    });

    document.getElementById('btn-zoom-in')?.addEventListener('click',    zoomIn);
    document.getElementById('btn-zoom-out')?.addEventListener('click',   zoomOut);
    document.getElementById('btn-zoom-reset')?.addEventListener('click', zoomReset);
    document.getElementById('btn-grid')?.addEventListener('click',       toggleGrid);
    document.getElementById('btn-snap')?.addEventListener('click',       () => {
        Editor.snapEnabled = !Editor.snapEnabled;
        document.getElementById('btn-snap').classList.toggle('active', Editor.snapEnabled);
    });

    document.getElementById('btn-undo')?.addEventListener('click', undo);
    document.getElementById('btn-redo')?.addEventListener('click', redo);

    document.getElementById('btn-group')?.addEventListener('click',   groupSelected);
    document.getElementById('btn-ungroup')?.addEventListener('click', ungroupSelected);
    document.getElementById('btn-copy')?.addEventListener('click',    copySelected);
    document.getElementById('btn-paste')?.addEventListener('click',   pasteClipboard);
    document.getElementById('btn-delete')?.addEventListener('click',  deleteSelected);
    document.getElementById('btn-mirror-h')?.addEventListener('click', mirrorH);
    document.getElementById('btn-mirror-v')?.addEventListener('click', mirrorV);

    document.getElementById('btn-align-left')?.addEventListener('click',    () => alignObjects('left'));
    document.getElementById('btn-align-right')?.addEventListener('click',   () => alignObjects('right'));
    document.getElementById('btn-align-top')?.addEventListener('click',     () => alignObjects('top'));
    document.getElementById('btn-align-bottom')?.addEventListener('click',  () => alignObjects('bottom'));
    document.getElementById('btn-align-centerH')?.addEventListener('click', () => alignObjects('centerH'));
    document.getElementById('btn-align-centerV')?.addEventListener('click', () => alignObjects('centerV'));

    document.getElementById('btn-save')?.addEventListener('click', savePlan);
    document.getElementById('btn-export')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('export-modal'));
        modal.show();
    });

    document.getElementById('btn-export-svg')?.addEventListener('click', exportSvg);
    document.getElementById('btn-export-png')?.addEventListener('click', exportPng);
    document.getElementById('btn-export-pdf')?.addEventListener('click', exportPdf);

    document.getElementById('plan-scale')?.addEventListener('change', function () {
        Editor.scale = this.value;
        document.getElementById('status-scale').textContent = 'Maßstab ' + this.value;
    });

    // Image upload from file
    document.getElementById('btn-add-image')?.addEventListener('click', () => {
        document.getElementById('img-upload-input')?.click();
    });

    document.getElementById('img-upload-input')?.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            fabric.Image.fromURL(e.target.result, (img) => {
                img.scaleToWidth(Math.min(img.width, 200));
                img.set({ left: 50, top: 50 });
                Editor.canvas.add(img);
                Editor.canvas.setActiveObject(img);
                pushHistory();
            });
        };
        reader.readAsDataURL(file);
        this.value = '';
    });
}

// ── Symbol library panel ──────────────────────────────────────
function bindSymbolPanel() {
    const searchInput = document.getElementById('symbol-search');
    searchInput?.addEventListener('input', function () {
        filterSymbols(this.value);
    });

    document.querySelectorAll('[data-cat-filter]').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-cat-filter]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterSymbols(searchInput?.value || '', this.dataset.catFilter);
        });
    });
}

async function loadSymbolLibrary() {
    const grid = document.getElementById('symbol-grid');
    if (!grid) return;

    try {
        const response = await fetch('/api/v1/symbols?per_page=200');
        const data     = await response.json();
        const symbols  = data.data || data || [];

        grid.innerHTML = symbols.length === 0
            ? '<p class="text-muted text-center small p-3">Keine Symbole vorhanden.<br>Symbole unter <a href="/symbols/create">Symbole → Neu</a> hochladen.</p>'
            : '';

        symbols.forEach(sym => {
            const item = document.createElement('div');
            item.className = 'symbol-item';
            item.dataset.symId  = sym.id;
            item.dataset.symUrl = sym.file_url || `/dateien/symbols/${sym.file_path}`;
            item.dataset.symName = sym.name;
            item.dataset.symW   = sym.width_mm  || 100;
            item.dataset.symH   = sym.height_mm || 100;
            item.title = `${sym.name}${sym.sign_number ? ' (' + sym.sign_number + ')' : ''}`;
            item.innerHTML = `
                <img src="${item.dataset.symUrl}" alt="${escHtml(sym.name)}"
                     class="symbol-thumb" onerror="this.src='/assets/img/symbol-placeholder.svg'">
                <span class="symbol-name">${escHtml(sym.name)}</span>
            `;
            item.addEventListener('click', () => addSymbolToCanvas(item));
            item.addEventListener('dblclick', () => addSymbolToCanvas(item));
            grid.appendChild(item);
        });

        // Make items draggable to canvas
        grid.querySelectorAll('.symbol-item').forEach(item => {
            item.draggable = true;
            item.addEventListener('dragstart', e => {
                e.dataTransfer.setData('sym-url',  item.dataset.symUrl);
                e.dataTransfer.setData('sym-name', item.dataset.symName);
                e.dataTransfer.setData('sym-w',    item.dataset.symW);
                e.dataTransfer.setData('sym-h',    item.dataset.symH);
            });
        });

        // Canvas drop target
        document.getElementById('canvas-area')?.addEventListener('dragover', e => e.preventDefault());
        document.getElementById('canvas-area')?.addEventListener('drop', e => {
            e.preventDefault();
            const url  = e.dataTransfer.getData('sym-url');
            const symW = parseFloat(e.dataTransfer.getData('sym-w')) || 100;
            if (!url) return;
            const rect = Editor.canvas.getElement().getBoundingClientRect();
            const x    = (e.clientX - rect.left) / Editor.canvas.getZoom();
            const y    = (e.clientY - rect.top)  / Editor.canvas.getZoom();
            addImageToCanvas(url, x - symW / 2, y - symW / 2, symW);
        });

    } catch (e) {
        console.error('Error loading symbol library:', e);
    }
}

function addSymbolToCanvas(item) {
    const url = item.dataset.symUrl;
    const w   = parseFloat(item.dataset.symW) || 100;
    addImageToCanvas(url, 50, 50, w);
}

function addImageToCanvas(url, x, y, targetWidth) {
    fabric.Image.fromURL(url, (img) => {
        if (!img.width) return;
        img.scaleToWidth(targetWidth);
        img.set({ left: x, top: y });
        Editor.canvas.add(img);
        Editor.canvas.setActiveObject(img);
        Editor.canvas.renderAll();
        pushHistory();
    }, { crossOrigin: 'anonymous' });
}

function filterSymbols(query, category = '') {
    const q = query.toLowerCase();
    document.querySelectorAll('#symbol-grid .symbol-item').forEach(item => {
        const name = (item.dataset.symName || '').toLowerCase();
        const matchQ = q === '' || name.includes(q);
        const matchC = category === '' || category === 'all' || (item.dataset.symCat || '').toLowerCase() === category.toLowerCase();
        item.style.display = (matchQ && matchC) ? '' : 'none';
    });
}

// ── Layer panel ───────────────────────────────────────────────
function bindLayerPanel() {
    document.getElementById('btn-bring-forward')?.addEventListener('click',  () => { Editor.canvas.getActiveObject()?.bringForward();  Editor.canvas.renderAll(); pushHistory(); });
    document.getElementById('btn-send-backward')?.addEventListener('click',  () => { Editor.canvas.getActiveObject()?.sendBackwards(); Editor.canvas.renderAll(); pushHistory(); });
    document.getElementById('btn-bring-front')?.addEventListener('click',    () => { Editor.canvas.getActiveObject()?.bringToFront();  Editor.canvas.renderAll(); pushHistory(); });
    document.getElementById('btn-send-back')?.addEventListener('click',      () => { Editor.canvas.getActiveObject()?.sendToBack();    Editor.canvas.renderAll(); pushHistory(); });
}

// ── Keyboard shortcuts ────────────────────────────────────────
function bindKeyboard() {
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        const ctrl = e.ctrlKey || e.metaKey;

        if (ctrl && e.key === 'z') { e.preventDefault(); undo(); }
        if (ctrl && e.key === 'y') { e.preventDefault(); redo(); }
        if (ctrl && e.key === 'c') { e.preventDefault(); copySelected(); }
        if (ctrl && e.key === 'v') { e.preventDefault(); pasteClipboard(); }
        if (ctrl && e.key === 's') { e.preventDefault(); savePlan(); }
        if (ctrl && e.key === '+') { e.preventDefault(); zoomIn(); }
        if (ctrl && e.key === '-') { e.preventDefault(); zoomOut(); }
        if (ctrl && e.key === '0') { e.preventDefault(); zoomReset(); }
        if (ctrl && e.key === 'g') { e.preventDefault(); groupSelected(); }
        if (e.key === 'Delete' || e.key === 'Backspace') {
            if (Editor.canvas.getActiveObject()) { e.preventDefault(); deleteSelected(); }
        }
        if (e.key === 'Escape') { setTool('select'); }
    });
}

// ── Save ──────────────────────────────────────────────────────
function savePlan() {
    if (!Editor.planId) return;
    if (window.RSA21?.setAutoSaveStatus) window.RSA21.setAutoSaveStatus('saving');

    // Generate thumbnail (low-res PNG dataURL)
    const thumb = Editor.canvas.toDataURL({ format: 'jpeg', quality: 0.4, multiplier: 0.3 });

    const formData = new FormData();
    formData.append('canvas_data', JSON.stringify(Editor.canvas.toJSON(['data', 'id'])));
    formData.append('thumbnail',   thumb);
    formData.append('scale',       Editor.scale);
    formData.append('_token',      document.querySelector('meta[name="csrf-token"]')?.content || '');

    fetch(`/plans/${Editor.planId}/save`, { method: 'POST', body: formData })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(() => {
            Editor.modified = false;
            if (window.RSA21?.setAutoSaveStatus) window.RSA21.setAutoSaveStatus('saved');
        })
        .catch(() => {
            if (window.RSA21?.setAutoSaveStatus) window.RSA21.setAutoSaveStatus('error');
        });
}

function startAutoSave() {
    setInterval(() => {
        if (Editor.modified) savePlan();
    }, 60000); // auto-save every 60s
}

// ── Export ────────────────────────────────────────────────────
function getExportCredits() {
    return (document.getElementById('exportCredits')?.value || '').trim();
}

function addCreditsToSvg(svg) {
    const credits = getExportCredits();
    if (!credits) return svg;
    const escaped = escHtml(credits);
    const creditsSvg = `<text x="10" y="100%" dy="-6" font-family="Helvetica, Arial, sans-serif" font-size="11" fill="#555" opacity="0.8">${escaped}</text>`;
    return svg.replace('</svg>', creditsSvg + '</svg>');
}

function exportSvg() {
    const svg = addCreditsToSvg(Editor.canvas.toSVG());
    const blob = new Blob([svg], { type: 'image/svg+xml' });
    downloadBlob(blob, 'plan.svg');
}

function exportPng() {
    const dpi    = parseFloat(document.getElementById('exportResolution')?.value || '300');
    const mult   = dpi / 96;
    const dataUrl = Editor.canvas.toDataURL({ format: 'png', multiplier: mult });
    const link    = document.createElement('a');
    link.download = 'plan.png';
    link.href     = dataUrl;
    link.click();
}

function exportPdf() {
    // For PDF: send canvas data to server to generate PDF
    const formData = new FormData();
    formData.append('svg',     addCreditsToSvg(Editor.canvas.toSVG()));
    formData.append('format',  document.getElementById('export-format')?.value || 'A4');
    formData.append('credits', getExportCredits());
    formData.append('_token',  document.querySelector('meta[name="csrf-token"]')?.content || '');

    fetch(`/plans/${Editor.planId}/export`, { method: 'POST', body: formData })
        .then(r => r.blob())
        .then(blob => downloadBlob(blob, 'plan.pdf'))
        .catch(() => { if (window.RSA21?.Flash) window.RSA21.Flash.show('danger', 'PDF-Export fehlgeschlagen.'); });
}

function downloadBlob(blob, filename) {
    const url  = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href     = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}

// ── Status bar ────────────────────────────────────────────────
function updateStatusBar(x, y) {
    document.getElementById('status-x').textContent = 'X: ' + Math.round(x);
    document.getElementById('status-y').textContent = 'Y: ' + Math.round(y);
    document.getElementById('zoom-level').textContent = Math.round(Editor.canvas.getZoom() * 100) + '%';
}

// ── Rulers ────────────────────────────────────────────────────
function initRulers() {
    const rulerH = document.getElementById('ruler-h');
    const rulerV = document.getElementById('ruler-v');
    if (!rulerH || !rulerV) return;
    Editor.rulers.h = rulerH;
    Editor.rulers.v = rulerV;
    drawRuler(rulerH, 'h');
    drawRuler(rulerV, 'v');
}

function drawRuler(canvas, dir) {
    if (!canvas) return;
    const size   = dir === 'h' ? canvas.offsetWidth : canvas.offsetHeight;
    const ctx    = canvas.getContext ? canvas.getContext('2d') : null;
    if (!ctx) return;
    if (dir === 'h') canvas.width = size; else canvas.height = size;
    ctx.fillStyle   = '#888';
    ctx.font        = '9px sans-serif';
    ctx.strokeStyle = '#aaa';

    for (let i = 0; i < size; i += 50) {
        ctx.beginPath();
        if (dir === 'h') { ctx.moveTo(i, 14); ctx.lineTo(i, 20); ctx.fillText(i, i + 2, 12); }
        else             { ctx.moveTo(14, i); ctx.lineTo(20, i); ctx.fillText(i, 0, i - 2); }
        ctx.stroke();
    }
}

function updateRulers() { /* could redraw on zoom change */ }
function updateRulerCursor(x, y) { /* could show cursor line on rulers */ }

// ── Color helper ─────────────────────────────────────────────
function getActiveColor() {
    return document.getElementById('prop-stroke')?.value || '#333333';
}

// ── HTML escape (from app.js if available) ────────────────────
function escHtml(str) {
    if (window.RSA21?.escHtml) return window.RSA21.escHtml(str);
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

// ── Expose init ───────────────────────────────────────────────
window.initEditor = initEditor;
