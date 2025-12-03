<?php

declare(strict_types=1);

namespace App\Enums;

enum JobErrorType: string
{
    case NOT_FOUND = 'NOT_FOUND';
    case AI_API_ERROR = 'AI_API_ERROR';
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case UNKNOWN_ERROR = 'UNKNOWN_ERROR';
}
