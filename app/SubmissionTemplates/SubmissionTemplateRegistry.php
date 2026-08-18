<?php

namespace App\SubmissionTemplates;

class SubmissionTemplateRegistry
{
    private const COMPLETED_ATTACHMENTS = [
        ['key' => 'proposed_innovation_material', 'label' => 'Copy of the Proposed Innovation/Intervention Material'],
        ['key' => 'approved_proposal', 'label' => 'Copy of the Approved Proposal'],
        ['key' => 'documentation', 'label' => 'Copy of the Documentation'],
        ['key' => 'implementation_report', 'label' => 'Copy of the Implementation (Accomplishment Report, Certificate of Implementation)'],
        ['key' => 'dissemination', 'label' => 'Copy of the Dissemination'],
        ['key' => 'adoption', 'label' => 'Copy of the Adoption'],
        ['key' => 'utilization', 'label' => 'Copy of the Utilization'],
        ['key' => 'liquidation', 'label' => 'Copy of the Liquidation'],
    ];

    private const TABLE_COLUMNS = [
        'work_plan' => [
            ['key' => 'activity', 'label' => 'Research Activities'],
            ['key' => 'month1_week1', 'label' => 'Month 1 – 1st Week'],
            ['key' => 'month1_week2', 'label' => 'Month 1 – 2nd Week'],
            ['key' => 'month1_week3', 'label' => 'Month 1 – 3rd Week'],
            ['key' => 'month1_week4', 'label' => 'Month 1 – 4th Week'],
            ['key' => 'month2_week1', 'label' => 'Month 2 – 1st Week'],
            ['key' => 'month2_week2', 'label' => 'Month 2 – 2nd Week'],
            ['key' => 'month2_week3', 'label' => 'Month 2 – 3rd Week'],
            ['key' => 'month2_week4', 'label' => 'Month 2 – 4th Week'],
            ['key' => 'month3_week1', 'label' => 'Month 3 – 1st Week'],
            ['key' => 'month3_week2', 'label' => 'Month 3 – 2nd Week'],
            ['key' => 'month3_week3', 'label' => 'Month 3 – 3rd Week'],
            ['key' => 'month3_week4', 'label' => 'Month 3 – 4th Week'],
        ],
        'cost_estimate' => [
            ['key' => 'activity', 'label' => 'Activity'],
            ['key' => 'item_description', 'label' => 'Item Description/Particular/Needs'],
            ['key' => 'cost', 'label' => 'Cost'],
            ['key' => 'total_amount', 'label' => 'Total Amount'],
            ['key' => 'source_of_fund', 'label' => 'Source of Fund'],
        ],
        'dissemination_plan' => [
            ['key' => 'strategy', 'label' => 'Strategies'],
            ['key' => 'activity', 'label' => 'Activities for Utilization and Dissemination'],
            ['key' => 'timeframe', 'label' => 'Timeframe'],
            ['key' => 'resources_needed', 'label' => 'Resources Needed'],
        ],
        'action_plan_sustain' => [
            ['key' => 'strategy', 'label' => 'Strategy/Activity'],
            ['key' => 'timeline', 'label' => 'Time Frame'],
            ['key' => 'person_responsible', 'label' => 'Person(s) Responsible'],
            ['key' => 'resources_needed', 'label' => 'Resources Needed'],
        ],
        'financial_report' => [
            ['key' => 'item', 'label' => 'Item'],
            ['key' => 'budgeted_amount', 'label' => 'Budgeted Amount'],
            ['key' => 'actual_amount', 'label' => 'Actual Amount'],
            ['key' => 'variance', 'label' => 'Variance'],
            ['key' => 'remarks', 'label' => 'Remarks'],
        ],
        'timetable' => [
            ['key' => 'activity', 'label' => 'Activity'],
            ['key' => 'timeline', 'label' => 'Time Frame'],
            ['key' => 'person_responsible', 'label' => 'Person(s) Responsible'],
        ],
    ];

    public static function all(): array
    {
        return [
            self::actionProposal(),
            self::actionCompleted(),
            self::basicProposal(),
            self::basicCompleted(),
        ];
    }

