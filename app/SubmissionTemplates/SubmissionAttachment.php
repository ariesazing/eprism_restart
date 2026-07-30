<?php

namespace App\SubmissionTemplates;

class SubmissionAttachment
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $required = true,
    ) {}
}
