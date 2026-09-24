<?php

namespace App\Evaluation;

/**
 * The DepEd Schools Division of Santiago City's four official scoring templates for research
 * appraisal — one per (research_type, classification) combination, matching
 * App\SubmissionTemplates\SubmissionTemplateRegistry's own four-way split exactly:
 *   - RO-PPRD-F004 Scoring Template for Appraising Basic/Applied Research Proposal
 *   - RO-PPRD-F005 Scoring Template for Appraising Action Research Proposal
 *   - RO-PPRD-F006 Scoring Template for Complete Basic Research
 *   - RO-PPRD-F007 Scoring Template for Appraising Completed Action Research
 * (source .docx forms under rubrics_templates/ — gitignored, not part of the repo; kept locally
 * for reference only. Every criterion/label/point value below was transcribed from those four
 * files, not invented.)
 *
 * Every template totals 100 points, but unlike this class's previous incarnation there are no
 * excellent/good/fair tiers anywhere in the source forms — each line item is a blank numeric box
 * on the printed page, so a reviewer types a raw score (0 up to that item's own max) rather than
 * picking a tier. A criterion with sub-items (e.g. "Research Methods (40 points)" split into
 * three sub-items totaling 40) is only ever scored at the leaf: the parent is always the sum of
 * its children, computed on demand, never entered or stored directly — see RubricItem::isLeaf().
 *
 * The two "completed research" templates additionally group their items into two named,
 * separately-subtotaled sections (Manuscript / Utilization and Dissemination, 50 points each);
 * the two proposal templates are a single unlabeled section. RubricSection::$label being null is
 * what a renderer uses to tell the two shapes apart.
 *
 * Approval requires at least 70 points under the application review policy.
 */
class ResearchEvaluationRubric
{
    public const MAX_SCORE = 100;

    public const PASSING_SCORE = 70;

    /**
     * @return list<RubricTemplate>
     */
    public static function all(): array
    {
        return [
            self::basicProposal(),
            self::actionProposal(),
            self::basicCompleted(),
            self::actionCompleted(),
        ];
    }

    public static function key(string $researchType, string $classification): string
    {
        return "{$researchType}_{$classification}";
    }

    public static function for(string $researchType, string $classification): RubricTemplate
    {
        return self::forKey(self::key($researchType, $classification));
    }

    public static function forKey(string $key): RubricTemplate
    {
        foreach (self::all() as $template) {
            if ($template->key === $key) {
                return $template;
            }
        }

        throw new \InvalidArgumentException("No scoring rubric registered for [{$key}].");
    }

    /**
     * Laravel validation rules for one submitted score set: every leaf key required, an integer
     * from 0 to that leaf's own max. A criterion this rubric doesn't define simply isn't in the
     * returned rules (and so isn't required or read back — see scoresFromInputs()).
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(RubricTemplate $template): array
    {
        $rules = [];

        foreach ($template->leaves() as $item) {
            $rules[$item->key] = ['required', 'integer', 'min:0', "max:{$item->max}"];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $inputs  already validated by rules() above
     * @return array<string, int> leaf key => score — this, not $inputs raw, is what gets stored
     *                            as Review::$criteria_scores
     */
    public static function scoresFromInputs(RubricTemplate $template, array $inputs): array
    {
        $scores = [];

        foreach ($template->leaves() as $item) {
            if (array_key_exists($item->key, $inputs)) {
                $scores[$item->key] = (int) $inputs[$item->key];
            }
        }

        return $scores;
    }

    /**
     * @param  array<string, mixed>  $scores  leaf key => points, as stored on Review::$criteria_scores
     */
    public static function totalScore(array $scores): int
    {
        return array_sum(array_map('intval', $scores));
    }

