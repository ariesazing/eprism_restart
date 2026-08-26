<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapmDocument extends Model
{
    use HasFactory;

    public const KIND_REVIEW_SUMMARY = 'review_summary';

    public const KIND_ROUTING_SLIP = 'routing_slip';

    public const OUTCOME_APPROVED = 'approved';

    public const OUTCOME_REVISIONS_REQUIRED = 'revisions_required';

    protected $fillable = [
        'research_submission_id',
        'kind',
        'version',
        'path',
        'fingerprint',
        'outcome',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
