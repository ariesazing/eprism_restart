<?php

namespace App\Events;

use App\Models\ResearchSubmission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a submission's state changes in a way that a dashboard, assignment list,
 * or admin submissions table needs to reflect live — assigning/unassigning a reviewer,
 * submitting/resubmitting, or a reviewer decision (see AdminSubmissionController::
 * assignReviewer(), ResearchSubmissionController::submit()/resubmit(), and
 * SubmissionDecisionService::evaluate()). Deliberately generic (a $type label, not a
 * payload) — every listener just re-fetches its own already-correctly-scoped region (see
 * resources/js/live-refresh.js) rather than trying to patch specific fields from the event.
 */
class SubmissionActivity implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $reviewerIds  Currently (or newly) assigned reviewer ids —
     *         passed explicitly rather than read off $submission->reviewers() at broadcast
     *         time, since queued broadcasting means that could run after the pivot has
     *         already changed again.
     */
    public function __construct(
        public ResearchSubmission $submission,
        public string $type,
        public array $reviewerIds = [],
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("submission.{$this->submission->id}"),
            new PrivateChannel('admin.dashboard'),
        ];

        if ($this->submission->researcher_id !== null) {
            $channels[] = new PrivateChannel("App.Models.User.{$this->submission->researcher_id}");
        }

        foreach ($this->reviewerIds as $reviewerId) {
            $channels[] = new PrivateChannel("App.Models.User.{$reviewerId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'submission-activity';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'submission_id' => $this->submission->id,
        ];
    }
}
