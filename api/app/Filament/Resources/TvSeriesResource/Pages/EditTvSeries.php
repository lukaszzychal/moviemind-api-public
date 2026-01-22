<?php

namespace App\Filament\Resources\TvSeriesResource\Pages;

use App\Filament\Resources\TvSeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTvSeries extends EditRecord
{
    protected static string $resource = TvSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
