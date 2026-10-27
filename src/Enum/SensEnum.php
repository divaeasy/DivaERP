<?php

namespace App\Enum;

enum SensEnum: string
{
    case DEBIT = 'Entree';
    case CREDIT = 'Sortie';

    public function label(): string
    {
        return match ($this) {
            self::DEBIT => 'Entree',
            self::CREDIT => 'Sortie',
        };
    }
}
