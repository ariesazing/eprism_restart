<?php

namespace App\Http\Controllers;

use App\Models\RapmDocument;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Rapm\RapmTemplate;
use App\Rapm\RapmTemplateRegistry;
use App\Services\ActivityLogger;
use App\Services\RapmDataBuilder;
use App\Services\RapmPdfComposer;
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

/**
 * Admin management for every admin-editable PDF template in the system: both the
 * per-research-type submission chapters (SubmissionTemplateRegistry) and RAPM's two
 * process documents, Review Summary and Routing Slip (RapmTemplateRegistry). They share
 * one page/route group because they're the same underlying concept to an admin — an HTML
 * template stored in `submission_document_templates`, edited with the same canvas-editor —
 * even though the two registries shape their placeholder data very differently (research
 * chapters/proponents vs. review/routing data), which is why edit()/preview() branch on
 * which registry a given key belongs to.
 */
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

        $submissionTemplates = collect(SubmissionTemplateRegistry::all())->map(fn (SubmissionTemplate $template) => [
            'key' => $template->key,
            'label' => $template->label,
            'record' => $active->get($template->key),
        ]);

        $rapmTemplates = collect(RapmTemplateRegistry::all())->map(fn (RapmTemplate $template) => [
            'key' => $template->key,
            'label' => $template->label,
            'record' => $active->get($template->key),
        ]);

        return view('admin.document-templates.index', [
            'templates' => $submissionTemplates,
            'rapmTemplates' => $rapmTemplates,
        ]);
    }

    public function edit(string $templateKey): View
    {
        $record = SubmissionDocumentTemplate::active($templateKey);

        if ($submissionTemplate = $this->findSubmissionTemplate($templateKey)) {
            $label = $submissionTemplate->label;
            $placeholders = $this->placeholderReference($submissionTemplate);
            $hasPreviewSubmission = $this->findSubmissionPreview($submissionTemplate) !== null;
        } elseif ($rapmTemplate = $this->findRapmTemplate($templateKey)) {
            $label = $rapmTemplate->label;
            $placeholders = ['scalars' => $rapmTemplate->scalars, 'each' => $rapmTemplate->each];
            $hasPreviewSubmission = $this->findRapmPreview($templateKey) !== null;
        } else {
            abort(404);
        }

        return view('admin.document-templates.edit', [
            'templateKey' => $templateKey,
            'templateLabel' => $label,
            'editorData' => $record?->content ? json_decode($record->content, true) : null,
            'pageOptions' => $record?->page_options ? json_decode($record->page_options, true) : null,
            'placeholders' => $placeholders,
            'hasPreviewSubmission' => $hasPreviewSubmission,
        ]);
    }

    public function update(Request $request, string $templateKey): RedirectResponse
    {
        $label = $this->resolveLabel($templateKey);

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

        $this->activity->log(
            $request->user(),
            'document-template.updated',
            $record,
            "{$request->user()->name} saved the \"{$label}\" document template."
        );

        return redirect()->route('admin.document-templates.edit', $templateKey)->with('status', 'Template saved.');
    }

    /**
     * Renders the posted (not yet saved) content before it's committed — against a real
     * submission's chapters for a submission template, or against a submission's review/
     * routing data (via RapmDataBuilder) for a RAPM template.
     */
    public function preview(
        Request $request,
        string $templateKey,
        SubmissionHtmlTemplateRenderer $renderer,
        SubmissionPdfComposer $composer,
        RapmDataBuilder $rapmDataBuilder,
        RapmPdfComposer $rapmComposer,
    ): Response {
        $validated = $request->validate([
            'body_html' => ['required', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'page_options' => ['nullable', 'string'],
        ]);

        if ($submissionTemplate = $this->findSubmissionTemplate($templateKey)) {
            $submission = $this->findSubmissionPreview($submissionTemplate);
            abort_unless($submission !== null, 422, "No {$submissionTemplate->label} submission exists yet to preview against.");

            $headerHtml = $renderer->render($validated['header_html'] ?? '', $submission);
            $footerHtml = $renderer->render($validated['footer_html'] ?? '', $submission);

            $html = view('pdf.template-shell', [
                'bodyHtml' => $renderer->render($validated['body_html'], $submission),
                'headerHtml' => $headerHtml,
                'footerHtml' => $footerHtml,
                'geometry' => $composer->resolveGeometry($validated['page_options'] ?? null, $headerHtml, $footerHtml),
            ])->render();

            $pdf = Pdf::loadHTML($html)->setPaper('a4')->output();
        } elseif ($this->findRapmTemplate($templateKey)) {
            $submission = $this->findRapmPreview($templateKey);
            abort_unless($submission !== null, 422, 'No submission exists yet to preview this template against.');

            $documentTemplate = new SubmissionDocumentTemplate([
                'body_html' => $validated['body_html'],
                'header_html' => $validated['header_html'] ?? '',
                'footer_html' => $validated['footer_html'] ?? '',
                'page_options' => $validated['page_options'] ?? null,
            ]);

            $data = $templateKey === RapmDocument::KIND_ROUTING_SLIP
                ? $rapmDataBuilder->buildRoutingSlipData($submission)
                : $rapmDataBuilder->buildReviewSummaryData($submission, $submission->reviews()->with('reviewer')->get()->keyBy('reviewer_id'));

            $pdf = $rapmComposer->compose($documentTemplate, $data['scalars'], $data['each']);
        } else {
            abort(404);
        }

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
     * SubmissionHtmlTemplateRenderer::inlineTemplateImages). Shared by both the submission
     * and RAPM template editors — the disk directory isn't keyed by template.
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

    private function findSubmissionTemplate(string $templateKey): ?SubmissionTemplate
    {
        foreach (SubmissionTemplateRegistry::all() as $template) {
            if ($template->key === $templateKey) {
                return $template;
            }
        }

        return null;
    }

    private function findRapmTemplate(string $templateKey): ?RapmTemplate
    {
        try {
            return RapmTemplateRegistry::for($templateKey);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function resolveLabel(string $templateKey): string
    {
        return $this->findSubmissionTemplate($templateKey)?->label
            ?? $this->findRapmTemplate($templateKey)?->label
            ?? abort(404);
    }

    private function findSubmissionPreview(SubmissionTemplate $template): ?ResearchSubmission
    {
        return ResearchSubmission::query()
            ->where('research_type', $template->researchType)
            ->where('classification', $template->classification)
            ->latest()
            ->first();
    }

    /**
     * A review summary previews best against a submission with at least one review on file;
     * a routing slip previews fine against any submission. Falls back to any submission at
     * all so preview is still available early on, before any submission has been reviewed.
     */
    private function findRapmPreview(string $templateKey): ?ResearchSubmission
    {
        $query = ResearchSubmission::query();

        if ($templateKey === RapmDocument::KIND_REVIEW_SUMMARY) {
            $query->whereHas('reviews');
        }

        return $query->latest()->first() ?? ResearchSubmission::query()->latest()->first();
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