    /**
     * The full scored breakdown, for display: every section and item with a score attached — a
     * parent item's score is always computed as its children's sum, never read from $scores
     * directly even if something happens to be stored under the parent's own key.
     *
     * @param  array<string, mixed>  $scores
     * @return list<array{label: ?string, max: int, total: int, items: list<array<string, mixed>>}>
     */
    public static function breakdown(RubricTemplate $template, array $scores): array
    {
        $renderItem = function (RubricItem $item) use (&$renderItem, $scores): array {
            $children = array_map($renderItem, $item->children);
            $score = $item->isLeaf() ? (int) ($scores[$item->key] ?? 0) : array_sum(array_column($children, 'score'));

            return [
                'key' => $item->isLeaf() ? $item->key : null,
                'code' => $item->code,
                'label' => $item->label,
                'max' => $item->max,
                'score' => $score,
                'leaf' => $item->isLeaf(),
                'children' => $children,
            ];
        };

        return array_map(fn (RubricSection $section) => [
            'label' => $section->label,
            'max' => $section->max(),
            'total' => array_sum(array_map(fn (RubricItem $item) => $renderItem($item)['score'], $section->items)),
            'items' => array_map($renderItem, $section->items),
        ], $template->sections);
    }

    private static function basicProposal(): RubricTemplate
    {
        return new RubricTemplate(
            key: 'basic_proposal',
            researchType: 'basic',
            classification: 'proposal',
            label: 'Scoring Template for Appraising Basic/Applied Research Proposal',
            sections: [
                new RubricSection(null, [
                    new RubricItem('rationale', 'A', 'Rationale of the Research', 10),
                    new RubricItem('research_questions', 'B', 'Research Questions', 20),
                    new RubricItem('literature_citation', 'C', 'Use of Related Literature and Proper Citation', 10),
                    new RubricItem('research_methods', 'D', 'Research Methods', 40, children: [
                        new RubricItem('participants_sources', 'D.1', 'Participants and/or Other Sources of Data and Information', 10),
                        new RubricItem('data_gathering_methods', 'D.2', 'Data Gathering Method(s) and Research Instruments', 20),
                        new RubricItem('data_analysis_plan', 'D.3', 'Data Analysis Plan', 10),
                    ]),
                    new RubricItem('work_plan_timelines', 'E', 'Work Plan and Timelines', 10),
                    new RubricItem('cost_estimates', 'F', 'Cost Estimates', 10),
                ]),
            ],
        );
    }

    private static function actionProposal(): RubricTemplate
    {
        return new RubricTemplate(
            key: 'action_proposal',
            researchType: 'action',
            classification: 'proposal',
            label: 'Scoring Template for Appraising Action Research Proposal',
            sections: [
                new RubricSection(null, [
                    new RubricItem('rationale', 'A', 'Rationale of the Action Research', 30, children: [
                        new RubricItem('context', 'A.1', 'Context', 15),
                        new RubricItem('proposed_intervention', 'A.2', 'Proposed Intervention, Innovation, Strategy', 15),
                    ]),
                    new RubricItem('action_research_questions', 'B', 'Action Research Questions', 30),
                    new RubricItem('action_research_methods', 'C', 'Action Research Methods', 30, children: [
                        new RubricItem('participants_sources', 'C.1', 'Participants and/or Other Sources of Data and Information', 10),
                        new RubricItem('data_gathering_methods', 'C.2', 'Data Gathering Method(s) and Research Instruments', 10),
                        new RubricItem('data_analysis_plan', 'C.3', 'Data Analysis Plan', 10),
                    ]),
                    new RubricItem('work_plan_timelines', 'D', 'Work Plan and Timelines', 5),
                    new RubricItem('cost_estimates', 'E', 'Cost Estimates', 5),
                ]),
            ],
        );
    }

