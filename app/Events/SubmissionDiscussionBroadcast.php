<?php

namespace App\Events;

use App\Models\SubmissionDiscussionMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubmissionDiscussionBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SubmissionDiscussionMessage $message,
        public string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("submission.{$this->message->research_submission_id}.discussion"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'discussion-message';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'message' => $this->message,
        ];
    }
}
