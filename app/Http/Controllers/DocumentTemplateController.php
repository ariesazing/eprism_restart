<?php

namespace App\Http\Controllers;

use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Services\ActivityLogger;
use App\Services\SubmissionHtmlTemplateRenderer;
use App\Services\SubmissionPdfComposer;
use App\Services\SubmissionSectionService;
use App\SubmissionTemplates\SubmissionTemplate;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTemplateController extends Controller
{
    private const IMAGE_DISK = 'local';

    private const IMAGE_DIRECTORY = 'template-images';

    public function __construct(
        private readonly SubmissionSectionService $sections,
        private readonly ActivityLogger $activity,
    ) {}

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
            'pageOptions' => $record?->page_options ? json_decode($record->page_options, true) : null,
            'placeholders' => $this->placeholderReference($template),
            'hasPreviewSubmission' => $this->findPreviewSubmission($template) !== null,
        ]);
    }

    public function update(Request $request, string $templateKey): RedirectResponse
    {
        $this->findRegistryTemplate($templateKey);

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'page_options' => ['nullable', 'string'],
            'body_html' => ['required', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
        ]);

        $record = SubmissionDocumentTemplate::updateOrCreate(
            ['template_key' => $templateKey],
            [
                'content' => $validated['content'],
                'page_options' => $validated['page_options'] ?? null,
                'body_html' => $this->sections->sanitizeRichText($validated['body_html']),
                'header_html' => $this->sections->sanitizeRichText($validated['header_html'] ?? null),
                'footer_html' => $this->sections->sanitizeRichText($validated['footer_html'] ?? null),
                'updated_by' => $request->user()->id,
            ],
        );

        $template = $this->findRegistryTemplate($templateKey);

        $this->activity->log(
            $request->user(),
            'document-template.updated',
            $record,
            "{$request->user()->name} saved the \"{$template->label}\" document template."
        );

        return redirect()->route('admin.document-templates.edit', $templateKey)->with('status', 'Template saved.');
    }

    /**
     * Renders the posted (not yet saved) content against a real submission, so admins
     * can preview edits before committing them.
     */
    public function preview(Request $request, string $templateKey, SubmissionHtmlTemplateRenderer $renderer, SubmissionPdfComposer $composer): Response
    {
        $template = $this->findRegistryTemplate($templateKey);

        $validated = $request->validate([
            'body_html' => ['required', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'page_options' => ['nullable', 'string'],
        ]);

        $submission = $this->findPreviewSubmission($template);
        abort_unless($submission !== null, 422, "No {$template->label} submission exists yet to preview against.");

        $headerHtml = $renderer->render($validated['header_html'] ?? '', $submission);
        $footerHtml = $renderer->render($validated['footer_html'] ?? '', $submission);

        $html = view('pdf.template-shell', [
            'bodyHtml' => $renderer->render($validated['body_html'], $submission),
            'headerHtml' => $headerHtml,
            'footerHtml' => $footerHtml,
            'geometry' => $composer->resolveGeometry($validated['page_options'] ?? null, $headerHtml, $footerHtml),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4')->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    /**
     * Stores an image inserted via the template editor's toolbar as a real file instead
     * of embedding it as base64 in the document — a logo embedded inline (and duplicated
     * across the JSON content and its HTML mirror) is easily large enough to blow past
     * MySQL's max_allowed_packet on save. Only a short URL ends up in the database; the
     * bytes are only re-inflated to base64 transiently when a PDF is generated (see
     * SubmissionHtmlTemplateRenderer::inlineTemplateImages).
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = $validated['image']->store(self::IMAGE_DIRECTORY, self::IMAGE_DISK);
        $dimensions = getimagesize($validated['image']->getRealPath());

        return response()->json([
            'url' => route('admin.document-templates.images.show', basename($path)),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
        ]);
    }

    public function showImage(string $filename): StreamedResponse
    {
        $path = self::IMAGE_DIRECTORY.'/'.$filename;

        abort_unless(Storage::disk(self::IMAGE_DISK)->exists($path), 404);

        return Storage::disk(self::IMAGE_DISK)->response($path);
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
            // proponent_photo is deliberately absent here: canvas-editor is WYSIWYG-only,
            // so typing "${proponent_photo}" as text can never become a real <img> — it
            // renders as its own raw base64 value instead. Use the editor's own
            // "Insert > Proponent photo placeholder" inside this block instead of a token.
            ['key' => 'proponents', 'fields' => ['proponent_name', 'proponent_position']],
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
