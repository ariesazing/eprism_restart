<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResearchSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        return view('researcher.submissions.index', [
            'submissions' => $request->user()->submissions()->with(['reviewer', 'reviews'])->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('researcher.submissions.create', [
            'existingTitles' => ResearchSubmission::query()->distinct()->pluck('title'),
            'organizationalUnits' => OrganizationalUnit::ordered(),
            'schoolPositions' => OrganizationalUnitPosition::schoolPositions(),
            'nonSchoolPositions' => OrganizationalUnitPosition::nonSchoolPositions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isSubmit = $request->string('action')->value() === 'submit';
        $validated = $this->validateSubmission($request, $isSubmit);
        $classification = $this->resolveClassification($validated['title'], $validated['classification']);

        $status = $isSubmit ? SubmissionStatus::SUBMITTED : SubmissionStatus::DRAFT;

        $submission = $request->user()->submissions()->create([
            'title' => $validated['title'],
            'research_type' => $validated['research_type'],
            'classification' => $classification,
            'course' => '',
            'authors' => '',
            'abstract' => '',
            'status' => $status,
        ]);

        $this->syncProponents($submission, $validated['proponents']);
        $this->storeManuscript($request, $submission);
        $this->storeDocuments($request, $submission, $status === SubmissionStatus::SUBMITTED ? 'initial-submission' : 'draft');

        return redirect()->route('submissions.show', $submission)
            ->with('status', 'Research submission saved.');
    }

    public function show(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);

        $submission->load(['proponents', 'documents.uploader', 'reviews.reviewer']);

        return view('researcher.submissions.show', [
            'submission' => $submission,
            'existingTitles' => ResearchSubmission::query()->distinct()->pluck('title'),
            'organizationalUnits' => OrganizationalUnit::ordered(),
            'schoolPositions' => OrganizationalUnitPosition::schoolPositions(),
            'nonSchoolPositions' => OrganizationalUnitPosition::nonSchoolPositions(),
        ]);
    }

    public function update(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless(in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REVISIONS_REQUIRED], true), 403);

        $validated = $this->validateSubmission($request, false);
        $classification = $this->resolveClassification($validated['title'], $validated['classification']);

        $submission->update([
            'title' => $validated['title'],
            'research_type' => $validated['research_type'],
            'classification' => $classification,
        ]);

        $this->syncProponents($submission, $validated['proponents']);
        $this->storeManuscript($request, $submission);
        $this->storeDocuments($request, $submission, 'updated-manuscript');

        return back()->with('status', 'Submission details updated.');
    }

    public function submit(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($submission->status === SubmissionStatus::DRAFT, 403);

        $missing = $this->missingRequiredDocumentTypes($submission);

        if ($missing->isNotEmpty()) {
            return back()->withErrors(['documents' => 'Required document(s) missing: ' . $missing->join(', ')]);
        }

        $submission->update(['status' => SubmissionStatus::SUBMITTED]);

        return back()->with('status', 'Submission sent for review.');
    }

    public function submitRevision(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($submission->status === SubmissionStatus::REVISIONS_REQUIRED, 403);

        $request->validate([
            'revision_document' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $path = $request->file('revision_document')->store('research-documents');

        $submission->documents()->create([
            'uploaded_by' => $request->user()->id,
            'document_type' => 'revision',
            'original_name' => $request->file('revision_document')->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $request->file('revision_document')->getMimeType(),
        ]);

        $submission->update([
            'status' => SubmissionStatus::RESUBMITTED,
            'admin_notes' => null,
        ]);

        return back()->with('status', 'Revision submitted.');
    }

    public function download(Request $request, ResearchSubmission $submission, ResearchDocument $document): BinaryFileResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return response()->download(Storage::path($document->path), $document->original_name);
    }

    private function validateSubmission(Request $request, bool $isSubmit = false): array
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

        if ($isSubmit) {
            $rules['manuscript'] = ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'];
            $rules = array_merge($rules, $this->submissionDocumentRules($request->input('classification')));
        } else {
            $rules['manuscript'] = ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'];
            $rules = array_merge($rules, $this->optionalDocumentRules());
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

    private function submissionDocumentRules(string $classification): array
    {
        if ($classification === 'proposal') {
            return [
                'documents.documentation' => ['required', 'file', 'mimes:pdf', 'max:10240'],
                'documents.narrative_form' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            ];
        }

        return [
            'documents.proposed_innovation' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'documents.approval_proposal' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'documents.documentation' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'documents.implementation_accomplishment_report' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'documents.implementation_certificate_of_implementation' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'documents.dissemination' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'documents.adoption' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'documents.utilization' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'documents.liquidation' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    private function optionalDocumentRules(): array
    {
        return [
            'documents.proposed_innovation' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documents.approval_proposal' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documents.documentation' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documents.narrative_form' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documents.implementation_accomplishment_report' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documents.implementation_certificate_of_implementation' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documents.dissemination' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documents.adoption' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documents.utilization' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documents.liquidation' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    private function resolveClassification(string $title, string $classification): string
    {
        return ResearchSubmission::query()->where('title', $title)->exists()
            ? $classification
            : 'proposal';
    }

    private function missingRequiredDocumentTypes(ResearchSubmission $submission)
    {
        $required = $submission->classification === 'proposal'
            ? ['manuscript', 'documentation', 'narrative_form']
            : [
                'manuscript',
                'proposed_innovation',
                'approval_proposal',
                'documentation',
                'implementation_accomplishment_report',
                'implementation_certificate_of_implementation',
                'dissemination',
                'adoption',
                'utilization',
                'liquidation',
            ];

        return collect($required)->filter(fn (string $type) => ! $submission->documents()->where('document_type', $type)->exists());
    }

    private function storeManuscript(Request $request, ResearchSubmission $submission): void
    {
        if ($request->hasFile('manuscript')) {
            $this->storeDocumentFile($request->file('manuscript'), $submission, 'manuscript');
        }
    }

    private function storeDocuments(Request $request, ResearchSubmission $submission, string $defaultDocumentType): void
    {
        foreach ($request->file('documents', []) as $documentType => $file) {
            if (is_array($file)) {
                foreach ($file as $nestedType => $nestedFile) {
                    $this->storeDocumentFile($nestedFile, $submission, $nestedType);
                }

                continue;
            }

            $this->storeDocumentFile($file, $submission, is_string($documentType) ? $documentType : $defaultDocumentType);
        }
    }

    private function storeDocumentFile($file, ResearchSubmission $submission, string $documentType): void
    {
        if (! $file || ! method_exists($file, 'getClientOriginalName')) {
            return;
        }

        $path = $file->store('research-documents');

        $submission->documents()->create([
            'uploaded_by' => request()->user()->id,
            'document_type' => $documentType,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
        ]);
    }
}
