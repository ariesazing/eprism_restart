<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'research_submission_id',
        'review_id',
        'author_id',
        'last_edited_by',
        'page_number',
        'quote_text',
        'anchor',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'anchor' => 'array',
            'page_number' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function scopeVisibleToResearcher(Builder $query): Builder
    {
        return $query->whereHas('review', fn ($q) => $q->whereNotNull('approved_at'));
    }
}
