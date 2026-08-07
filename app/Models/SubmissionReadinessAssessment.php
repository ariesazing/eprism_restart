<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionReadinessAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_submission_id',
        'content_hash',
        'completeness_percent',
        'sections_done',
        'sections_total',
        'attachments_done',
        'attachments_total',
        'grammar_percent',
        'word_count',
        'issue_count',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'grammar_percent' => 'decimal:2',
            'checked_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }
}
