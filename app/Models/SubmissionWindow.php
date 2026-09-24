<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-controlled gate on new proposal/completed-research submissions, one per
 * (research_type, classification) pair — basic and action research each open and
 * close independently, as do proposal and completed research within each. is_open is
 * a manual kill switch that always wins when off; opens_at/closes_at are an optional
 * date range layered on top for admins who'd rather schedule a call for submissions
 * than flip the switch by hand on the day.
 */
class SubmissionWindow extends Model
{
    protected $fillable = [
        'research_type',
        'classification',
        'is_open',
        'opens_at',
        'closes_at',
        'memorandum_path',
        'memorandum_original_name',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'last_notified_open' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public static function forWindow(string $researchType, string $classification): self
    {
        return self::query()->firstOrCreate(
            ['research_type' => $researchType, 'classification' => $classification],
            ['is_open' => true]
        );
    }

    public function isCurrentlyOpen(): bool
    {
        if (! $this->is_open) {
            return false;
        }

        $now = now();

        if ($this->opens_at && $now->lt($this->opens_at)) {
            return false;
        }

        if ($this->closes_at && $now->gt($this->closes_at)) {
            return false;
        }

        return true;
    }

    public static function isOpenFor(string $researchType, string $classification): bool
    {
        return self::forWindow($researchType, $classification)->isCurrentlyOpen();
    }
}
