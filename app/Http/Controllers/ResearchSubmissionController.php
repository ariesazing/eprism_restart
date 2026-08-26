<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use App\Models\SubmissionWindow;
use App\Services\ActivityLogger;
use App\Services\SubmissionAssessmentService;
use App\Services\SubmissionReadinessService;
use App\Services\SubmissionSectionService;
use App\Services\SubmissionSnapshotService;
use App\SubmissionTemplates\SubmissionTemplate;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResearchSubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionSectionService $sections,
        private readonly SubmissionSnapshotService $snapshots,
        private readonly SubmissionReadinessService $readiness,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): View
    {
        $query = $request->user()->submissions()->with(['reviewers', 'reviews']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('research_type')) {
            $query->where('research_type', $type);
        }

        if ($classification = $request->query('classification')) {
            $query->where('classification', $classification);
        }

        return view('researcher.submissions.index', [
            'submissions' => $query->latest()->get(),
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
                'research_type' => $type ?? '',
                'classification' => $classification ?? '',
            ],
        ]);
    }

    public function create(): View
    {
        return view('researcher.submissions.create', [
            'organizationalUnits' => OrganizationalUnit::activeOrdered(),
            'schoolPositions' => OrganizationalUnitPosition::schoolPositions(),
            'nonSchoolPositions' => OrganizationalUnitPosition::nonSchoolPositions(),
            'proposalWindowOpen' => SubmissionWindow::isOpenFor('proposal'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateHeader($request);

        if (! SubmissionWindow::isOpenFor($validated['classification'])) {
            return back()->withErrors(['classification' => ucfirst($validated['classification']).' research submissions are currently closed.'])->withInput();
        }

        $submission = $request->user()->submissions()->create([
            'title' => $validated['title'],
            'research_type' => $validated['research_type'],
            'classification' => $validated['classification'],
            'organizational_unit' => $validated['organizational_unit'],
            'organizational_unit_type' => $validated['organizational_unit_type'],
            'school_id' => $validated['school_id'] ?? null,
            'status' => SubmissionStatus::DRAFT,
        ]);

        $submission->update(['reference_code' => sprintf('EPRISM-%s-%06d', now()->year, $submission->id)]);

        $this->syncProponents($submission, $validated['proponents']);
        $this->sections->ensureSections($submission, $submission->template());

        $this->activity->log($request->user(), 'submission.created', $submission, "{$request->user()->name} created draft \"{$submission->title}\" ({$submission->reference_code}).");

        return redirect()->route('submissions.show', $submission)
            ->with('status', 'Draft created. Fill in each chapter, then submit for review when ready.');
    }

    public function show(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);

        $template = $submission->template();
        $sections = $this->sections->ensureSections($submission, $template);

        $submission->load(['proponents', 'reviewers', 'documents.uploader', 'reviews.reviewer']);

        return view('researcher.submissions.show', [
            'submission' => $submission,
            'template' => $template,
            'sections' => $sections,
            'organizationalUnits' => $this->organizationalUnitsIncludingCurrent($submission),
            'schoolPositions' => OrganizationalUnitPosition::schoolPositions(),
            'nonSchoolPositions' => OrganizationalUnitPosition::nonSchoolPositions(),
            'submissionWindowOpen' => SubmissionWindow::isOpenFor($submission->classification),
        ]);
    }

    /**
     * The active roster, plus this submission's own unit even if it's since gone
     * inactive — otherwise editing a submission tied to a now-retired unit would
     * silently drop it from the dropdown instead of just not offering it to new picks.
     */
    private function organizationalUnitsIncludingCurrent(ResearchSubmission $submission): Collection
    {
        $units = OrganizationalUnit::activeOrdered();

        if ($submission->organizational_unit && ! $units->contains('name', $submission->organizational_unit)) {
            $current = OrganizationalUnit::query()->where('name', $submission->organizational_unit)->first();

            if ($current) {
                $units = $units->push($current);
            }
        }

        return $units;
    }

    public function update(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless(! $submission->isLocked(), 403);

        $validated = $this->validateHeader($request, $submission);
        $template = SubmissionTemplateRegistry::for($validated['research_type'], $validated['classification']);

        $submission->update([
            'title' => $validated['title'],
            'research_type' => $validated['research_type'],
            'classification' => $validated['classification'],
            'organizational_unit' => $validated['organizational_unit'],
            'organizational_unit_type' => $validated['organizational_unit_type'],
            'school_id' => $validated['school_id'] ?? null,
        ]);

        $this->syncProponents($submission, $validated['proponents']);
        $this->sections->save($submission, $template, $request->input('sections', []));
        $this->storeAttachments($request, $submission, $template);

        $this->activity->log($request->user(), 'submission.updated', $submission, "{$request->user()->name} updated \"{$submission->title}\" ({$submission->reference_code}).");

        return back()->with('status', 'Submission updated.');
    }

    /**
     * Background autosave for a single chapter, hit every few seconds while a draft/revision
     * is being edited. Deliberately narrower than update(): it skips title/proponents/
     * attachments entirely and never re-validates the whole form, so a transiently-invalid
     * field elsewhere (or an in-progress attachment upload) can't block a chapter's content
     * from being saved, and repeated ticks can't reprocess file uploads or resurrect deleted
     * proponents the way replaying the full form payload would.
     */
    public function autosave(Request $request, ResearchSubmission $submission): JsonResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless(! $submission->isLocked(), 403);

        $validated = $request->validate([
            'section' => ['required', 'string'],
            'value' => ['present'],
        ]);

        $this->sections->saveOne($submission, $submission->template(), $validated['section'], $validated['value']);

        return response()->json(['saved_at' => now()->toIso8601String()]);
    }

    public function submit(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($submission->status === SubmissionStatus::DRAFT, 403);

        if (! SubmissionWindow::isOpenFor($submission->classification)) {
            return back()->withErrors(['submission' => ucfirst($submission->classification).' research submissions are currently closed.']);
        }

        if ($errors = $this->readiness->errors($submission)) {
            return back()->withErrors(['submission' => $errors]);
        }

        $this->snapshots->generate($submission, $request->user());
        $submission->update(['status' => SubmissionStatus::SUBMITTED]);

        $this->activity->log($request->user(), 'submission.submitted', $submission, "{$request->user()->name} submitted \"{$submission->title}\" ({$submission->reference_code}) for review.");

        return redirect()->route('submissions.show', $submission)->with('status', 'Submission sent for review.');
    }

    public function resubmit(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($submission->status === SubmissionStatus::REVISIONS_REQUIRED, 403);

        if ($errors = $this->readiness->errors($submission)) {
            return back()->withErrors(['submission' => $errors]);
        }

        $this->snapshots->generate($submission, $request->user());
        $submission->update(['status' => SubmissionStatus::RESUBMITTED, 'admin_notes' => null]);

        $this->activity->log($request->user(), 'submission.resubmitted', $submission, "{$request->user()->name} resubmitted \"{$submission->title}\" ({$submission->reference_code}) after revisions.");

        return redirect()->route('submissions.show', $submission)->with('status', 'Revision resubmitted for review.');
    }

    public function manuscript(Request $request, ResearchSubmission $submission): Response
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);

        return $this->streamManuscript($submission);
    }

    public function reviewManuscript(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);

        return view('submissions.document-review', [
            'submission' => $submission,
            'documentViewUrl' => route('submissions.manuscript', $submission),
            'commentsUrl' => route('submissions.comments.index', $submission),
            'backUrl' => route('submissions.show', $submission),
            'canCreate' => false,
            'canEditAll' => false,
        ]);
    }

    public function download(Request $request, ResearchSubmission $submission, ResearchDocument $document): BinaryFileResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return response()->download(Storage::path($document->path), $document->original_name);
    }

    public function view(Request $request, ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    /**
     * Only while the submission is still editable (draft or a revision being reworked) —
     * once it's been submitted for review, its attachments are frozen along with
     * everything else, same as isLocked() already governs for the rest of the form.
     */
    public function destroyAttachment(Request $request, ResearchSubmission $submission, ResearchDocument $document): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($document->research_submission_id === $submission->id, 404);
        abort_unless(! $submission->isLocked(), 403);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        $this->activity->log(
            $request->user(),
            'submission.attachment_removed',
            $submission,
            "{$request->user()->name} removed the attachment \"{$document->original_name}\" from \"{$submission->title}\" ({$submission->reference_code})."
        );

        return back()->with('status', 'Attachment removed.');
    }

    public function sram(Request $request, ResearchSubmission $submission, SubmissionAssessmentService $assessments): JsonResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);

        return response()->json($assessments->assess($submission));
    }

    protected function streamManuscript(ResearchSubmission $submission): Response
    {
        // A submitted submission has an immutable snapshot — that's the record reviewer
        // comments are anchored to, so it's always what gets shown while locked. A draft, or
        // a revision being reworked before resubmission, is still editable even though an
        // older snapshot exists from a prior round — gating on isLocked() rather than "a
        // snapshot exists" is what makes this a *live* preview of the current content against
        // whichever document template is active right now, not a stale pre-revision copy.
        $snapshot = $submission->isLocked() ? $submission->latestSnapshot() : null;

        $bytes = $snapshot !== null
            ? $this->snapshots->decryptedBytes($snapshot)
            : $this->snapshots->composePreview($submission);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($submission->title).'.pdf"',
        ]);
    }

    private function validateHeader(Request $request, ?ResearchSubmission $submission = null): array
    {
        $unitTypes = OrganizationalUnit::typeMap();

        $unit = $request->input('organizational_unit');
        $unitType = $unitTypes[$unit] ?? null;

        // The submitted org unit can fall out of the live roster (renamed, merged, or
        // removed since the submission was created) without the submission itself ever
        // changing — every save re-submits the whole header, including a field the
        // researcher never touched. If the value matches what's already stored, trust
        // the submission's own recorded type instead of rejecting the entire request:
        // otherwise a stale roster entry (see OrganizationalUnitSeeder's own docblock —
        // "removing a school here also removes it from the app") permanently blocks
        // every other edit (attachments, chapters) on an otherwise-untouched submission.
        // A genuinely new selection still has to exist in the current roster below.
        $allowedUnitNames = array_keys($unitTypes);
        if ($unitType === null && $submission !== null && $unit === $submission->organizational_unit) {
            $unitType = $submission->organizational_unit_type;
            $allowedUnitNames[] = $unit;
        }

        $proponentIndexes = array_keys((array) $request->input('proponents', []));
        $validPositions = OrganizationalUnitPosition::forType($unitType)->pluck('label');

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'research_type' => ['required', 'string', 'in:basic,action'],
            'classification' => ['required', 'string', 'in:proposal,completed'],
            'organizational_unit' => ['required', 'string', Rule::in($allowedUnitNames)],
            'school_id' => $unitType === 'school'
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'proponents' => ['required', 'array', 'min:1'],
        ];

        foreach ($proponentIndexes as $index) {
            $rules["proponents.$index.id"] = ['nullable', 'integer'];
            $rules["proponents.$index.last_name"] = ['required', 'string', 'max:255'];
            $rules["proponents.$index.first_name"] = ['required', 'string', 'max:255'];
            $rules["proponents.$index.middle_initial"] = ['nullable', 'string', 'max:10'];
            $rules["proponents.$index.email"] = ['nullable', 'email', 'max:255'];
            $rules["proponents.$index.contact_number"] = ['nullable', 'string', 'max:50'];
            $rules["proponents.$index.photo"] = ['nullable', 'image', 'max:10240'];
            $rules["proponents.$index.position"] = ['required', 'string', Rule::in($validPositions->all())];
        }

        $validated = $request->validate($rules);

        // Falls back to the already-resolved $unitType (which may itself be the
        // submission's own stored type, from the stale-roster case above) rather than
        // null — otherwise a validated-but-stale org unit would still wipe out the
        // submission's type on save.
        $validated['organizational_unit_type'] = $unitTypes[$validated['organizational_unit']] ?? $unitType;

        return $validated;
    }

    private function syncProponents(ResearchSubmission $submission, array $proponents): void
    {
        $keepIds = [];

        foreach (array_values($proponents) as $index => $proponent) {
            $existing = ! empty($proponent['id'])
                ? $submission->proponents()->find($proponent['id'])
                : null;

            $photoPath = $existing->photo_path ?? null;

            if (! empty($proponent['photo']) && method_exists($proponent['photo'], 'store')) {
                $photoPath = $proponent['photo']->store('research-photos');
            }

            $attributes = [
                'last_name' => $proponent['last_name'],
                'first_name' => $proponent['first_name'],
                'middle_initial' => $proponent['middle_initial'] ?? null,
                'email' => $proponent['email'] ?? null,
                'contact_number' => $proponent['contact_number'] ?? null,
                'photo_path' => $photoPath,
                'position' => $proponent['position'],
                'is_lead' => $index === 0,
                'sort_order' => ($index + 1) * 10,
            ];

            if ($existing) {
                $existing->update($attributes);
                $keepIds[] = $existing->id;
            } else {
                $keepIds[] = $submission->proponents()->create($attributes)->id;
            }
        }

        $submission->proponents()->whereNotIn('id', $keepIds)->delete();
    }

    private function storeAttachments(Request $request, ResearchSubmission $submission, SubmissionTemplate $template): void
    {
        $allowedKeys = $template->attachmentKeys();

        $rules = [];
        foreach ($allowedKeys as $key) {
            $rules["attachments.$key"] = ['nullable', 'array', 'max:5'];
            $rules["attachments.$key.*"] = ['file', 'mimes:pdf', 'max:10240'];
        }

        $validated = $request->validate($rules);

        foreach ($allowedKeys as $key) {
            $files = $validated['attachments'][$key] ?? [];

            foreach ($files as $file) {
                $submission->documents()->create([
                    'uploaded_by' => $request->user()->id,
                    'document_type' => $key,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $file->store('research-documents'),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }
    }
}
