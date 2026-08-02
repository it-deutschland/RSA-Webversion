<?php

use App\Core\CSRF;
use App\Core\View;

require_once VIEWS_PATH . '/_helpers.php';

$plan = $plan ?? [];
$project = $project ?? [];
$canvasData = rsa21_data_get($plan, 'canvas_data', '{}');
if (!is_string($canvasData) || trim($canvasData) === '') {
    $canvasData = '{}';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e((string) rsa21_data_get($plan, 'title', 'Plan-Editor')) ?> · RSA21-Free</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.php" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { margin: 0; overflow: hidden; }
        .editor-shell { display: grid; grid-template-columns: minmax(0, 3fr) minmax(320px, 1fr); grid-template-rows: auto auto auto 1fr auto; height: 100vh; }
        .editor-main { min-width: 0; display: flex; flex-direction: column; }
        .editor-canvas-wrap { position: relative; flex: 1; overflow: hidden; background: var(--bs-tertiary-bg); }
        .editor-canvas-stage { position: absolute; inset: 0; padding: 1rem; }
        #planCanvas { width: 100%; height: 100%; background-image: linear-gradient(to right, rgba(127,127,127,.15) 1px, transparent 1px), linear-gradient(to bottom, rgba(127,127,127,.15) 1px, transparent 1px); background-size: 24px 24px; border-radius: .75rem; box-shadow: inset 0 0 0 1px rgba(127,127,127,.18); }
        .editor-sidebar { border-left: 1px solid var(--bs-border-color); display: flex; flex-direction: column; min-height: 0; }
        .sidebar-scroll { overflow: auto; min-height: 0; }
        .symbol-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: .75rem; }
        .symbol-item { cursor: grab; }
        .toolbar-scroll { overflow-x: auto; }
        .toolbar-scroll .btn-toolbar { flex-wrap: nowrap; }
        .status-bar { font-size: .875rem; }
        @media (max-width: 991.98px) {
            .editor-shell { grid-template-columns: 1fr; grid-template-rows: auto auto auto minmax(320px, 1fr) auto auto; }
            .editor-sidebar { border-left: 0; border-top: 1px solid var(--bs-border-color); }
        }
    </style>
</head>
<body>
<div class="editor-shell bg-body">
    <header class="editor-main border-bottom bg-body px-3 px-xl-4 py-3">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div><div class="text-body-secondary small"><?= View::e((string) rsa21_data_get($project, 'title', 'Projekt')) ?></div><h1 class="h4 mb-0"><?= View::e((string) rsa21_data_get($plan, 'title', 'Plan-Editor')) ?></h1></div>
            <div class="d-flex flex-wrap align-items-center gap-2"><div class="btn-group"><button class="btn btn-outline-secondary" type="button" data-editor-action="undo"><i class="bi bi-arrow-counterclockwise"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-action="redo"><i class="bi bi-arrow-clockwise"></i></button></div><select class="form-select" id="planScale"><?php foreach (['1:100', '1:200', '1:500', '1:1000', '1:2000'] as $scale): ?><option value="<?= View::e($scale) ?>" <?= (string) rsa21_data_get($plan, 'scale', '1:500') === $scale ? 'selected' : '' ?>><?= View::e($scale) ?></option><?php endforeach; ?></select><span class="badge text-bg-secondary" id="autosaveIndicator">Auto-Save bereit</span><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#exportModal"><i class="bi bi-download me-2"></i>Export</button></div>
        </div>
    </header>
    <div class="editor-main border-bottom bg-body px-3 px-xl-4 py-2 toolbar-scroll"><div class="btn-toolbar gap-2"><div class="btn-group"><button class="btn btn-outline-secondary active" type="button" data-tool="select"><i class="bi bi-cursor"></i> Auswahl</button><button class="btn btn-outline-secondary" type="button" data-tool="pan"><i class="bi bi-hand-index"></i> Bewegen</button><button class="btn btn-outline-secondary" type="button" data-tool="draw"><i class="bi bi-pencil"></i> Zeichnen</button></div><div class="btn-group"><button class="btn btn-outline-secondary" type="button" data-tool="rect"><i class="bi bi-square"></i></button><button class="btn btn-outline-secondary" type="button" data-tool="circle"><i class="bi bi-circle"></i></button><button class="btn btn-outline-secondary" type="button" data-tool="line"><i class="bi bi-slash-lg"></i></button><button class="btn btn-outline-secondary" type="button" data-tool="arrow"><i class="bi bi-arrow-up-right"></i></button><button class="btn btn-outline-secondary" type="button" data-tool="text"><i class="bi bi-type"></i></button></div><button class="btn btn-outline-secondary" type="button" id="editorImageUploadTrigger"><i class="bi bi-image"></i> Bild</button></div></div>
    <div class="editor-main border-bottom bg-body px-3 px-xl-4 py-2 toolbar-scroll"><div class="btn-toolbar gap-2"><div class="btn-group"><button class="btn btn-outline-secondary" type="button" data-editor-action="zoom-out"><i class="bi bi-zoom-out"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-action="zoom-reset"><i class="bi bi-arrows-fullscreen"></i></button><button class="btn btn-outline-secondary" type="button" data-editor-action="zoom-in"><i class="bi bi-zoom-in"></i></button></div><div class="btn-group"><button class="btn btn-outline-secondary" type="button" data-editor-toggle="grid"><i class="bi bi-grid-3x3-gap"></i> Raster</button><button class="btn btn-outline-secondary" type="button" data-editor-toggle="snap"><i class="bi bi-magnet"></i> Snap</button><button class="btn btn-outline-secondary" type="button" data-editor-toggle="rulers"><i class="bi bi-rulers"></i> Lineale</button><button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#layersModal"><i class="bi bi-layers"></i> Ebenen</button><button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#gridModal"><i class="bi bi-layout-wtf"></i> Rastereinstellungen</button></div></div></div>
    <section class="editor-main"><div class="editor-canvas-wrap"><div class="editor-canvas-stage"><canvas id="planCanvas"></canvas></div></div></section>
    <aside class="editor-sidebar bg-body"><div class="sidebar-scroll"><div class="p-3 border-bottom"><h2 class="h5 mb-3">Symbolbibliothek</h2><div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" class="form-control" id="symbolSearch" placeholder="Symbole suchen"></div><select class="form-select" id="symbolCategoryFilter"><option value="">Alle Kategorien</option></select></div><div class="p-3 border-bottom"><div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h6 mb-0">Katalog</h3><span class="badge text-bg-secondary" id="symbolCount">0</span></div><div class="symbol-grid" id="symbolGrid"><div class="card symbol-item text-center p-3 text-body-secondary"><div class="small">Symbole werden geladen…</div></div></div></div><div class="p-3" id="propertiesPanel"><div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h6 mb-0">Eigenschaften</h3><span class="badge text-bg-light" id="selectionState">Keine Auswahl</span></div><div class="row g-2"><div class="col-6"><label class="form-label small" for="propX">X</label><input type="number" class="form-control" id="propX"></div><div class="col-6"><label class="form-label small" for="propY">Y</label><input type="number" class="form-control" id="propY"></div><div class="col-6"><label class="form-label small" for="propWidth">Breite</label><input type="number" class="form-control" id="propWidth"></div><div class="col-6"><label class="form-label small" for="propHeight">Höhe</label><input type="number" class="form-control" id="propHeight"></div><div class="col-6"><label class="form-label small" for="propRotation">Rotation</label><input type="number" class="form-control" id="propRotation"></div><div class="col-6"><label class="form-label small" for="propOpacity">Deckkraft</label><input type="number" class="form-control" id="propOpacity" min="0" max="100"></div><div class="col-12"><label class="form-label small" for="propColor">Farbe</label><input type="color" class="form-control form-control-color" id="propColor" value="#0d6efd"></div></div></div></div></aside>
    <footer class="editor-main border-top bg-body px-3 px-xl-4 py-2 status-bar"><div class="d-flex flex-wrap justify-content-between gap-3"><div class="d-flex flex-wrap gap-3"><span><i class="bi bi-crosshair me-1"></i><span id="statusCoords">X: 0 · Y: 0</span></span><span><i class="bi bi-zoom-in me-1"></i><span id="statusZoom">100 %</span></span><span><i class="bi bi-rulers me-1"></i><span id="statusScale">Maßstab <?= View::e((string) rsa21_data_get($plan, 'scale', '1:500')) ?></span></span></div><div class="text-body-secondary">Speichert nach /plans/<?= View::e((string) rsa21_data_get($plan, 'id', '')) ?>/save</div></div></footer>
