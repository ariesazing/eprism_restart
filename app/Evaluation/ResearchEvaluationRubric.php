<?php

namespace App\Evaluation;

/**
 * The DepEd Schools Division of Santiago City's "Pro Forma for Regional Research Awards
 * and Journal Publication (Manuscript)" screening rubric — 5 weighted criteria totaling
 * 100 points, each scored on a fixed 3-tier scale (Excellent/Good/Fair) rather than a
 * free-form number, since the pro forma defines an exact point value per tier per
 * criterion. A paper needs a total of at least PASSING_SCORE to be accepted for
 * publication and awards, per the pro forma's own text.
 */
class ResearchEvaluationRubric
{
    public const PASSING_SCORE = 85;

    public const MAX_SCORE = 100;

    public const CRITERIA = [
        'clear_focus' => [
            'label' => 'Clear Focus',
            'weight' => 20,
            'tiers' => [
                'excellent' => ['points' => 20, 'description' => 'Met all the 4 indicators'],
                'good' => ['points' => 17, 'description' => 'Attained only 2-3 indicators'],
                'fair' => ['points' => 14, 'description' => 'Achieved only 1 indicator'],
            ],
        ],
        'research' => [
            'label' => 'Research',
            'weight' => 25,
            'tiers' => [
                'excellent' => ['points' => 25, 'description' => 'Met all the 5 indicators'],
                'good' => ['points' => 22, 'description' => 'Attained only 3-4 indicators'],
                'fair' => ['points' => 20, 'description' => 'Achieved only 1-2 indicator/s'],
            ],
        ],
        'reasoning_organization' => [
            'label' => 'Reasoning and Organization',
            'weight' => 25,
            'tiers' => [
                'excellent' => ['points' => 25, 'description' => 'Met all the 6 indicators'],
                'good' => ['points' => 21, 'description' => 'Attained only 3-5 indicators'],
                'fair' => ['points' => 18, 'description' => 'Achieved only 1-2 indicators'],
            ],
        ],
        'documentation' => [
            'label' => 'Documentation',
            'weight' => 15,
            'tiers' => [
                'excellent' => ['points' => 15, 'description' => 'Met all the 4 indicators'],
                'good' => ['points' => 12, 'description' => 'Attained only 2-3 indicators'],
                'fair' => ['points' => 10, 'description' => 'Achieved only 1 indicator'],
            ],
        ],
        'writing_mechanics' => [
            'label' => 'Writing and Mechanics',
            'weight' => 15,
            'tiers' => [
                'excellent' => ['points' => 15, 'description' => 'Met all 5 indicators'],
                'good' => ['points' => 12, 'description' => 'Attained only 3-4 indicators'],
                'fair' => ['points' => 10, 'description' => 'Achieved only 1-2 indicators'],
            ],
        ],
    ];

    public static function criteriaKeys(): array
    {
        return array_keys(self::CRITERIA);
    }

    public static function tierKeysFor(string $criterion): array
    {
        return array_keys(self::CRITERIA[$criterion]['tiers'] ?? []);
    }

    public static function pointsFor(string $criterion, string $tier): ?int
    {
        return self::CRITERIA[$criterion]['tiers'][$tier]['points'] ?? null;
    }

    /**
     * @param  array<string, string>  $tierSelections  criterion key => tier key
     * @return array<string, array{tier: string, points: int}>
     */
    public static function scoreFromTiers(array $tierSelections): array
    {
        $scored = [];

        foreach ($tierSelections as $criterion => $tier) {
            $points = self::pointsFor($criterion, $tier);

            if ($points !== null) {
                $scored[$criterion] = ['tier' => $tier, 'points' => $points];
            }
        }

        return $scored;
    }

    /**
     * @param  array<string, array{tier: string, points: int}>  $scoredCriteria
     */
    public static function totalScore(array $scoredCriteria): int
    {
        return array_sum(array_column($scoredCriteria, 'points'));
    }

    /**
     * @param  array<string, array{tier: string, points: int}>  $scoredCriteria
     */
    public static function passes(array $scoredCriteria): bool
    {
        return self::totalScore($scoredCriteria) >= self::PASSING_SCORE;
    }
}