    private static function basicCompleted(): RubricTemplate
    {
        return new RubricTemplate(
            key: 'basic_completed',
            researchType: 'basic',
            classification: 'completed',
            label: 'Scoring Template for Appraising Completed Basic/Applied Research',
            sections: [
                new RubricSection('Manuscript', [
                    new RubricItem('abstract', 'A', 'Abstract', 3),
                    new RubricItem('introduction', 'B', 'Introduction of the Research', 3),
                    new RubricItem('literature_review', 'C', 'Literature Review', 5),
                    new RubricItem('research_questions', 'D', 'Research Questions', 3),
                    new RubricItem('scope_limitations', 'E', 'Scope and Limitations', 3),
                    new RubricItem('research_methodology', 'F', 'Research Methodology', 9, children: [
                        new RubricItem('sampling', '1', 'Sampling', 3),
                        new RubricItem('data_collection', '2', 'Data Collection', 3),
                        new RubricItem('ethical_issues', '3', 'Ethical Issues', 3),
                    ]),
                    // The source form prints "F" again here — a typo (the following rows keep
                    // going G, H, I, J as if this one were "G"). Kept as printed for $code, which
                    // is display-only; $key is this item's own unambiguous storage key regardless.
                    new RubricItem('discussion_results', 'F', 'Discussion of Results and Recommendation', 10),
                    new RubricItem('dissemination_advocacy', 'G', 'Dissemination and Advocacy Plans', 5),
                    new RubricItem('references', 'H', 'References', 3),
                    new RubricItem('financial_report', 'I', 'Financial Report', 3),
                    new RubricItem('appendices', 'J', 'Appendices', 3),
                ]),
                new RubricSection('Utilization and Dissemination', [
                    new RubricItem('utilization_findings', 'A', 'Utilization of Research Findings', 15),
                    new RubricItem('partnership_linkages', 'B', 'Partnership/External Linkages', 10),
                    new RubricItem('dissemination_findings', 'C', 'Dissemination of Research Findings', 15),
                    new RubricItem('capacity_multiple_problems', 'D', 'Capacity to Address Multiple Problems', 10),
                ]),
            ],
        );
    }

    private static function actionCompleted(): RubricTemplate
    {
        return new RubricTemplate(
            key: 'action_completed',
            researchType: 'action',
            classification: 'completed',
            label: 'Scoring Template for Appraising Completed Action Research',
            sections: [
                new RubricSection('Manuscript', [
                    new RubricItem('abstract', 'A', 'Abstract', 5),
                    new RubricItem('context_rationale', 'B', 'Context and Rationale', 5),
                    new RubricItem('innovation_intervention_strategy', 'C', 'Innovation, Intervention, and Strategy', 5),
                    new RubricItem('action_research_questions', 'D', 'Action Research Questions', 5),
                    new RubricItem('action_research_methods', 'E', 'Action Research Methods', 13, children: [
                        new RubricItem('participants_sources', '1', 'Participants and/or Other Sources of Data and Information', 5),
                        new RubricItem('data_gathering_methods', '2', 'Data Gathering Methods', 5),
                        new RubricItem('ethical_issues', '3', 'Ethical Issues', 3),
                    ]),
                    new RubricItem('discussion_reflections', 'F', 'Discussion of Results and Reflections', 5),
                    new RubricItem('action_plan', 'G', 'Action Plan', 3),
                    new RubricItem('references', 'H', 'References', 3),
                    new RubricItem('financial_report', 'I', 'Financial Report', 3),
                    new RubricItem('appendices', 'J', 'Appendices', 3),
                ]),
                new RubricSection('Utilization and Dissemination', [
                    new RubricItem('novelty_intervention', 'A', 'Novelty of the Intervention', 10),
                    new RubricItem('adoption_intervention', 'B', 'Adoption of Research Intervention', 10),
                    new RubricItem('partnership_linkages', 'C', 'Partnership/External Linkages', 10),
                    new RubricItem('dissemination_findings', 'D', 'Dissemination of Research Findings', 15),
                    new RubricItem('capacity_multiple_problems', 'E', 'Capacity to Address Multiple Problems', 5),
                ]),
            ],
        );
    }
}
