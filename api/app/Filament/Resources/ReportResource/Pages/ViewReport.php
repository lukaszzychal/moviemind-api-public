<?php

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure record is loaded before form is filled
        $record = $this->record;

        if ($record) {
            // Pre-load entity and description data to avoid issues in form
            $entityType = $record->entity_type ?? null;
            $entityId = $record->entity_id ?? null;
            $descriptionId = $record->description_id ?? null;

            // Add computed fields to form data
            if ($entityId && $entityType) {
                $data['entity_name'] = $this->getEntityName($entityType, $entityId);
            }

            if ($descriptionId && $entityType) {
                $data['description_full'] = $this->getDescriptionText($entityType, $descriptionId);
            }
        }

        return $data;
    }

    private function getEntityName(string $entityType, string $entityId): string
    {
        return match ($entityType) {
            'movie' => \App\Models\Movie::find($entityId)?->title ?? "Movie #{$entityId}",
            'person' => \App\Models\Person::find($entityId)?->name ?? "Person #{$entityId}",
            'tv_series' => \App\Models\TvSeries::find($entityId)?->title ?? "TV Series #{$entityId}",
            'tv_show' => \App\Models\TvShow::find($entityId)?->title ?? "TV Show #{$entityId}",
            default => "Unknown #{$entityId}",
        };
    }

    private function getDescriptionText(string $entityType, string $descriptionId): ?string
    {
        $description = match ($entityType) {
            'movie' => \App\Models\MovieDescription::find($descriptionId),
            'person' => \App\Models\PersonBio::find($descriptionId),
            'tv_series' => \App\Models\TvSeriesDescription::find($descriptionId),
            'tv_show' => \App\Models\TvShowDescription::find($descriptionId),
            default => null,
        };

        return $description?->text ?? null;
    }
}
