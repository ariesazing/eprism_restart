<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case REVIEWER = 'reviewer';
    case RESEARCHER = 'researcher';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}