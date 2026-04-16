<?php

namespace App\Enum;

enum ArticleModeGestion: string
{
    case EN_STOCK = 'En stock';
    case HORS_STOCK = 'Hors Stock';
    case EXTERIEUR = 'Extérieur';
}
