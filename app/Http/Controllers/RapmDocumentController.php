<?php

namespace App\Http\Controllers;

use App\Models\RapmDocument;
use App\Services\RapmDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Streams a generated Review Summary / Routing Slip PDF — same convention as every other
 * document in this app (manuscript(), attachments, etc.): never a public URL, always
 * decrypted and streamed through an auth-checked controller action. One shared route works
 * for both the researcher (their own submission) and any admin, since both are legitimate
 * recipients of these documents.
 */
class RapmDocumentController extends Controller
{
    public function __construct(
        private readonly RapmDocumentService $documents,
    ) {}

    public function show(Request $request, RapmDocument $document): Response
    {
        $document->loadMissing('submission');
        $user = $request->user();

        // Reviewers only ever get a preview of an *approved* Review Summary they were
        // actually assigned to — a round that ended in revisions is still an internal
        // deliberation between reviewers and admins, not something to hand back to one
        // reviewer as a fait accompli while the researcher is still reworking it.
        $reviewerCanView = $user->isReviewer()
            && $document->kind === RapmDocument::KIND_REVIEW_SUMMARY
            && $document->outcome === RapmDocument::OUTCOME_APPROVED
            && $document->submission->reviewers()->whereKey($user->id)->exists();

        abort_unless($user->isAdmin() || $document->submission->researcher_id === $user->id || $reviewerCanView, 403);

        $label = $document->kind === RapmDocument::KIND_REVIEW_SUMMARY ? 'Review Summary' : 'Routing Slip';

        return response($this->documents->decryptedBytes($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($label).' - '.addslashes($document->submission->reference_code ?? (string) $document->research_submission_id).'.pdf"',
        ]);
    }
}
