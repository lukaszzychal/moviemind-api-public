<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleType: string
{
    case ACTOR = 'ACTOR';
    case DIRECTOR = 'DIRECTOR';
    case WRITER = 'WRITER';
    case PRODUCER = 'PRODUCER';
}
