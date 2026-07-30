<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use App\Services\SubmissionSectionService;
use App\Services\SubmissionSnapshotService;
use App\SubmissionTemplates\SubmissionTemplate;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Illuminate\Contracts\View\View;
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
    ) {}

    public function index(Request $request): View
    {
        return view('researcher.submissions.index', [
            'submissions' => $request->user()->submissions()->with(['reviewer', 'reviews'])->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('researcher.submissions.create', [
            'organizationalUnits' => OrganizationalUnit::ordered(),
            'schoolPositions' => OrganizationalUnitPosition::schoolPositions(),
            'nonSchoolPositions' => OrganizationalUnitPosition::nonSchoolPositions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateHeader($request);

        $submission = $request->user()->submissions()->create([
            'title' => $validated['title'],
            'research_type' => $validated['research_type'],
            'classification' => $validated['classification'],
            'status' => SubmissionStatus::DRAFT,
        ]);

        $this->syncProponents($submission, $validated['proponents']);
        $this->sections->ensureSections($submission, $submission->template());

        return redirect()->route('submissions.show', $submission)
            ->with('status', 'Draft created. Fill in each chapter, then submit for review when ready.');
    }

    public function show(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);

        $template = $submission->template();
        $sections = $this->sections->ensureSections($submission, $template);

        $submission->load(['proponents', 'documents.uploader', 'reviews.reviewer']);

        return view('researcher.submissions.show', [
            'submission' => $submission,
            'template' => $template,
            'sections' => $sections,
            'organizationalUnits' => OrganizationalUnit::ordered(),
            'schoolPositions' => OrganizationalUnitPosition::schoolPositions(),
            'nonSchoolPositions' => OrganizationalUnitPosition::nonSchoolPositions(),
        ]);
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
        ]);

        $this->syncProponents($submission, $validated['proponents']);
        $this->sections->save($submission, $template, $request->input('sections', []));
        $this->storeAttachments($request, $submission, $template);

        return back()->with('status', 'Submission updated.');
    }

    public function submit(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($submission->status === SubmissionStatus::DRAFT, 403);

        if ($errors = $this->completenessErrors($submission)) {
            return back()->withErrors(['submission' => $errors]);
        }

        $this->snapshots->generate($submission, $request->user());
        $submission->update(['status' => SubmissionStatus::SUBMITTED]);

        return redirect()->route('submissions.show', $submission)->with('status', 'Submission sent for review.');
    }

    public function resubmit(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($submission->status === SubmissionStatus::REVISIONS_REQUIRED, 403);

        if ($errors = $this->completenessErrors($submission)) {
            return back()->withErrors(['submission' => $errors]);
        }

        $this->snapshots->generate($submission, $request->user());
        $submission->update(['status' => SubmissionStatus::RESUBMITTED, 'admin_notes' => null]);

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

    protected function streamManuscript(ResearchSubmission $submission): Response
    {
        $snapshot = $submission->latestSnapshot();
        abort_unless($snapshot !== null, 404);

        return response($this->snapshots->decryptedBytes($snapshot), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($submission->title).'.pdf"',
        ]);
    }

    protected function completenessErrors(ResearchSubmission $submission): array
    {
        $template = $submission->template();
        $errors = [];

        $missingSections = $this->sections->missingRequiredSections($submission, $template);
        if ($missingSections->isNotEmpty()) {
            $errors[] = 'Missing content for: '.$missingSections->join(', ');
        }

        $uploadedTypes = $submission->documents()->pluck('document_type')->all();
        $missingAttachments = collect($template->attachments)
            ->where('required', true)
            ->reject(fn ($attachment) => in_array($attachment->key, $uploadedTypes, true))
            ->pluck('label');

        if ($missingAttachments->isNotEmpty()) {
            $errors[] = 'Missing required attachment(s): '.$missingAttachments->join(', ');
        }

        return $errors;
    }

    private function validateHeader(Request $request, ?ResearchSubmission $submission = null): array
    {
        $unitTypes = OrganizationalUnit::typeMap();
        $proponentIndexes = array_keys((array) $request->input('proponents', []));

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'research_type' => ['required', 'string', 'in:basic,action'],
            'classification' => ['required', 'string', 'in:proposal,completed'],
            'proponents' => ['required', 'array', 'min:1'],
        ];

        foreach ($proponentIndexes as $index) {
            $unit = $request->input("proponents.$index.organizational_unit");
            $unitType = $unitTypes[$unit] ?? null;

            $validPositions = $unitType
                ? OrganizationalUnitPosition::query()->where('organizational_unit_type', $unitType)->pluck('label')
                : collect();

            $rules["proponents.$index.id"] = ['nullable', 'integer'];
            $rules["proponents.$index.last_name"] = ['required', 'string', 'max:255'];
            $rules["proponents.$index.first_name"] = ['required', 'string', 'max:255'];
            $rules["proponents.$index.middle_initial"] = ['nullable', 'string', 'max:10'];
            $rules["proponents.$index.email"] = ['required', 'email', 'max:255'];
            $rules["proponents.$index.contact_number"] = ['required', 'string', 'max:50'];
            $rules["proponents.$index.photo"] = ['nullable', 'image', 'max:10240'];
            $rules["proponents.$index.organizational_unit"] = ['required', 'string', Rule::in(array_keys($unitTypes))];
            $rules["proponents.$index.position"] = ['required', 'string', Rule::in($validPositions->all())];
            $rules["proponents.$index.school_id"] = $unitType === 'school'
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        foreach ($proponentIndexes as $index) {
            $unit = $validated['proponents'][$index]['organizational_unit'];
            $validated['proponents'][$index]['organizational_unit_type'] = $unitTypes[$unit] ?? null;
        }

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
                'email' => $proponent['email'],
                'contact_number' => $proponent['contact_number'],
                'photo_path' => $photoPath,
                'organizational_unit' => $proponent['organizational_unit'],
                'organizational_unit_type' => $proponent['organizational_unit_type'],
                'position' => $proponent['position'],
                'school_id' => $proponent['school_id'] ?? null,
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
            $rules["attachments.$key"] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];
        }

        $validated = $request->validate($rules);

        foreach ($allowedKeys as $key) {
            $file = $validated['attachments'][$key] ?? null;

            if (! $file) {
                continue;
            }

            $submission->documents()->where('document_type', $key)->get()->each(function (ResearchDocument $existing) {
                Storage::disk('local')->delete($existing->path);
                $existing->delete();
            });

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