    public static function for(string $researchType, string $classification): SubmissionTemplate
    {
        $key = "{$researchType}_{$classification}";

        foreach (self::all() as $template) {
            if ($template->key === $key) {
                return $template;
            }
        }

        throw new \InvalidArgumentException("No submission template registered for [{$key}].");
    }

    private static function actionProposal(): SubmissionTemplate
    {
        return new SubmissionTemplate(
            key: 'action_proposal',
            researchType: 'action',
            classification: 'proposal',
            label: 'Action Research Proposal',
            sections: [
                new SubmissionSection('context_and_rationale', 'Chapter I. Context and Rationale', 'rich_text'),
                new SubmissionSection('action_research_questions', 'Chapter II. Action Research Questions', 'rich_text'),
                new SubmissionSection('proposed_innovation_intervention_strategy', 'Chapter III. Proposed Innovation, Intervention, and Strategy', 'rich_text'),
                new SubmissionSection('participants_sources_of_data', 'Chapter IV.a Participants and/or Other Sources of Data and Information', 'rich_text'),
                new SubmissionSection('data_gathering_methods', 'Chapter IV.b Data Gathering Methods', 'rich_text'),
                new SubmissionSection('ethical_considerations', 'Chapter IV.c Ethical Considerations', 'rich_text'),
                new SubmissionSection('data_analysis_plan', 'Chapter IV.d Data Analysis Plan', 'rich_text'),
                new SubmissionSection('work_plan_and_timelines', 'Chapter V. Action Research Work Plan and Timelines', 'table', columns: self::TABLE_COLUMNS['work_plan']),
                new SubmissionSection('cost_estimates', 'Chapter VI. Cost Estimates', 'table', columns: self::TABLE_COLUMNS['cost_estimate']),
                new SubmissionSection('dissemination_and_utilization_plan', 'Chapter VII. Plans for Dissemination and Utilization', 'table', columns: self::TABLE_COLUMNS['dissemination_plan']),
                new SubmissionSection('references', 'Chapter VIII. References', 'rich_text'),
            ],
            attachments: [
                new SubmissionAttachment('proposed_innovation_material', 'Copy of the Proposed Innovation/Intervention Material (Documentation, Narrative Form)'),
            ],
        );
    }

    private static function actionCompleted(): SubmissionTemplate
    {
        return new SubmissionTemplate(
            key: 'action_completed',
            researchType: 'action',
            classification: 'completed',
            label: 'Action Research Completed',
            sections: [
                new SubmissionSection('context_and_rationale', 'Chapter I. Context and Rationale', 'rich_text'),
                new SubmissionSection('action_research_questions', 'Chapter II. Action Research Questions', 'rich_text'),
                new SubmissionSection('proposed_innovation_intervention_strategy', 'Chapter III. Proposed Innovation, Intervention, and Strategy', 'rich_text'),
                new SubmissionSection('participants_sources_of_data', 'Chapter IV.a Participants and/or Other Sources of Data and Information', 'rich_text'),
                new SubmissionSection('data_gathering_methods', 'Chapter IV.b Data Gathering Methods', 'rich_text'),
                new SubmissionSection('ethical_issues', 'Chapter IV.c Ethical Issues', 'rich_text'),
                new SubmissionSection('data_analysis_plan', 'Chapter IV.d Data Analysis Plan', 'rich_text'),
                new SubmissionSection('discussion_of_results_and_reflection', 'Chapter V. Discussion of Results and Reflection', 'rich_text'),
                new SubmissionSection('conclusions', 'Conclusions', 'rich_text'),
                new SubmissionSection('recommendations', 'Recommendations', 'rich_text'),
                new SubmissionSection('reflection', 'Reflection', 'rich_text'),
                new SubmissionSection('action_plan_to_sustain_utilization', 'Chapter VI. Action Plan to Sustain the Utilization of the Intervention Material', 'table', columns: self::TABLE_COLUMNS['action_plan_sustain']),
                new SubmissionSection('references', 'Chapter VII. References', 'rich_text'),
                new SubmissionSection('financial_report', 'Chapter VIII. Financial Report', 'table', columns: self::TABLE_COLUMNS['financial_report']),
            ],
            attachments: array_map(
                fn (array $attachment) => new SubmissionAttachment($attachment['key'], $attachment['label']),
                self::COMPLETED_ATTACHMENTS,
            ),
        );
    }

