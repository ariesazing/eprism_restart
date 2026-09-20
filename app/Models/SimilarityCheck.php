<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of the similarity checker against a submission's chapters — see
 * App\Similarity\SimilarityCheckRunner. A check is a frozen record: it keeps the exact
 * paragraphs it examined (`document`) alongside the sources it found, so its report stays
 * coherent no matter how the chapters change afterwards.
 */
class SimilarityCheck extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    // A *running* check older than this is presumed dead (a worker that crashed or was restarted
    // mid-run never gets to record a failure), so it stops blocking new checks.
    private const STALE_RUNNING_AFTER_MINUTES = 20;

    // A check that's still merely *queued* this long was never picked up at all — almost always
    // because no queue worker is running. Much shorter than the running limit: nothing is
    // legitimately "in progress" here, and waiting 20 minutes on an empty spinner is exactly how
    // that problem stays invisible.
    private const STALE_QUEUED_AFTER_MINUTES = 5;

    /** Seconds a check may sit queued before the progress page starts warning about it. */
    public const QUEUED_WARNING_AFTER_SECONDS = 20;

    protected $fillable = [
        'research_submission_id',
        'requested_by',
        'status',
        'text_hash',
        'word_count',
        'matched_words',
        'score',
        'document',
        'warnings',
        'error',
        'started_at',
        'completed_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'document' => 'encrypted:array',
            'warnings' => 'array',
            'score' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SimilarityMatch::class)->orderBy('rank');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Records that the researcher now knows this check finished (they opened its report, or
     * dismissed the popup announcing it) so it isn't announced again. A check still in progress
     * has nothing to acknowledge yet.
     */
    public function acknowledge(): void
    {
        if ($this->acknowledged_at === null && ! $this->isActive()) {
            $this->update(['acknowledged_at' => now()]);
        }
    }

    public function isStale(): bool
    {
        return match ($this->status) {
            self::STATUS_QUEUED => $this->created_at->lt(now()->subMinutes(self::STALE_QUEUED_AFTER_MINUTES)),
            self::STATUS_RUNNING => $this->created_at->lt(now()->subMinutes(self::STALE_RUNNING_AFTER_MINUTES)),
            default => false,
        };
    }

    /**
     * How long this check has been waiting for a worker to pick it up — null once it has
     * started (or finished).
     */
    public function queuedForSeconds(): ?int
    {
        return $this->status === self::STATUS_QUEUED ? (int) $this->created_at->diffInSeconds(now(), true) : null;
    }

    /**
     * Turns a presumed-dead check into a visible failure instead of leaving it spinning
     * forever — returns whether it did.
     */
    public function expireIfStale(): bool
    {
        if (! $this->isStale()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_FAILED,
            'error' => $this->status === self::STATUS_QUEUED
                ? 'The check never started — the background worker that runs it may not be running. Please try again, and tell an administrator if this keeps happening.'
                : 'The check took too long and was stopped. Please run it again.',
            'completed_at' => now(),
        ]);

        return true;
    }

    /**
     * green / amber / orange / rose — the same four-band scale Turnitin's similarity index
     * uses, mapped to fixed class names in the views (never built by string concatenation, so
     * Tailwind's scanner still sees them).
     */
    public function band(): string
    {
        return match (true) {
            $this->score === null => 'slate',
            $this->score < 25 => 'emerald',
            $this->score < 50 => 'amber',
            $this->score < 75 => 'orange',
            default => 'rose',
        };
    }
}
