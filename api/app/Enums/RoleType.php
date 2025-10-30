<?php

namespace App\Enums;

enum RoleType: string
{
    case ACTOR = 'ACTOR';
    case DIRECTOR = 'DIRECTOR';
    case WRITER = 'WRITER';
    case PRODUCER = 'PRODUCER';
}
