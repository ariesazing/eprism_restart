<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\RoutingSlipReadyMail;
use App\Models\RapmDocument;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Notifications\SubmissionDecisionNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Generates the Routing Slip PDF — the end-to-end audit trail from submission to approval —
 * only once a submission reaches final approval (see SubmissionDecisionService::evaluate()).
 * Unlike RapmReviewSummaryService there's no fingerprint guard: final approval is terminal,
 * so this only ever runs once per submission.
 */
class RapmRoutingSlipService
{
    public function __construct(
        private readonly RapmDataBuilder $dataBuilder,
        private readonly RapmPdfComposer $composer,
    ) {}

    /**
     * Backfills a missing Routing Slip for an already fully-approved submission — the
     * single generate() call at final approval has no retry, so if it silently no-opped
     * back then (e.g. the routing_slip document template wasn't active yet), the
     * submission would otherwise be stuck showing a 404 wherever its routing slip is
     * linked forever. Called from every place a routing-slip link is rendered
     * (RepositoryController, AdminSubmissionController, ResearchSubmissionController) so
     * the document is guaranteed to exist by the time a user actually clicks it.
     */
    public function ensureGenerated(ResearchSubmission $submission): ?RapmDocument
    {
        $existing = $submission->latestRapmDocument(RapmDocument::KIND_ROUTING_SLIP);

        if ($existing !== null) {
            return $existing;
        }

        if ($submission->classification !== 'completed' || $submission->status !== \App\Enums\SubmissionStatus::APPROVED) {
            return null;
        }

        $causer = $submission->approver ?? $submission->researcher;

        if ($causer === null) {
            return null;
        }

        try {
            return $this->generate($submission, $causer);
        } catch (\Throwable $e) {
            Log::warning('Failed to backfill routing slip for submission '.$submission->id, ['exception' => $e]);

            return null;
        }
    }

    public function generate(ResearchSubmission $submission, User $causer): ?RapmDocument
    {
        $documentTemplate = SubmissionDocumentTemplate::active(RapmDocument::KIND_ROUTING_SLIP);
        if ($documentTemplate === null) {
            Log::warning('Routing slip not generated for submission '.$submission->id.': no active routing_slip document template.');

            return null;
        }

        $data = $this->dataBuilder->buildRoutingSlipData($submission);
        $pdf = $this->composer->compose($documentTemplate, $data['scalars'], $data['each']);

        $version = ($submission->latestRapmDocument(RapmDocument::KIND_ROUTING_SLIP)?->version ?? 0) + 1;
        $path = "rapm-documents/{$submission->id}/routing-slip/v{$version}.pdf.enc";
        Storage::disk('local')->put($path, Crypt::encrypt($pdf));

        $document = $submission->rapmDocuments()->create([
            'kind' => RapmDocument::KIND_ROUTING_SLIP,
            'version' => $version,
            'path' => $path,
            'generated_by' => $causer->id,
            'generated_at' => now(),
        ]);

        $this->notify($submission, $document);

        return $document;
    }

    private function notify(ResearchSubmission $submission, RapmDocument $document): void
    {
        $submission->loadMissing('researcher');

        $recipients = collect([$submission->researcher?->email])
            ->merge(User::query()->where('role', UserRole::ADMIN->value)->pluck('email'))
            ->filter()
            ->unique();

        foreach ($recipients as $email) {
            Mail::to($email)->send(new RoutingSlipReadyMail($submission, $document));
        }

        if ($submission->researcher !== null) {
            $submission->researcher->notify(new SubmissionDecisionNotification(
                $submission,
                'Routing Slip ready',
                route('rapm-documents.show', $document),
            ));
        }
    }
}
