<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\ReviewSummaryReadyMail;
use App\Models\RapmDocument;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Notifications\SubmissionDecisionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Generates the Review Summary PDF once every assigned reviewer has finished evaluating a
 * submission, and emails it to the researcher and admins — independent of whether that
 * round of reviews ends in approval or a revision request (see
 * SubmissionDecisionService::evaluate(), which calls this before branching on the outcome).
 */
class RapmReviewSummaryService
{
    public function __construct(
        private readonly RapmDataBuilder $dataBuilder,
        private readonly RapmPdfComposer $composer,
    ) {}

    /**
     * @param  Collection<int, \App\Models\Review>  $reviews  Keyed by reviewer_id, already confirmed complete by the caller.
     */
    public function maybeGenerate(ResearchSubmission $submission, Collection $reviews, User $causer): ?RapmDocument
    {
        $fingerprint = $this->fingerprint($reviews);

        $existing = $submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY);
        if ($existing !== null && $existing->fingerprint === $fingerprint) {
            return $existing;
        }

        $documentTemplate = SubmissionDocumentTemplate::active(RapmDocument::KIND_REVIEW_SUMMARY);
        if ($documentTemplate === null) {
            return null;
        }

        $data = $this->dataBuilder->buildReviewSummaryData($submission, $reviews);
        $pdf = $this->composer->compose($documentTemplate, $data['scalars'], $data['each']);

        $version = ($existing?->version ?? 0) + 1;
        $path = "rapm-documents/{$submission->id}/review-summary/v{$version}.pdf.enc";
        Storage::disk('local')->put($path, Crypt::encrypt($pdf));

        $document = $submission->rapmDocuments()->create([
            'kind' => RapmDocument::KIND_REVIEW_SUMMARY,
            'version' => $version,
            'path' => $path,
            'fingerprint' => $fingerprint,
            'generated_by' => $causer->id,
            'generated_at' => now(),
        ]);

        $this->notify($submission, $document);

        return $document;
    }

    /**
     * @param  Collection<int, \App\Models\Review>  $reviews
     */
    private function fingerprint(Collection $reviews): string
    {
        return md5(
            $reviews->map(fn ($review) => "{$review->reviewer_id}:{$review->updated_at}")
                ->sort()
                ->implode('|')
        );
    }

    private function notify(ResearchSubmission $submission, RapmDocument $document): void
    {
        $submission->loadMissing('researcher');

        $recipients = collect([$submission->researcher?->email])
            ->merge(User::query()->where('role', UserRole::ADMIN->value)->pluck('email'))
            ->filter()
            ->unique();

        foreach ($recipients as $email) {
            Mail::to($email)->send(new ReviewSummaryReadyMail($submission, $document));
        }

        if ($submission->researcher !== null) {
            $submission->researcher->notify(new SubmissionDecisionNotification(
                $submission,
                'Review Summary ready',
                route('rapm-documents.show', $document),
            ));
        }
    }
}
