<?php

namespace App\Similarity;

use App\Models\SimilarityCheck;
use App\Models\SimilarityMatch;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs one similarity check end to end: extract the submission's text, ask each source for
 * candidate texts, compare every candidate against the document, and record which sources
 * overlap it and where.
 *
 * What the numbers mean — and don't:
 *  - Detects matching wording in approved repository documents, excluding this research.
 *    Reworded passages and sources outside the repository are not checked.
 *  - The overall score is the share of the document's words covered by a match in *any*
 *    source, counted once (overlapping sources don't add up); each source's own percentage is
 *    its own overlap, counted independently.
 *  - A high score isn't proof of plagiarism — quotations, standard phrases and reused
 *    boilerplate all match. It's a screening aid for a human to interpret.
 */
final class SimilarityCheckRunner
{
    public function __construct(
        private readonly TextExtractor $extractor,
        private readonly SourceRegistry $sources,
    ) {}

    public function run(SimilarityCheck $check): void
    {
        // Claimed atomically (queued -> running in one UPDATE), so a job the queue delivers twice
        // can't run the same check twice: with several workers and a long check, a second worker
        // will pick the job up again once the queue's retry_after elapses, while the first is still
        // working. Only the delivery that wins this UPDATE does the work.
        $claimed = SimilarityCheck::query()
            ->whereKey($check->id)
            ->where('status', SimilarityCheck::STATUS_QUEUED)
            ->update(['status' => SimilarityCheck::STATUS_RUNNING, 'started_at' => now(), 'error' => null, 'source_scope' => 'repository']);

        if ($claimed === 0) {
            return;
        }

        $check->refresh();

        try {
            $this->execute($check);
        } catch (Throwable $e) {
            report($e);

            $this->fail($check, 'The similarity check could not be completed. Please try again in a moment.');
        }
    }

    private function execute(SimilarityCheck $check): void
    {
        $paragraphs = $this->extractor->paragraphs($check->submission);
        $index = DocumentIndex::build($paragraphs, (int) config('similarity.shingle_size'));
        $total = $index->tokenCount();

        $minimum = (int) config('similarity.min_document_words');

        if ($total < $minimum) {
            $this->fail($check, "There isn't enough text to check yet — write at least {$minimum} words in your chapters first.");

            return;
        }

        $context = new CheckContext(
            $check->submission,
            $paragraphs,
            $index,
            microtime(true) + (int) config('similarity.time_budget_seconds'),
        );

        $found = $this->collectMatches($context);

        usort($found, fn (array $a, array $b) => $b['words'] <=> $a['words']);
        $found = array_slice($found, 0, (int) config('similarity.max_sources'));

        $covered = [];
        foreach ($found as $match) {
            foreach ($match['spans'] as $span) {
                for ($i = $span['from']; $i <= $span['to']; $i++) {
                    $covered[$i] = true;
                }
            }
        }

        DB::transaction(function () use ($check, $found, $index, $paragraphs, $total, $covered, $context) {
            foreach ($found as $position => $match) {
                SimilarityMatch::create([
                    'similarity_check_id' => $check->id,
                    'rank' => $position + 1,
                    'source_type' => $match['type'],
                    'title' => mb_substr($match['title'], 0, 500),
                    'url' => $match['url'],
                    'meta' => $match['meta'],
                    'matched_words' => $match['words'],
                    'percent' => round($match['words'] / $total * 100, 2),
                    'spans' => array_map(fn (array $span) => [
                        'para' => $index->tokens[$span['from']]['para'],
                        'start' => $index->tokens[$span['from']]['start'],
                        'end' => $index->tokens[$span['to']]['end'],
                        'words' => $span['to'] - $span['from'] + 1,
                    ], $match['spans']),
                ]);
            }

            $check->update([
                'status' => SimilarityCheck::STATUS_COMPLETED,
                'document' => $paragraphs,
                'text_hash' => hash('sha256', implode("\n", array_column($paragraphs, 'text'))),
                'word_count' => $total,
                'matched_words' => count($covered),
                'score' => round(count($covered) / $total * 100, 2),
                'warnings' => $context->warnings,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * @return list<array{type: string, title: string, url: ?string, meta: array<string, mixed>, spans: list<array{from: int, to: int}>, words: int}>
     */
    private function collectMatches(CheckContext $context): array
    {
        $matcher = SimilarityMatcher::fromConfig();
        $minimumWords = (int) config('similarity.min_source_words');
        $found = [];

        foreach ($this->sources->all() as $source) {
            if (! $source->isEnabled()) {
                continue;
            }

            if (! $source->isConfigured()) {
                $context->warn($source->notConfiguredMessage());

                continue;
            }

            foreach ($source->candidates($context) as $candidate) {
                $spans = $matcher->spans($context->index, $candidate->text);
                $words = array_sum(array_map(fn (array $span) => $span['to'] - $span['from'] + 1, $spans));

                if ($words < $minimumWords) {
                    continue;
                }

                // Only what the report needs, not the candidate's (potentially huge) text.
                $found[] = [
                    'type' => $candidate->type,
                    'title' => $candidate->title,
                    'url' => $candidate->url,
                    'meta' => $candidate->meta,
                    'spans' => $spans,
                    'words' => $words,
                ];
            }
        }

        return $found;
    }

    private function fail(SimilarityCheck $check, string $message): void
    {
        $check->update([
            'status' => SimilarityCheck::STATUS_FAILED,
            'error' => $message,
            'completed_at' => now(),
        ]);
    }
}
