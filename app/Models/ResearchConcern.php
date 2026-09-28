<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchConcern extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_submission_id',
        'user_id',
        'subject',
        'category',
        'message',
        'status',
        'admin_response',
        'responded_by',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            default => 'Open',
        };
    }
}
