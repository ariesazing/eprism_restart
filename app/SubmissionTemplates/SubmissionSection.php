<?php

namespace App\SubmissionTemplates;

class SubmissionSection
{
    /**
     * @param  array<int, array{key: string, label: string}>|null  $columns
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly bool $required = true,
        public readonly ?array $columns = null,
    ) {}
}
