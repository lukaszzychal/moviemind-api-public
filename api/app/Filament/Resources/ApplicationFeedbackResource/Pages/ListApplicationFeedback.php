<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApplicationFeedbackResource\Pages;

use App\Filament\Resources\ApplicationFeedbackResource;
use Filament\Resources\Pages\ListRecords;

class ListApplicationFeedback extends ListRecords
{
    protected static string $resource = ApplicationFeedbackResource::class;
}
