<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_submission_id',
        'reviewer_id',
        'criteria_scores',
        'comments',
        'recommendation',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'criteria_scores' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function documentComments(): HasMany
    {
        return $this->hasMany(DocumentComment::class);
    }
}