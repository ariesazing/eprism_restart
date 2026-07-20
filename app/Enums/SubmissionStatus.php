<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case REVISIONS_REQUIRED = 'revisions_required';
    case RESUBMITTED = 'resubmitted';
    case APPROVED = 'approved';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}