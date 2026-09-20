<?php

namespace App\Jobs;

use App\Models\SimilarityCheck;
use App\Similarity\SimilarityCheckRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Off-request half of a similarity check — the external lookups alone can take minutes (one
 * rate-limited API call per phrase, then downloading candidate pages), far too long for a web
 * request. See SimilarityCheckRunner; the report page polls the check's status until this
 * finishes.
 */
class RunSimilarityCheck implements ShouldQueue
{
    use Queueable;

    // Never retried: a rerun would spend the same external API quota again, and the
    // researcher can simply start a new check.
    public int $tries = 1;

    // Above similarity.time_budget_seconds (what actually bounds the lookups, with room for the
    // last request in flight) and below the queue's retry_after, so a slow run is never picked
    // up a second time while it's still going.
    public int $timeout = 720;

    public function __construct(public int $checkId) {}

    public function handle(SimilarityCheckRunner $runner): void
    {
        $check = SimilarityCheck::find($this->checkId);

        if ($check === null || ! $check->isActive()) {
            return;
        }

        $runner->run($check);
    }

    /**
     * Only reached for what SimilarityCheckRunner::run() couldn't catch itself (the worker
     * timing out or being killed) — anything else is already recorded on the check.
     */
    public function failed(?Throwable $exception): void
    {
        report($exception);

        SimilarityCheck::query()
            ->whereKey($this->checkId)
            ->whereIn('status', [SimilarityCheck::STATUS_QUEUED, SimilarityCheck::STATUS_RUNNING])
            ->update([
                'status' => SimilarityCheck::STATUS_FAILED,
                'error' => 'The similarity check was interrupted. Please run it again.',
                'completed_at' => now(),
            ]);
    }
}
