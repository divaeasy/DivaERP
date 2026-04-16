<?php

namespace App\Enum;

enum ArticleModeSuivi: string
{
    case EN_QUANTITE = 'En quantité';
    case PAR_NUMERO_SERIE = 'Par N° de série';
    case PAR_NUMERO_LOT = 'Par N° de lot';
}