    private static function basicProposal(): SubmissionTemplate
    {
        return new SubmissionTemplate(
            key: 'basic_proposal',
            researchType: 'basic',
            classification: 'proposal',
            label: 'Basic Research Proposal',
            sections: [
                new SubmissionSection('context_and_rationale', 'Chapter I. Context and Rationale', 'rich_text'),
                new SubmissionSection('literature_review_and_studies', 'Chapter II. Literature Review and Studies', 'rich_text'),
                new SubmissionSection('research_questions', 'Chapter III. Research Questions', 'rich_text'),
                new SubmissionSection('scope_and_limitation', 'Chapter IV. Scope and Limitation', 'rich_text'),
                new SubmissionSection('sampling_method', 'Chapter V.a Sampling Method', 'rich_text'),
                new SubmissionSection('data_collection_methods', 'Chapter V.b Data Collection Methods', 'rich_text'),
                new SubmissionSection('ethical_considerations', 'Chapter V.c Ethical Considerations', 'rich_text'),
                new SubmissionSection('plan_for_data_analysis', 'Chapter V.d Plan for Data Analysis', 'rich_text'),
                new SubmissionSection('timetable', 'Chapter VI. Timetable', 'table', columns: self::TABLE_COLUMNS['timetable']),
                new SubmissionSection('cost_estimates', 'Chapter VII. Cost Estimates', 'table', columns: self::TABLE_COLUMNS['cost_estimate']),
                new SubmissionSection('dissemination_and_advocacy_plan', 'Chapter VIII. Plans for Dissemination and Advocacy Plan', 'table', columns: self::TABLE_COLUMNS['dissemination_plan']),
                new SubmissionSection('references', 'Chapter IX. References', 'rich_text'),
            ],
            attachments: [
                new SubmissionAttachment('research_instrument', 'Copy of the Research Instrument (Documentation, Narrative Form)'),
            ],
        );
    }

    private static function basicCompleted(): SubmissionTemplate
    {
        return new SubmissionTemplate(
            key: 'basic_completed',
            researchType: 'basic',
            classification: 'completed',
            label: 'Basic Research Completed',
            sections: [
                new SubmissionSection('introduction_and_rationale', 'Chapter I. Introduction and Rationale', 'rich_text'),
                new SubmissionSection('literature_review_and_studies', 'Chapter II. Literature Review and Studies', 'rich_text'),
                new SubmissionSection('research_questions', 'Chapter III. Research Questions', 'rich_text'),
                new SubmissionSection('scope_and_limitation', 'Chapter IV. Scope and Limitation', 'rich_text'),
                new SubmissionSection('sampling_method', 'Chapter V.a Sampling Method', 'rich_text'),
                new SubmissionSection('data_collection_methods', 'Chapter V.b Data Collection Methods', 'rich_text'),
                new SubmissionSection('ethical_considerations', 'Chapter V.c Ethical Considerations', 'rich_text'),
                new SubmissionSection('plan_for_data_analysis', 'Chapter V.d Plan for Data Analysis', 'rich_text'),
                new SubmissionSection('discussion_of_results_and_recommendations', 'Chapter VI. Discussions of Results and Recommendations', 'rich_text'),
                new SubmissionSection('conclusions', 'Conclusions', 'rich_text'),
                new SubmissionSection('recommendations', 'Recommendations', 'rich_text'),
                new SubmissionSection('reflection', 'Reflection', 'rich_text'),
                new SubmissionSection('dissemination_and_advocacy_plan', 'Chapter VII. Plans for Dissemination and Advocacy Plan', 'table', columns: self::TABLE_COLUMNS['dissemination_plan']),
                new SubmissionSection('references', 'Chapter VIII. References', 'rich_text'),
                new SubmissionSection('financial_report', 'Chapter IX. Financial Report', 'table', columns: self::TABLE_COLUMNS['financial_report']),
            ],
            attachments: array_map(
                fn (array $attachment) => new SubmissionAttachment($attachment['key'], $attachment['label']),
                self::COMPLETED_ATTACHMENTS,
            ),
        );
    }
}
