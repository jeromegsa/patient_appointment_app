<?php

namespace App;

enum Service: string
{
    case MEDECINE_GENERALE = 'MEDECINE_GENERALE';
    case PEDIATRIE = 'PEDIATRIE';
    case GYNECOLOGIE = 'GYNECOLOGIE';
    case DENTISTERIE = 'DENTISTERIE';
}
