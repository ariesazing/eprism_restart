<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-controlled gate on new proposal/completed-research submissions. is_open is a
 * manual kill switch that always wins when off; opens_at/closes_at are an optional
 * date range layered on top for admins who'd rather schedule a call for submissions
 * than flip the switch by hand on the day.
 */
class SubmissionWindow extends Model
{
    protected $fillable = [
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
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function forClassification(string $classification): self
    {
        return self::query()->firstOrCreate(['classification' => $classification], ['is_open' => true]);
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

    public static function isOpenFor(string $classification): bool
    {
        return self::forClassification($classification)->isCurrentlyOpen();
    }
}
