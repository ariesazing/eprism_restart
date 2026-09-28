<?php

namespace App\Similarity\Sources;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSnapshot;
use App\Models\ResearchSubmission;
use App\Services\SubmissionSnapshotService;
use App\Similarity\Candidate;
use App\Similarity\CheckContext;
use App\Similarity\SimilaritySource;
use App\Similarity\TextExtractor;
use Illuminate\Support\Facades\Process;
use Throwable;

final class RepositorySource implements SimilaritySource
{
    public function __construct(private readonly TextExtractor $extractor) {}

    public function type(): string
    {
        return 'repository';
    }

    public function label(): string
    {
        return 'System repository';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function notConfiguredMessage(): string
    {
        return '';
    }

    public function candidates(CheckContext $context): iterable
    {
        $count = 0;
        $submissions = ResearchSubmission::query()->whereKeyNot($context->submission->id)
            ->where(fn ($query) => $query->whereNotNull('proposal_approved_at')
                ->orWhere('status', SubmissionStatus::APPROVED->value));

        foreach ($submissions->lazyById(100) as $submission) {
            foreach (['proposal', 'completed'] as $stage) {
                $approvedAt = $stage === 'proposal' ? $submission->proposal_approved_at : $submission->approved_at;
                if (! $approvedAt || ($stage === 'completed' && $submission->status !== SubmissionStatus::APPROVED)) {
                    continue;
                }
                if ($context->expired()) {
                    $context->warn('The repository check reached its time limit. Results cover only the documents processed so far.');

                    return;
                }
                try {
                    if ($submission->usesManuscript()) {
                        $version = $submission->approvedManuscriptVersion($stage);
                        $paragraphs = $version && ! $version->files_pruned_at
                            ? $this->extractor->docxParagraphs($version->docx_path) : null;
                        $text = $paragraphs === null ? '' : implode("\n\n", $paragraphs);
                    } else {
                        $snapshot = $submission->snapshotApprovedAsOf($approvedAt);
                        $text = $snapshot ? $this->snapshotText($snapshot) : '';
                    }
                    if (trim($text) === '') {
                        $context->warn('Some approved repository documents have no readable text and were skipped.');

                        continue;
                    }
                    $count++;
                    // Corpus comparison does not grant access to another owner's files.
                    yield new Candidate('repository', $submission->title.' ('.$stage.')', null, $text,
                        ['submission_id' => $submission->id, 'classification' => $stage, 'reference_code' => $submission->reference_code]);
                } catch (Throwable $e) {
                    report($e);
                    $context->warn('Some approved repository documents could not be read. Results are partial.');
                }
            }
        }
        if ($count === 0) {
            $context->warn('No readable approved documents from other research were available in the repository. A zero score does not establish originality.');
        }
    }

    private function snapshotText(ResearchSnapshot $snapshot): string
    {
        if ($snapshot->similarity_text !== null) {
            return $snapshot->similarity_text;
        }
        // Older snapshots predate text capture. Read the immutable PDF, never today's draft.
        $path = tempnam(sys_get_temp_dir(), 'repository-text-');
        try {
            file_put_contents($path, app(SubmissionSnapshotService::class)->decryptedBytes($snapshot));
            $result = Process::timeout(30)->run([config('similarity.pdftotext_binary', 'pdftotext'), '-enc', 'UTF-8', $path, '-']);
            if (! $result->successful()) {
                throw new \RuntimeException('Could not extract the approved repository PDF text. '.$result->errorOutput());
            }
            $text = trim($result->output());
            $snapshot->update(['similarity_text' => $text]);

            return $text;
        } finally {
            @unlink($path);
        }
    }
}
