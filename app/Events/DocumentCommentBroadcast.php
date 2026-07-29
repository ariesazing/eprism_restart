<?php

namespace App\Events;

use App\Models\DocumentComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentCommentBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DocumentComment $comment,
        public string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("submission.{$this->comment->review->research_submission_id}.document.{$this->comment->research_document_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document-comment';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'comment' => $this->comment,
        ];
    }
}
