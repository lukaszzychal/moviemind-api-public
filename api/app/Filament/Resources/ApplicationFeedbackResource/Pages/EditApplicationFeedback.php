<?php

namespace App\Filament\Resources\ApplicationFeedbackResource\Pages;

use App\Filament\Resources\ApplicationFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApplicationFeedback extends EditRecord
{
    protected static string $resource = ApplicationFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
