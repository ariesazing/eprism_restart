<?php

namespace App\Http\Controllers;

use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Services\SubmissionHtmlTemplateRenderer;
use App\Services\SubmissionSectionService;
use App\SubmissionTemplates\SubmissionTemplate;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentTemplateController extends Controller
{
    public function __construct(private readonly SubmissionSectionService $sections) {}

    public function index(): View
    {
        $active = SubmissionDocumentTemplate::query()->with('updater:id,name')->get()->keyBy('template_key');

        $templates = collect(SubmissionTemplateRegistry::all())->map(fn (SubmissionTemplate $template) => [
            'key' => $template->key,
            'label' => $template->label,
            'record' => $active->get($template->key),
        ]);

        return view('admin.document-templates.index', ['templates' => $templates]);
    }

    public function edit(string $templateKey): View
    {
        $template = $this->findRegistryTemplate($templateKey);
        $record = SubmissionDocumentTemplate::active($templateKey);

        return view('admin.document-templates.edit', [
            'templateKey' => $templateKey,
            'templateLabel' => $template->label,
            'editorData' => $record?->content ? json_decode($record->content, true) : null,
            'placeholders' => $this->placeholderReference($template),
            'hasPreviewSubmission' => $this->findPreviewSubmission($template) !== null,
        ]);
    }

    public function update(Request $request, string $templateKey): RedirectResponse
    {
        $this->findRegistryTemplate($templateKey);

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'body_html' => ['required', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
        ]);

        SubmissionDocumentTemplate::updateOrCreate(
            ['template_key' => $templateKey],
            [
                'content' => $validated['content'],
                'body_html' => $this->sections->sanitizeRichText($validated['body_html']),
                'header_html' => $this->sections->sanitizeRichText($validated['header_html'] ?? null),
                'footer_html' => $this->sections->sanitizeRichText($validated['footer_html'] ?? null),
                'updated_by' => $request->user()->id,
            ],
        );

        return redirect()->route('admin.document-templates.edit', $templateKey)->with('status', 'Template saved.');
    }

    /**
     * Renders the posted (not yet saved) content against a real submission, so admins
     * can preview edits before committing them.
     */
    public function preview(Request $request, string $templateKey, SubmissionHtmlTemplateRenderer $renderer): Response
    {
        $template = $this->findRegistryTemplate($templateKey);

        $validated = $request->validate([
            'body_html' => ['required', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
        ]);

        $submission = $this->findPreviewSubmission($template);
        abort_unless($submission !== null, 422, "No {$template->label} submission exists yet to preview against.");

        $html = view('pdf.template-shell', [
            'bodyHtml' => $renderer->render($validated['body_html'], $submission),
            'headerHtml' => $renderer->render($validated['header_html'] ?? '', $submission),
            'footerHtml' => $renderer->render($validated['footer_html'] ?? '', $submission),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4')->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    private function findRegistryTemplate(string $templateKey): SubmissionTemplate
    {
        foreach (SubmissionTemplateRegistry::all() as $template) {
            if ($template->key === $templateKey) {
                return $template;
            }
        }

        abort(404);
    }

    private function findPreviewSubmission(SubmissionTemplate $template): ?ResearchSubmission
    {
        return ResearchSubmission::query()
            ->where('research_type', $template->researchType)
            ->where('classification', $template->classification)
            ->latest()
            ->first();
    }

    /**
     * @return array{scalars: array<int, string>, each: array<int, array{key: string, fields: array<int, string>}>}
     */
    private function placeholderReference(SubmissionTemplate $template): array
    {
        $scalars = ['title', 'research_type_label', 'classification_label', 'organizational_unit', 'school_id', 'template_label', 'generated_at'];
        $each = [
            ['key' => 'proponents', 'fields' => ['proponent_name', 'proponent_position', 'proponent_photo']],
        ];

        foreach ($template->sections as $section) {
            if ($section->type === 'table') {
                $each[] = ['key' => $section->key, 'fields' => array_map(fn ($c) => $c['key'], $section->columns)];
            } else {
                $scalars[] = $section->key;
            }
        }

        return ['scalars' => $scalars, 'each' => $each];
    }
}
