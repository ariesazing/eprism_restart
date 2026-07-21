<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'researcher_id',
        'assigned_reviewer_id',
        'title',
        'research_type',
        'classification',
        'abstract',
        'keywords',
        'authors',
        'course',
        'status',
        'admin_notes',
        'approved_at',
        'approved_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function researcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'researcher_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ResearchDocument::class);
    }

    public function proponents(): HasMany
    {
        return $this->hasMany(ResearchProponent::class)->orderBy('sort_order');
    }

    public function leadProponent(): ?ResearchProponent
    {
        return $this->proponents->firstWhere('is_lead', true) ?? $this->proponents->first();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}