<?php

namespace App\Filament\Widgets;

use App\Models\FailedJob;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FailedJobsWidget extends BaseWidget
{
    protected static ?string $heading = 'Failed Jobs';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FailedJob::query()->orderBy('failed_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('queue')
                    ->badge()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('payload')
                    ->formatStateUsing(function ($state) {
                        if (is_string($state)) {
                            $decoded = json_decode($state, true);

                            return $decoded['displayName'] ?? 'Unknown Job';
                        }

                        return $state['displayName'] ?? 'Unknown Job';
                    })
                    ->label('Job Class'),
                Tables\Columns\TextColumn::make('exception')
                    ->limit(50)
                    ->tooltip(fn (?string $state) => $state ?? '')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('failed_at')
                    ->dateTime()
                    ->placeholder('N/A'),
            ]);
    }
}
