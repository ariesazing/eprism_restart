<?php

namespace App\Services;

use App\Evaluation\ResearchEvaluationRubric;
use Illuminate\Support\Collection;

class EvaluationOutcome
{
    public static function average(Collection $reviews): float
    {
        return (float) $reviews->avg(fn ($review) => $review->percentageScore());
    }

    public static function result(Collection $reviews): string
    {
        if (self::average($reviews) < ResearchEvaluationRubric::PASSING_SCORE) {
            return 'rejected';
        }

        return $reviews->contains(fn ($review) => in_array($review->recommendation, ['revision', 'minor_revision', 'major_revision'], true))
            ? 'revisions_required' : 'approved';
    }
}
