<?php

use App\Core\CSRF;
use App\Core\View;

require_once VIEWS_PATH . '/_helpers.php';

$document = $document ?? [];
$project = $project ?? [];
$contentValue = (string) rsa21_data_get($document, 'content', '');
$legalDisclaimer = $legalDisclaimer ?? ($settings['legal_disclaimer'] ?? $settings['legalDisclaimer'] ?? '');
?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4"><div><div class="text-body-secondary small"><?= View::e((string) rsa21_data_get($project, 'title', 'Projekt')) ?></div><h1 class="h2 mb-1">Dokument bearbeiten</h1></div><a href="/documents/<?= View::e((string) rsa21_data_get($document, 'id', '')) ?>/pdf" class="btn btn-outline-primary">PDF exportieren</a></div>
<div class="alert alert-warning"><i class="bi bi-shield-exclamation me-2"></i><?= View::e((string) $legalDisclaimer) ?></div>
<div class="card shadow-sm"><div class="card-body"><form method="post" action="/documents/<?= View::e((string) rsa21_data_get($document, 'id', '')) ?>" class="row g-3" id="documentEditorForm"><?= CSRF::field() ?><div class="col-12"><label for="editor-content" class="form-label">HTML-Inhalt</label><textarea class="form-control" id="editor-content" name="content" rows="10"><?= View::e($contentValue) ?></textarea></div><div class="col-12"><label for="editor-preview" class="form-label">Vorschau / Editor</label><div id="editor-preview" class="form-control" contenteditable="true"><?= $contentValue ?></div></div><div class="col-12 d-flex justify-content-end gap-2"><a href="/documents/<?= View::e((string) rsa21_data_get($document, 'id', '')) ?>" class="btn btn-outline-secondary">Abbrechen</a><button type="submit" class="btn btn-primary">Speichern</button></div></form></div></div>
<script>
(() => {
    const textarea = document.getElementById('editor-content');
    const preview = document.getElementById('editor-preview');
    if (!textarea || !preview) return;
    preview.addEventListener('input', () => { textarea.value = preview.innerHTML; });
    textarea.addEventListener('input', () => { preview.innerHTML = textarea.value; });
})();
</script>
