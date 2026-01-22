<?php

namespace App\Filament\Resources;

use App\Actions\VerifyMovieReportAction;
use App\Actions\VerifyPersonReportAction;
use App\Actions\VerifyTvSeriesReportAction;
use App\Actions\VerifyTvShowReportAction;
use App\Enums\ReportStatus;
use App\Filament\Resources\ReportResource\Pages;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Models\MovieReport;
use App\Models\Person;
use App\Models\PersonBio;
use App\Models\PersonReport;
use App\Models\Report;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Models\TvSeriesReport;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use App\Models\TvShowReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Moderation';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Information')
                    ->schema([
                        Forms\Components\TextInput::make('entity_type')
                            ->label('Entity Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('entity_name')
                            ->label('Entity')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('description_full')
                            ->label('Description')
                            ->disabled()
                            ->rows(5)
                            ->dehydrated(false)
                            ->placeholder('N/A (report is about entity, not specific description)'),
                    ]),
                Forms\Components\Section::make('Report Details')
                    ->schema([
                        Forms\Components\TextInput::make('type')
                            ->label('Report Type')
                            ->disabled(),
                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->disabled()
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('suggested_fix')
                            ->label('Suggested Fix')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('No suggested fix provided'),
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                        Forms\Components\TextInput::make('priority_score')
                            ->label('Priority Score')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entity_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'movie' => 'info',
                        'person' => 'success',
                        'tv_series' => 'warning',
                        'tv_show' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('entity_name')
                    ->label('Entity')
                    ->getStateUsing(function (Report $record): string {
                        $entityType = $record->entity_type;
                        $entityId = $record->entity_id;

                        if (! $entityId) {
                            return 'N/A';
                        }

                        return match ($entityType) {
                            'movie' => Movie::find($entityId)?->title ?? "Movie #{$entityId}",
                            'person' => Person::find($entityId)?->name ?? "Person #{$entityId}",
                            'tv_series' => TvSeries::find($entityId)?->title ?? "TV Series #{$entityId}",
                            'tv_show' => TvShow::find($entityId)?->title ?? "TV Show #{$entityId}",
                            default => "Unknown #{$entityId}",
                        };
                    })
                    ->searchable(false)
                    ->sortable(false),
                Tables\Columns\TextColumn::make('description_preview')
                    ->label('Description')
                    ->getStateUsing(function (Report $record): ?string {
                        $descriptionId = $record->description_id;
                        $entityType = $record->entity_type;

                        if (! $descriptionId) {
                            return null;
                        }

                        $description = match ($entityType) {
                            'movie' => MovieDescription::find($descriptionId),
                            'person' => PersonBio::find($descriptionId),
                            'tv_series' => TvSeriesDescription::find($descriptionId),
                            'tv_show' => TvShowDescription::find($descriptionId),
                            default => null,
                        };

                        if (! $description || ! isset($description->text)) {
                            return null;
                        }

                        return \Illuminate\Support\Str::limit($description->text, 100);
                    })
                    ->tooltip(function (Report $record): ?string {
                        $descriptionId = $record->description_id;
                        $entityType = $record->entity_type;

                        if (! $descriptionId) {
                            return null;
                        }

                        $description = match ($entityType) {
                            'movie' => MovieDescription::find($descriptionId),
                            'person' => PersonBio::find($descriptionId),
                            'tv_series' => TvSeriesDescription::find($descriptionId),
                            'tv_show' => TvShowDescription::find($descriptionId),
                            default => null,
                        };

                        return $description?->text;
                    })
                    ->limit(50)
                    ->placeholder('N/A')
                    ->searchable(false)
                    ->sortable(false),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof ReportStatus ? $state->value : $state) {
                        'pending' => 'warning',
                        'verified' => 'info',
                        'resolved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('priority_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'verified' => 'Verified',
                        'resolved' => 'Resolved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('entity_type')
                    ->options([
                        'movie' => 'Movie',
                        'person' => 'Person',
                        'tv_series' => 'TV Series',
                        'tv_show' => 'TV Show',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('verify')
                    ->label('Verify & Regenerate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Verify Report')
                    ->modalDescription('This will verify the report and trigger regeneration if applicable. Are you sure?')
                    ->visible(fn (Report $record) => ($record->status instanceof ReportStatus ? $record->status->value : $record->status) === 'pending')
                    ->action(function (Report $record, VerifyMovieReportAction $verifyMovieAction, VerifyPersonReportAction $verifyPersonAction, VerifyTvSeriesReportAction $verifyTvSeriesAction, VerifyTvShowReportAction $verifyTvShowAction) {
                        try {
                            $entityType = $record->entity_type;
                            $reportId = $record->id;

                            match ($entityType) {
                                'movie' => self::verifyMovieReport($reportId, $verifyMovieAction),
                                'person' => self::verifyPersonReport($reportId, $verifyPersonAction),
                                'tv_series' => self::verifyTvSeriesReport($reportId, $verifyTvSeriesAction),
                                'tv_show' => self::verifyTvShowReport($reportId, $verifyTvShowAction),
                                default => throw new \RuntimeException("Unknown entity type: {$entityType}"),
                            };

                            Notification::make()
                                ->title('Report verified successfully')
                                ->body('Regeneration has been queued if applicable.')
                                ->success()
                                ->send();
                        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                            Notification::make()
                                ->title('Report not found')
                                ->body('The report could not be found in the database.')
                                ->danger()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Verification failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                // Read-only
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'view' => Pages\ViewReport::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Verify a movie report.
     */
    private static function verifyMovieReport(string $reportId, VerifyMovieReportAction $action): void
    {
        $report = MovieReport::find($reportId);
        if ($report === null) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Movie report not found: {$reportId}");
        }

        $action->handle($reportId);
    }

    /**
     * Verify a person report.
     */
    private static function verifyPersonReport(string $reportId, VerifyPersonReportAction $action): void
    {
        $report = PersonReport::find($reportId);
        if ($report === null) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Person report not found: {$reportId}");
        }

        $action->handle($reportId);
    }

    /**
     * Verify a TV series report.
     */
    private static function verifyTvSeriesReport(string $reportId, VerifyTvSeriesReportAction $action): void
    {
        $report = TvSeriesReport::find($reportId);
        if ($report === null) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("TV series report not found: {$reportId}");
        }

        $action->handle($reportId);
    }

    /**
     * Verify a TV show report.
     */
    private static function verifyTvShowReport(string $reportId, VerifyTvShowReportAction $action): void
    {
        $report = TvShowReport::find($reportId);
        if ($report === null) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("TV show report not found: {$reportId}");
        }

        $action->handle($reportId);
    }
}
