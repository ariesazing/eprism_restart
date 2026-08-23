<?php

namespace App\Rapm;

use App\Models\RapmDocument;

class RapmTemplateRegistry
{
    public static function all(): array
    {
        return [
            self::reviewSummary(),
            self::routingSlip(),
        ];
    }

    public static function for(string $key): RapmTemplate
    {
        foreach (self::all() as $template) {
            if ($template->key === $key) {
                return $template;
            }
        }

        throw new \InvalidArgumentException("No RAPM template registered for [{$key}].");
    }

    private static function reviewSummary(): RapmTemplate
    {
        return new RapmTemplate(
            key: RapmDocument::KIND_REVIEW_SUMMARY,
            label: 'Review Summary',
            scalars: [
                'title',
                'reference_code',
                'research_type_label',
                'classification_label',
                'organizational_unit',
                'researcher_name',
                'overall_recommendation_label',
                'admin_notes',
                'template_label',
                'generated_at',
            ],
            each: [
                [
                    'key' => 'reviewers',
                    'fields' => [
                        'reviewer_name',
                        'originality',
                        'methodology',
                        'clarity',
                        'compliance',
                        'average_score',
                        'recommendation_label',
                        'comments',
                        'submitted_at',
                    ],
                ],
            ],
        );
    }

    private static function routingSlip(): RapmTemplate
    {
        return new RapmTemplate(
            key: RapmDocument::KIND_ROUTING_SLIP,
            label: 'Routing Slip',
            scalars: [
                'title',
                'reference_code',
                'researcher_name',
                'organizational_unit',
                'submitted_at',
                'approved_at',
                'current_status_label',
                'template_label',
                'generated_at',
            ],
            each: [
                [
                    'key' => 'routing_steps',
                    'fields' => ['step_date', 'actor_name', 'action_label', 'description'],
                ],
            ],
        );
    }
}
