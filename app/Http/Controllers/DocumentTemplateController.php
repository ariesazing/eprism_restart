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
use Illuminate\Validation\Rule;
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

    /**
     * Shared by update() (persists it) and preview() (applies it to the in-flight, unsaved
     * content) so both render identically — see normalizeAutoFormat()/encodeAutoFormat()
     * and SubmissionPdfComposer::compose() / pdf/template-shell.blade.php. A method rather
     * than a const since Rule::in(...) isn't a compile-time-constant expression.
     *
     * Two profiles, both optional:
     *  - `default`: applied across the whole research-content wrapper, same as the
     *    original whole-document behavior.
     *  - `sections.<key>`: applied only to that one chapter (see the data-af-section
     *    wrapper SubmissionHtmlTemplateRenderer::buildScalars() adds), overriding
     *    `default` per-property via CSS specificity, not by replacing it wholesale —
     *    see pdf/template-shell.blade.php. `<key>` isn't restricted here (it's whatever
     *    chapter keys the submitting template happens to have); restrictAutoFormatSections()
     *    drops anything that isn't actually one of this template's own section keys.
     *
     * @return array<string, array<int, mixed>>
     */
    private function autoFormatRules(): array
    {
        $profileRules = fn (string $prefix) => [
            "{$prefix}.font_family" => ['nullable', 'string', 'max:100'],
            "{$prefix}.font_size" => ['nullable', 'integer', 'min:6', 'max:72'],
            "{$prefix}.text_align" => ['nullable', Rule::in(['left', 'center', 'right', 'justify'])],
            "{$prefix}.line_height" => ['nullable', 'numeric', 'min:0.5', 'max:4'],
        ];

        return [
            'auto_format' => ['nullable', 'array'],
            'auto_format.default' => ['nullable', 'array'],
            ...$profileRules('auto_format.default'),
            'auto_format.sections' => ['nullable', 'array'],
            ...$profileRules('auto_format.sections.*'),
        ];
    }

    /**
     * Section keys arrive as request array keys (auto_format[sections][<key>][...]),
     * which Laravel's validator can check the *values* under but not restrict the keys
     * themselves to an allow-list — so this drops any key that isn't actually one of
     * the submitting template's own (non-table) section keys before it ever reaches
     * encodeAutoFormat()/the preview render, rather than trusting whatever the request
     * happened to send.
     *
     * @param  array<string, mixed>  $autoFormat
     * @return array<string, mixed>
     */
    private function restrictAutoFormatSections(array $autoFormat, ?SubmissionTemplate $submissionTemplate): array
    {
        if (empty($autoFormat['sections']) || ! is_array($autoFormat['sections'])) {
            return $autoFormat;
        }

        $validKeys = $submissionTemplate
            ? collect($submissionTemplate->sections)->reject(fn ($d) => $d->type === 'table')->pluck('key')->all()
            : [];

        $autoFormat['sections'] = array_intersect_key($autoFormat['sections'], array_flip($validKeys));

        return $autoFormat;
    }

    /**
     * Templates saved before per-section auto-format existed stored a flat
     * `{font_family, font_size, text_align, line_height}` shape directly (no
     * `default`/`sections` wrapper) — real, already-in-use admin configuration, not
     * something to discard. Lifts that flat shape into the new `default` profile so
     * it keeps applying exactly as before, and so it shows up correctly prefilled in
     * the edit form's now-relabeled "Default" row instead of appearing blank.
     *
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private static function migrateLegacyAutoFormatShape(array $decoded): array
    {
        if (isset($decoded['default']) || isset($decoded['sections'])) {
            return $decoded;
        }

        $knownKeys = ['font_family', 'font_size', 'text_align', 'line_height'];

        return array_intersect_key($decoded, array_flip($knownKeys)) !== [] ? ['default' => $decoded] : $decoded;
    }

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
            // Table-type sections (e.g. a proponents table) don't get their own
            // per-section auto-format row — they're substituted row-by-row via the
            // admin's own {{#each}} table markup, not a single ${key} scalar, so
            // there's no single element SubmissionHtmlTemplateRenderer could wrap in a
            // data-af-section marker for them.
            $autoFormatSections = collect($submissionTemplate->sections)
                ->reject(fn ($definition) => $definition->type === 'table')
                ->map(fn ($definition) => ['key' => $definition->key, 'label' => $definition->label])
                ->values();
        } elseif ($rapmTemplate = $this->findRapmTemplate($templateKey)) {
            $label = $rapmTemplate->label;
            $placeholders = ['scalars' => $rapmTemplate->scalars, 'each' => $rapmTemplate->each];
            $hasPreviewSubmission = $this->findRapmPreview($templateKey) !== null;
            $autoFormatSections = collect(); // RAPM templates have no chapter concept to scope to.
        } else {
            abort(404);
        }

        return view('admin.document-templates.edit', [
            'templateKey' => $templateKey,
            'templateLabel' => $label,
            'editorData' => $record?->content ? json_decode($record->content, true) : null,
            'pageOptions' => $record?->page_options ? json_decode($record->page_options, true) : null,
            'autoFormatOptions' => $record?->auto_format_options
                ? self::migrateLegacyAutoFormatShape(json_decode($record->auto_format_options, true))
                : [],
            'autoFormatSections' => $autoFormatSections,
            'placeholders' => $placeholders,
            'hasPreviewSubmission' => $hasPreviewSubmission,
        ]);
    }

    public function update(Request $request, string $templateKey): RedirectResponse
    {
        $label = $this->resolveLabel($templateKey);
        $submissionTemplate = $this->findSubmissionTemplate($templateKey);

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'page_options' => ['nullable', 'string'],
            'body_html' => ['required', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            ...$this->autoFormatRules(),
        ]);

        $autoFormat = $this->restrictAutoFormatSections($validated['auto_format'] ?? [], $submissionTemplate);

        $record = SubmissionDocumentTemplate::updateOrCreate(
            ['template_key' => $templateKey],
            [
                'content' => $validated['content'],
                'page_options' => $validated['page_options'] ?? null,
                'auto_format_options' => $this->encodeAutoFormat($autoFormat),
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
            ...$this->autoFormatRules(),
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
                'autoFormat' => $this->normalizeAutoFormat($this->restrictAutoFormatSections($validated['auto_format'] ?? [], $submissionTemplate)),
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

    /**
     * @param  array<string, mixed>  $autoFormat
     */
    private function encodeAutoFormat(array $autoFormat): ?string
    {
        $normalized = $this->normalizeAutoFormat($autoFormat);

        return $normalized === [] ? null : json_encode($normalized);
    }

    /**
     * Drops empty values from the `default` profile and from every `sections.<key>`
     * profile, then drops any profile (or the whole `sections` bucket) left empty
     * after that — so an admin who touched a section's fields and then cleared them
     * back out doesn't leave a dead `{}` entry sitting in the stored JSON forever.
     *
     * @param  array<string, mixed>  $autoFormat
     * @return array<string, mixed>
     */
    private function normalizeAutoFormat(array $autoFormat): array
    {
        $filterProfile = fn (array $profile) => array_filter($profile, fn ($value) => $value !== null && $value !== '');

        $normalized = [];

        if (! empty($autoFormat['default']) && is_array($autoFormat['default'])) {
            $default = $filterProfile($autoFormat['default']);

            if ($default !== []) {
                $normalized['default'] = $default;
            }
        }

        if (! empty($autoFormat['sections']) && is_array($autoFormat['sections'])) {
            $sections = [];

            foreach ($autoFormat['sections'] as $key => $profile) {
                if (! is_array($profile)) {
                    continue;
                }

                $profile = $filterProfile($profile);

                if ($profile !== []) {
                    $sections[$key] = $profile;
                }
            }

            if ($sections !== []) {
                $normalized['sections'] = $sections;
            }
        }

        return $normalized;
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