</div>
<form id="planSaveForm" method="post" action="/plans/<?= View::e((string) rsa21_data_get($plan, 'id', '')) ?>/save" class="d-none"><?= CSRF::field() ?><input type="hidden" name="canvas_data" id="canvasDataField"></form>
<input type="file" id="editorImageUpload" class="d-none" accept="image/*">
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Export</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body row g-3"><div class="col-12"><label class="form-label" for="exportFormat">Format</label><select class="form-select" id="exportFormat"><option value="svg">SVG</option><option value="png">PNG</option><option value="pdf">PDF</option></select></div><div class="col-12"><label class="form-label" for="exportResolution">Qualität</label><select class="form-select" id="exportResolution"><option value="150">150 DPI</option><option value="300" selected>300 DPI</option><option value="600">600 DPI</option></select></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button><button type="button" class="btn btn-primary" data-editor-action="export">Exportieren</button></div></div></div></div>
<div class="modal fade" id="layersModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Ebenenverwaltung</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="list-group" id="layersList"><div class="list-group-item text-body-secondary">Ebenen werden durch den Editor geladen.</div></div></div></div></div></div>
<div class="modal fade" id="gridModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Rastereinstellungen</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body row g-3"><div class="col-12"><label class="form-label" for="gridSize">Rastergröße</label><input type="number" class="form-control" id="gridSize" value="24" min="5"></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="gridSnapEnabled" checked><label class="form-check-label" for="gridSnapEnabled">Am Raster ausrichten</label></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button><button type="button" class="btn btn-primary" data-editor-action="apply-grid">Übernehmen</button></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
<script>
window.rsa21PlanEditor = {
    plan: <?= json_encode(['id' => rsa21_data_get($plan, 'id', null), 'title' => rsa21_data_get($plan, 'title', ''), 'scale' => rsa21_data_get($plan, 'scale', '1:500'), 'canvasData' => json_decode($canvasData, true) ?? new stdClass(), 'saveUrl' => '/plans/' . (string) rsa21_data_get($plan, 'id', '') . '/save'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    project: <?= json_encode(['id' => rsa21_data_get($project, 'id', null), 'title' => rsa21_data_get($project, 'title', '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    symbolsApi: '/api/v1/symbols',
    csrfToken: <?= json_encode(CSRF::token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
};
</script>
<script src="/assets/js/plan-editor.js"></script>
</body>
</html>
