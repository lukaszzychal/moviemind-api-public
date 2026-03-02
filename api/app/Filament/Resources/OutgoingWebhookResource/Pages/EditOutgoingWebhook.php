<?php

namespace App\Filament\Resources\OutgoingWebhookResource\Pages;

use App\Filament\Resources\OutgoingWebhookResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOutgoingWebhook extends EditRecord
{
    protected static string $resource = OutgoingWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
