<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'causer_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
    ];

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Every action key logged app-wide is a dotted "{area}.{outcome}" string (e.g.
     * "submission.approved", "submission-window.updated") — no schema column needed to
     * split it into a general Activity area and a specific Status outcome for display.
     */
    public function activityLabel(): string
    {
        return str($this->action)->before('.')->replace('-', ' ')->headline()->toString();
    }

    public function statusLabel(): string
    {
        return str($this->action)->after('.')->replace('_', ' ')->headline()->toString();
    }
}
