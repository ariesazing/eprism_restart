<?php

namespace App\Models;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\SubmissionTemplates\SubmissionTemplate;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResearchSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'researcher_id',
        'reference_code',
        'title',
        'research_type',
        'classification',
        'organizational_unit',
        'organizational_unit_type',
        'school_id',
        'status',
        'editor_engine',
        'admin_notes',
        'approved_at',
        'approved_by',
        'reviewed_at',
        'proposal_approved_at',
        'submitted_at',
        'manuscript',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'editor_engine' => EditorEngine::class,
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'proposal_approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'manuscript' => 'array',
        ];
    }

    public function researcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'researcher_id')->withTrashed();
    }

    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'research_submission_reviewer', 'research_submission_id', 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ResearchDocument::class);
    }

    public function proponents(): HasMany
    {
        return $this->hasMany(ResearchProponent::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where(function ($query) {
            $query->whereNull('manuscript_version_id')->orWhere('manuscript_version_id', function ($version) {
                $version->select('id')->from('manuscript_versions')
                    ->whereColumn('manuscript_versions.research_submission_id', 'reviews.research_submission_id')
                    ->whereNotNull('research_snapshot_id')->orderByDesc('id')->limit(1);
            });
        });
    }

    public function allReviews(): HasMany
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
        return $this->relationLoaded('snapshots')
            ? $this->snapshots->sortByDesc('version')->first()
            : $this->snapshots()->orderByDesc('version')->first();
    }

    public function rapmDocuments(): HasMany
    {
        return $this->hasMany(RapmDocument::class);
    }

    public function similarityChecks(): HasMany
    {
        return $this->hasMany(SimilarityCheck::class);
    }

    /**
     * Who may run the grammar and similarity checks on this submission's text: its own
     * researcher, any admin, and a reviewer assigned to it once it's out of draft (the same
     * gate a reviewer needs to open it at all).
     */
    public function canRunTextChecks(User $user): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isReviewer() => $this->status !== SubmissionStatus::DRAFT && $this->reviewers()->whereKey($user->id)->exists(),
            default => $this->researcher_id === $user->id,
        };
    }

    /**
     * reviewer_id => 1-based display number, ordered by assignment order (the
     * research_submission_reviewer pivot's own auto-increment id) — used to anonymize
     * reviewers as "Reviewer 1"/"Reviewer 2" wherever their feedback is shown to the
     * researcher (Review Summary document, admin_notes), so the same reviewer always gets
     * the same number in both places. A reviewer detached from the submission after their
     * review already fed into admin_notes loses their stable number on next computation —
     * an accepted edge case of deriving numbering from the current pivot rows rather than a
     * separate history table.
     *
     * @return array<int, int>
     */
    public function reviewerNumbers(): array
    {
        return $this->reviewers()
            ->orderBy('research_submission_reviewer.id')
            ->pluck('users.id')
            ->values()
            ->flip()
            ->map(fn (int $index) => $index + 1)
            ->all();
    }

    public function latestRapmDocument(string $kind): ?RapmDocument
    {
        return $this->rapmDocuments()->where('kind', $kind)->orderByDesc('version')->first();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class);
    }

    public function discussionMessages(): HasMany
    {
        return $this->hasMany(SubmissionDiscussionMessage::class);
    }

    public function readinessAssessment(): HasOne
    {
        return $this->hasOne(SubmissionReadinessAssessment::class);
    }

    public function template(): SubmissionTemplate
    {
        return SubmissionTemplateRegistry::for($this->research_type, $this->classification);
    }

    public function isLocked(): bool
    {
        if (in_array($this->manuscript['state'] ?? null, ['waiting_for_save', 'processing'], true)) {
            return true;
        }

        return ! in_array($this->status, [SubmissionStatus::DRAFT, SubmissionStatus::REVISIONS_REQUIRED], true);
    }

    public function usesOnlyOffice(): bool
    {
        return in_array($this->editor_engine, [EditorEngine::ONLYOFFICE, EditorEngine::ONLYOFFICE_MANUSCRIPT], true);
    }

    public function usesManuscript(): bool
    {
        return $this->editor_engine === EditorEngine::ONLYOFFICE_MANUSCRIPT;
    }

    public function manuscriptVersions(): HasMany
    {
        return $this->hasMany(ManuscriptVersion::class);
    }

    public function currentManuscriptVersion(): ?ManuscriptVersion
    {
        return $this->manuscriptVersions()->whereNotNull('research_snapshot_id')->latest('id')->first();
    }

    /**
     * The version actually approved *for that specific classification stage* — needed because
     * a proposal's classification is overwritten to 'completed' in place on promotion
     * (SubmissionDecisionService), so "the current manuscript" no longer reliably means "the
     * approved proposal" once the same submission has moved on to its completed-research phase.
     * Used by submission-card.blade.php to link each repository card to the document it
     * actually represents, not whatever happens to be current right now.
     */
    public function approvedManuscriptVersion(string $classification): ?ManuscriptVersion
    {
        return $this->manuscriptVersions()
            ->where('metadata->classification', $classification)
            ->whereNotNull('approved_at')
            ->latest('approved_at')
            ->first();
    }

    /**
     * Legacy-engine (canvas_editor/onlyoffice) equivalent of approvedManuscriptVersion() — those
     * engines have no per-classification version record at all, just a flat, sequential
     * research_snapshots history, so this infers the right one as the latest snapshot that
     * already existed at the moment this stage was actually approved, rather than always
     * returning today's latest (which suffers the exact same "promoted in place" staleness).
     */
    public function snapshotApprovedAsOf(?\DateTimeInterface $at): ?ResearchSnapshot
    {
        if ($at === null) {
            return null;
        }

        return $this->snapshots()->where('generated_at', '<=', $at)->orderByDesc('generated_at')->first();
    }
}
