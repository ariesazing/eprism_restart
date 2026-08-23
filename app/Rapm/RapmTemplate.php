<?php

namespace App\Rapm;

/**
 * A single RAPM document's shape: unlike App\SubmissionTemplates\SubmissionTemplate, this
 * has no sections/attachments/researchType/classification — a review summary or routing
 * slip is one fixed document, not a per-research-type variant, so it only needs a
 * placeholder reference for the admin editor's "Available Placeholders" sidebar.
 */
class RapmTemplate
{
    /**
     * @param  array<int, string>  $scalars
     * @param  array<int, array{key: string, fields: array<int, string>}>  $each
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $scalars,
        public readonly array $each,
    ) {}
}
