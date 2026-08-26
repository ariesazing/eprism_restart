<?php

namespace App\Enums;

enum AccountStatus: string
{
    case ACTIVE = 'active';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
