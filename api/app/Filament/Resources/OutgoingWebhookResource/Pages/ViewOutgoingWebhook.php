<?php

namespace App\Filament\Resources\OutgoingWebhookResource\Pages;

use App\Filament\Resources\OutgoingWebhookResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOutgoingWebhook extends ViewRecord
{
    protected static string $resource = OutgoingWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
