<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\ControllerHelpers;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use Throwable;

/**
 * Handles document management.
 */
class DocumentController extends Controller
{
    use ControllerHelpers;

    public function index(): void
    {
        $this->requireAuth();

        $type = trim((string) Request::get('type', ''));
        $params = [];
        $where = '';
        if ($type !== '') {
            $where = 'WHERE d.`type` = :type';
            $params[':type'] = $type;
        }

        $documents = $this->db()->fetchAll(
            'SELECT d.*, p.`title` AS `project_title`, t.`name` AS `template_name`
             FROM `documents` d
             INNER JOIN `projects` p ON p.`id` = d.`project_id`
             LEFT JOIN `templates` t ON t.`id` = d.`template_id`
             ' . $where . '
             ORDER BY d.`updated_at` DESC',
            $params
        );

        $this->render('documents/index', ['documents' => $documents, 'filterType' => $type]);
    }

    public function create(string $pid): void
    {
        $this->requireAuth();

        $this->render('documents/create', [
            'project' => $this->requireRecord('projects', $pid),
            'templates' => $this->db()->fetchAll('SELECT * FROM `templates` ORDER BY `type`, `name`'),
        ]);
    }

    public function store(string $pid): void
    {
        $this->requireAuth();
        $this->ensureCsrf();

        $project = $this->requireRecord('projects', $pid);
        $validator = Validator::make(Request::all(), [
            'title' => 'required|max:200',
            'type' => 'required|max:50',
        ]);
        if ($validator->fails()) {
            $this->flashValidation($validator->errors(), Request::all());
            Session::flash('error', 'Please correct the document form.');
            $this->back();
        }

        $templateId = $this->nullableInt(Request::post('template_id'));
        $content = trim((string) Request::post('content', ''));
        if ($templateId !== null) {
            $template = $this->findRecord('templates', $templateId);
            if ($template !== null && $content === '') {
                $content = (string) ($template['content'] ?? '');
            }
        }

        $payload = [
            'project_id' => (int) $project['id'],
            'created_by' => Auth::id(),
            'template_id' => $templateId,
            'type' => trim((string) Request::post('type', 'other')) ?: 'other',
            'title' => trim((string) Request::post('title')),
            'content' => $content,
            'version' => 1,
            'status' => trim((string) Request::post('status', 'draft')) ?: 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db()->table('documents')->insert($payload);
        $documentId = (int) $this->db()->lastInsertId();
        $this->recordLog('document_created', 'documents', $documentId, 'document', [], $payload);
        Session::flash('success', 'Document created successfully.');
        $this->redirect('/documents/' . $documentId);
    }

    public function show(string $id): void
    {
        $this->requireAuth();

        $document = $this->loadDocument((int) $id);
        if ($document === null) {
            Response::notFound();
        }

        $this->render('documents/show', ['document' => $document]);
    }

    public function edit(string $id): void
    {
        $this->requireAuth();

        $document = $this->loadDocument((int) $id);
        if ($document === null) {
            Response::notFound();
        }

        $this->render('documents/edit', ['document' => $document]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->ensureCsrf();

        $document = $this->requireRecord('documents', $id);
        $validator = Validator::make(Request::all(), [
            'title' => 'required|max:200',
            'type' => 'required|max:50',
        ]);
        if ($validator->fails()) {
            $this->flashValidation($validator->errors(), Request::all());
            Session::flash('error', 'Please correct the document form.');
            $this->back();
        }

        $update = [
            'title' => trim((string) Request::post('title')),
            'type' => trim((string) Request::post('type', 'other')) ?: 'other',
            'content' => Request::post('content', ''),
            'status' => trim((string) Request::post('status', 'draft')) ?: 'draft',
            'version' => ((int) ($document['version'] ?? 1)) + 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db()->table('documents')->where('id', (int) $id)->update($update);
        $this->recordLog('document_updated', 'documents', (int) $id, 'document', $document, $update);
        Session::flash('success', 'Document updated successfully.');
        $this->redirect('/documents/' . (int) $id . '/edit');
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->enforceRole('editor');
        $this->ensureCsrf();

        $document = $this->requireRecord('documents', $id);
        $this->db()->table('documents')->where('id', (int) $id)->delete();
        $this->recordLog('document_deleted', 'documents', (int) $id, 'document', $document);
        Session::flash('success', 'Document deleted successfully.');
        $this->redirect('/documents');
    }

    public function exportPdf(string $id): void
    {
        $this->requireAuth();

        $document = $this->loadDocument((int) $id);
        if ($document === null) {
            Response::notFound();
        }

        try {
            if (class_exists('App\\Services\\PdfService')) {
                $pdfService = new \App\Services\PdfService();
                if (method_exists($pdfService, 'streamDocument')) {
                    $pdfService->streamDocument($document);
                    return;
                }
                if (method_exists($pdfService, 'stream')) {
                    $pdfService->stream((string) ($document['title'] ?? 'document'), $this->buildPrintHtml($document));
                    return;
                }
            }
        } catch (Throwable) {
        }

        $this->renderHtml($this->buildPrintHtml($document));
    }

    public function printView(string $id): void
    {
        $this->requireAuth();

        $document = $this->loadDocument((int) $id);
        if ($document === null) {
            Response::notFound();
        }

        $this->renderHtml($this->buildPrintHtml($document));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadDocument(int $documentId): ?array
    {
        $document = $this->db()->fetch(
            'SELECT d.*, p.`title` AS `project_title`, p.`project_number`, t.`name` AS `template_name`
             FROM `documents` d
             INNER JOIN `projects` p ON p.`id` = d.`project_id`
             LEFT JOIN `templates` t ON t.`id` = d.`template_id`
             WHERE d.`id` = :id
             LIMIT 1',
            [':id' => $documentId]
        );

        return $document === false ? null : $document;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function buildPrintHtml(array $document): string
    {
        $content = (string) ($document['content'] ?? '');
        if ($content !== '' && str_starts_with(ltrim($content), '{')) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $content = '<pre>' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', ENT_QUOTES, 'UTF-8') . '</pre>';
            }
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>'
            . htmlspecialchars((string) ($document['title'] ?? 'Document'), ENT_QUOTES, 'UTF-8')
            . '</title><style>body{font-family:Arial,sans-serif;max-width:960px;margin:40px auto;color:#222}header{border-bottom:1px solid #ccc;margin-bottom:24px;padding-bottom:12px}pre{white-space:pre-wrap;background:#f7f7f7;padding:16px;border-radius:8px}</style></head><body>'
            . '<header><h1>' . htmlspecialchars((string) ($document['title'] ?? 'Document'), ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p>Project: ' . htmlspecialchars((string) ($document['project_title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p></header>'
            . '<main>' . $content . '</main></body></html>';
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
