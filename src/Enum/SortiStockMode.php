<?php

namespace App\Enum;

enum SortiStockMode: string
{
    case FIFO = 'FIFO';
    case LIFO = 'LIFO';
    case FEFO = 'FEFO';
    case DOSSIER = 'Dossier';
}
