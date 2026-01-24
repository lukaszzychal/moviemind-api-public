<?php

namespace App\Filament\Resources\OutgoingWebhookResource\Pages;

use App\Filament\Resources\OutgoingWebhookResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOutgoingWebhooks extends ListRecords
{
    protected static string $resource = OutgoingWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
