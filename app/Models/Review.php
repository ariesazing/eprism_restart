<?php

namespace App\Models;

use App\Evaluation\ResearchEvaluationRubric;
use App\Evaluation\RubricTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_submission_id',
        'reviewer_id',
        'rubric_key',
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
        return $this->belongsTo(User::class, 'reviewer_id')->withTrashed();
    }

    public function totalScore(): int
    {
        return ResearchEvaluationRubric::totalScore($this->criteria_scores ?? []);
    }

    /**
     * The rubric this review's own criteria_scores were scored against — via the stored
     * rubric_key, never the submission's current research_type/classification (see this
     * column's own migration comment on why those can drift apart after promotion). Null for
     * a review with no rubric_key on record (predates this column, or was never scored
     * through the normal reviewer flow).
     */
    public function rubric(): ?RubricTemplate
    {
        if ($this->rubric_key === null) {
            return null;
        }

        try {
            return ResearchEvaluationRubric::forKey($this->rubric_key);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>> ResearchEvaluationRubric::breakdown()'s shape, or
     *                                    empty when this review has no known rubric
     */
    public function breakdown(): array
    {
        $rubric = $this->rubric();

        return $rubric ? ResearchEvaluationRubric::breakdown($rubric, $this->criteria_scores ?? []) : [];
    }
}
