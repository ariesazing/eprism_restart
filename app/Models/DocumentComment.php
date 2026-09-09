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
        'research_snapshot_id',
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

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ResearchSnapshot::class, 'research_snapshot_id');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by')->withTrashed();
    }

    /**
     * A researcher should only ever see a comment left against a review that was actually
     * submitted (finalized), not a reviewer's still-in-progress notes. review_id can also be
     * null: SubmissionDecisionService::evaluate() clears a submission's Review rows once a
     * proposal is unanimously approved and promoted to "completed" (review_id is
     * nullOnDelete(), not cascadeOnDelete(), specifically so the comments themselves survive
     * that — see the migration that changed it). That's the *only* place a Review ever gets
     * deleted, and it only runs once every relevant review was already submitted with
     * recommendation === 'approve', so an orphaned comment (review_id null) is exactly as
     * finalized as one still pointing at a submitted review — it should stay visible, not
     * disappear along with the row that used to prove it.
     */
    public function scopeVisibleToResearcher(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('review_id')
                ->orWhereHas('review', fn ($review) => $review->whereNotNull('submitted_at'));
        });
    }
}
