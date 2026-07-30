<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\SubmissionTemplates\SubmissionTemplate;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'researcher_id',
        'title',
        'research_type',
        'classification',
        'organizational_unit',
        'organizational_unit_type',
        'school_id',
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

    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'research_submission_reviewer', 'research_submission_id', 'reviewer_id');
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

    public function sections(): HasMany
    {
        return $this->hasMany(SubmissionSection::class)->orderBy('sort_order');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ResearchSnapshot::class);
    }

    public function latestSnapshot(): ?ResearchSnapshot
    {
        return $this->snapshots()->orderByDesc('version')->first();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class);
    }

    public function template(): SubmissionTemplate
    {
        return SubmissionTemplateRegistry::for($this->research_type, $this->classification);
    }

    public function isLocked(): bool
    {
        return ! in_array($this->status, [SubmissionStatus::DRAFT, SubmissionStatus::REVISIONS_REQUIRED], true);
    }
}