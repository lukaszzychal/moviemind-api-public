<?php

namespace App\Filament\Resources\MovieResource\RelationManagers;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\AiGenerationMetric;
use App\Models\MovieDescription;
use App\Models\MovieReport;
use App\Services\MovieReportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Laravel\Pennant\Feature;

class DescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'descriptions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('locale')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('context_tag')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('text')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $columns = [
            Tables\Columns\TextColumn::make('locale')
                ->badge(),
            Tables\Columns\TextColumn::make('context_tag')
                ->badge()
                ->color('info'),
            Tables\Columns\TextColumn::make('text')
                ->limit(50)
                ->tooltip(fn (string $state): string => $state),
            Tables\Columns\TextColumn::make('ai_model')
                ->label('Model')
                ->toggleable(),
        ];

        // Add AI metrics columns (always show, but may be empty)
        $columns[] = Tables\Columns\TextColumn::make('metrics.total_tokens')
            ->label('Tokens')
            ->getStateUsing(fn (MovieDescription $record) => $this->getMetricsForRecord($record)?->total_tokens ?? 'N/A')
            ->toggleable()
            ->sortable(false);

        $columns[] = Tables\Columns\IconColumn::make('metrics.parsing_successful')
            ->label('Parsing')
            ->getStateUsing(fn (MovieDescription $record) => $this->getMetricsForRecord($record)?->parsing_successful ?? null)
            ->boolean()
            ->trueIcon('heroicon-o-check-circle')
            ->falseIcon('heroicon-o-x-circle')
            ->trueColor('success')
            ->falseColor('danger')
            ->toggleable();

        // Quality metrics (if flags are active)
        if (Feature::active('ai_quality_scoring')) {
            $columns[] = Tables\Columns\TextColumn::make('metrics.quality_score')
                ->label('Quality Score')
                ->getStateUsing(fn (MovieDescription $record) => $this->getMetricsForRecord($record)?->quality_score ?? 'N/A')
                ->toggleable();
        }

        if (Feature::active('hallucination_guard')) {
            $columns[] = Tables\Columns\IconColumn::make('metrics.hallucination_detected')
                ->label('Hallucination')
                ->getStateUsing(fn (MovieDescription $record) => $this->getMetricsForRecord($record)?->hallucination_detected ?? null)
                ->boolean()
                ->trueIcon('heroicon-o-exclamation-triangle')
                ->falseIcon('heroicon-o-check-circle')
                ->trueColor('warning')
                ->falseColor('success')
                ->toggleable();
        }

        if (Feature::active('ai_plagiarism_detection')) {
            $columns[] = Tables\Columns\TextColumn::make('metrics.plagiarism_score')
                ->label('Plagiarism')
                ->getStateUsing(fn (MovieDescription $record) => $this->getMetricsForRecord($record)?->plagiarism_score ?? 'N/A')
                ->toggleable();
        }

        $columns[] = Tables\Columns\TextColumn::make('created_at')
            ->dateTime()
            ->sortable();

        return $table
            ->recordTitleAttribute('locale')
            ->columns($columns)
            ->filters([
                //
            ])
            ->headerActions([
                // Read-only mostly, but allow manual creation if needed
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(function (MovieDescription $record) {
                        $metrics = $this->getMetricsForRecord($record);
                        $view = view('filament.resources.movie-resource.relation-managers.description-view', [
                            'record' => $record,
                            'metrics' => $metrics,
                        ]);

                        return $view;
                    })
                    ->modalHeading(fn (MovieDescription $record) => 'Description Details - '.$record->locale->value),
                Tables\Actions\Action::make('report')
                    ->label('Report Issue')
                    ->icon('heroicon-o-flag')
                    ->color('danger')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Report Type')
                            ->options(collect(ReportType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()]))
                            ->required()
                            ->native(false),
                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->required()
                            ->minLength(10)
                            ->maxLength(2000)
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('suggested_fix')
                            ->label('Suggested Fix (Optional)')
                            ->maxLength(2000)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (MovieDescription $record, array $data, MovieReportService $reportService) {
                        $report = MovieReport::create([
                            'movie_id' => $record->movie_id,
                            'description_id' => $record->id,
                            'type' => ReportType::from($data['type']),
                            'message' => $data['message'],
                            'suggested_fix' => $data['suggested_fix'] ?? null,
                            'status' => ReportStatus::PENDING,
                        ]);

                        $priorityScore = $reportService->calculatePriorityScore($report);
                        $report->update(['priority_score' => $priorityScore]);

                        // Also update priority scores for other pending reports of same type
                        MovieReport::where('movie_id', $record->movie_id)
                            ->where('type', $report->type)
                            ->where('status', ReportStatus::PENDING)
                            ->where('id', '!=', $report->id)
                            ->get()
                            ->each(function (MovieReport $otherReport) use ($reportService) {
                                $otherReport->update(['priority_score' => $reportService->calculatePriorityScore($otherReport)]);
                            });

                        Notification::make()
                            ->title('Report submitted successfully')
                            ->body('Report ID: '.$report->id)
                            ->success()
                            ->send();
                    }),
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
    }

    /**
     * Get AI generation metrics for a record.
     */
    private function getMetricsForRecord(?MovieDescription $record = null): ?AiGenerationMetric
    {
        if ($record === null) {
            return null;
        }

        // Load movie relationship if not loaded
        if (! $record->relationLoaded('movie')) {
            $record->load('movie');
        }

        if (! $record->movie || ! $record->movie->slug) {
            return null;
        }

        // Get the most recent metric for this movie
        return AiGenerationMetric::where('entity_type', 'MOVIE')
            ->where('entity_slug', $record->movie->slug)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
