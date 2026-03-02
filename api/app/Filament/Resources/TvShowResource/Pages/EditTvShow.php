<?php

namespace App\Filament\Resources\TvShowResource\Pages;

use App\Filament\Resources\TvShowResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTvShow extends EditRecord
{
    protected static string $resource = TvShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
