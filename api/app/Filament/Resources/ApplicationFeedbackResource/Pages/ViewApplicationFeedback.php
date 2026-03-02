<?php

namespace App\Filament\Resources\ApplicationFeedbackResource\Pages;

use App\Filament\Resources\ApplicationFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewApplicationFeedback extends ViewRecord
{
    protected static string $resource = ApplicationFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
