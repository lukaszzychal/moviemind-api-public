<?php

namespace App\Filament\Resources\TvShowResource\Pages;

use App\Filament\Resources\TvShowResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTvShows extends ListRecords
{
    protected static string $resource = TvShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
