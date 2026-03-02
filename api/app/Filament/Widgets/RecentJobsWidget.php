<?php

namespace App\Filament\Widgets;

use App\Models\AiJob;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentJobsWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Jobs';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AiJob::query()->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('job_id')
                    ->label('Job ID')
                    ->getStateUsing(fn ($record) => $record->payload_json['job_id'] ?? 'N/A')
                    ->copyable()
                    ->copyMessage('Job ID copied!')
                    ->searchable(false),
                Tables\Columns\TextColumn::make('entity_type')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('entity_id')
                    ->label('Entity ID'),
                Tables\Columns\TextColumn::make('locale')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('context_tag')
                    ->badge()
                    ->color('success')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PENDING' => 'warning',
                        'DONE' => 'success',
                        'FAILED' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Created At'),
            ])
            ->poll('5s');
    }
}
