<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        return view('researcher.submissions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSubmission($request);
        $status = $request->string('action')->value() === 'submit'
            ? SubmissionStatus::SUBMITTED
            : SubmissionStatus::DRAFT;

        $submission = $request->user()->submissions()->create([
            'title' => $validated['title'],
            'course' => $validated['course'],
            'authors' => $validated['authors'],
            'abstract' => $validated['abstract'],
            'keywords' => $validated['keywords'] ?? null,
            'status' => $status,
        ]);

        $this->storeDocuments($request, $submission, $status === SubmissionStatus::SUBMITTED ? 'initial-submission' : 'draft');

        return redirect()->route('submissions.show', $submission)
            ->with('status', 'Research submission saved.');
    }

    public function show(Request $request, ResearchSubmission $submission): View
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);

        $submission->load(['documents.uploader', 'reviews.reviewer']);

        return view('researcher.submissions.show', [
            'submission' => $submission,
        ]);
    }

    public function update(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless(in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REVISIONS_REQUIRED], true), 403);

        $validated = $this->validateSubmission($request);

        $submission->update([
            'title' => $validated['title'],
            'course' => $validated['course'],
            'authors' => $validated['authors'],
            'abstract' => $validated['abstract'],
            'keywords' => $validated['keywords'] ?? null,
        ]);

        $this->storeDocuments($request, $submission, 'updated-manuscript');

        return back()->with('status', 'Submission details updated.');
    }

    public function submit(Request $request, ResearchSubmission $submission): RedirectResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($submission->status === SubmissionStatus::DRAFT, 403);

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

    public function download(Request $request, ResearchSubmission $submission, ResearchDocument $document): StreamedResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($document->research_submission_id === $submission->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    private function validateSubmission(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'authors' => ['required', 'string'],
            'abstract' => ['required', 'string'],
            'keywords' => ['nullable', 'string'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);
    }

    private function storeDocuments(Request $request, ResearchSubmission $submission, string $documentType): void
    {
        foreach ($request->file('documents', []) as $file) {
            $path = $file->store('research-documents');

            $submission->documents()->create([
                'uploaded_by' => $request->user()->id,
                'document_type' => $documentType,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
            ]);
        }
    }
}