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
use App\Services\SubmissionSectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Admin template management for RAPM's two documents — structured exactly like
 * DocumentTemplateController, reading from RapmTemplateRegistry instead of
 * SubmissionTemplateRegistry. Image uploads reuse DocumentTemplateController's existing
 * admin.document-templates.images.* routes rather than duplicating them: that endpoint
 * already stores to a template-agnostic `template-images/` disk directory.
 */
class RapmTemplateController extends Controller
{
    public function __construct(
        private readonly SubmissionSectionService $sections,
        private readonly ActivityLogger $activity,
        private readonly RapmDataBuilder $dataBuilder,
        private readonly RapmPdfComposer $composer,
    ) {}

    public function index(): View
    {
        $active = SubmissionDocumentTemplate::query()->with('updater:id,name')->get()->keyBy('template_key');

        $templates = collect(RapmTemplateRegistry::all())->map(fn (RapmTemplate $template) => [
            'key' => $template->key,
            'label' => $template->label,
            'record' => $active->get($template->key),
        ]);

        return view('admin.rapm-templates.index', ['templates' => $templates]);
    }

    public function edit(string $templateKey): View
    {
        $template = $this->findRegistryTemplate($templateKey);
        $record = SubmissionDocumentTemplate::active($templateKey);

        return view('admin.rapm-templates.edit', [
            'templateKey' => $templateKey,
            'templateLabel' => $template->label,
            'editorData' => $record?->content ? json_decode($record->content, true) : null,
            'pageOptions' => $record?->page_options ? json_decode($record->page_options, true) : null,
            'placeholders' => ['scalars' => $template->scalars, 'each' => $template->each],
            'hasPreviewSubmission' => $this->findPreviewSubmission($templateKey) !== null,
        ]);
    }

    public function update(Request $request, string $templateKey): RedirectResponse
    {
        $template = $this->findRegistryTemplate($templateKey);

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
            'rapm-template.updated',
            $record,
            "{$request->user()->name} saved the \"{$template->label}\" RAPM template."
        );

        return redirect()->route('admin.rapm-templates.edit', $templateKey)->with('status', 'Template saved.');
    }

    /**
     * Renders the posted (not yet saved) content against a real submission's review/routing
     * data, so admins can preview edits before committing them.
     */
    public function preview(Request $request, string $templateKey): Response
    {
        $this->findRegistryTemplate($templateKey);

        $validated = $request->validate([
            'body_html' => ['required', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'page_options' => ['nullable', 'string'],
        ]);

        $submission = $this->findPreviewSubmission($templateKey);
        abort_unless($submission !== null, 422, 'No submission exists yet to preview this template against.');

        $documentTemplate = new SubmissionDocumentTemplate([
            'body_html' => $validated['body_html'],
            'header_html' => $validated['header_html'] ?? '',
            'footer_html' => $validated['footer_html'] ?? '',
            'page_options' => $validated['page_options'] ?? null,
        ]);

        $data = $templateKey === RapmDocument::KIND_ROUTING_SLIP
            ? $this->dataBuilder->buildRoutingSlipData($submission)
            : $this->dataBuilder->buildReviewSummaryData($submission, $submission->reviews()->with('reviewer')->get()->keyBy('reviewer_id'));

        $pdf = $this->composer->compose($documentTemplate, $data['scalars'], $data['each']);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    private function findRegistryTemplate(string $templateKey): RapmTemplate
    {
        try {
            return RapmTemplateRegistry::for($templateKey);
        } catch (\InvalidArgumentException) {
            abort(404);
        }
    }

    /**
     * A review summary previews best against a submission with at least one review on file;
     * a routing slip previews best against one with some activity history. Falls back to any
     * submission at all so preview is still available early on.
     */
    private function findPreviewSubmission(string $templateKey): ?ResearchSubmission
    {
        $query = ResearchSubmission::query();

        if ($templateKey === RapmDocument::KIND_REVIEW_SUMMARY) {
            $query->whereHas('reviews');
        }

        return $query->latest()->first() ?? ResearchSubmission::query()->latest()->first();
    }
}
