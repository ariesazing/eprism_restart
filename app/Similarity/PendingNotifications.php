<?php

namespace App\Similarity;

use App\Models\SimilarityCheck;
use App\Models\User;

/**
 * What the "your similarity check is done" popup needs to know about one researcher: how many of
 * their checks are still running (so the page knows whether to keep polling) and which finished
 * checks they haven't been told about yet. A check takes minutes, so the researcher has usually
 * left the page that started it — this is how they find out it's ready wherever they've gone.
 *
 * Used both to render the popup into a page (App\View\Components\SimilarityNotifier) and to answer
 * that page's polling (SimilarityNotificationController), so the two can never disagree.
 */
final class PendingNotifications
{
    // A finished check nobody was told about for longer than this is stale news, not a
    // notification — it's left alone rather than popping up days later.
    private const FRESH_FOR_HOURS = 24;

    /**
     * @param  int|null  $exceptCheckId  a check whose own page the researcher is on right now — that
     *                                   page already shows its progress and result, so announcing it
     *                                   on top would be redundant
     * @return array{active: int, finished: list<array{id: int, status: string, submission: string, score: ?string, band: string, error: ?string, url: string}>}
     */
    public function for(User $user, ?int $exceptCheckId = null): array
    {
        $checks = SimilarityCheck::query()
            ->with('submission:id,title')
            ->where('requested_by', $user->id)
            ->where(function ($query) {
                $query->whereIn('status', [SimilarityCheck::STATUS_QUEUED, SimilarityCheck::STATUS_RUNNING])
                    ->orWhere(function ($finished) {
                        $finished->whereIn('status', [SimilarityCheck::STATUS_COMPLETED, SimilarityCheck::STATUS_FAILED])
                            ->whereNull('acknowledged_at')
                            ->where('completed_at', '>=', now()->subHours(self::FRESH_FOR_HOURS));
                    });
            })
            ->orderBy('id')
            ->get();

        $active = 0;
        $finished = [];

        foreach ($checks as $check) {
            // A check whose worker never picked it up (or died) turns into a visible failure here
            // instead of counting as "running" forever and polling for as long as the tab is open.
            $check->expireIfStale();

            if ($check->isActive()) {
                $active++;

                continue;
            }

            if ($check->id === $exceptCheckId) {
                continue;
            }

            $finished[] = [
                'id' => $check->id,
                'status' => $check->status,
                'submission' => $check->submission?->title ?? 'your research',
                'score' => $this->scoreLabel($check),
                'band' => $check->band(),
                'error' => $check->isFailed() ? $check->error : null,
                'url' => route('submissions.similarity.show', [$check->research_submission_id, $check]),
            ];
        }

        return ['active' => $active, 'finished' => $finished];
    }

    private function scoreLabel(SimilarityCheck $check): ?string
    {
        if (! $check->isCompleted() || $check->score === null) {
            return null;
        }

        return $check->score > 0 && $check->score < 1 ? '<1' : (string) round($check->score);
    }
}
