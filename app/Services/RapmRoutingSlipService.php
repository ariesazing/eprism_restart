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

    public function generate(ResearchSubmission $submission, User $causer): ?RapmDocument
    {
        $documentTemplate = SubmissionDocumentTemplate::active(RapmDocument::KIND_ROUTING_SLIP);
        if ($documentTemplate === null) {
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
