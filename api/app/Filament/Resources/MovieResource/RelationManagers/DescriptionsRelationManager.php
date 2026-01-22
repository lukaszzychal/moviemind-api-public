<?php

namespace App\Filament\Resources\MovieResource\RelationManagers;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\MovieDescription;
use App\Models\MovieReport;
use App\Services\MovieReportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
        return $table
            ->recordTitleAttribute('locale')
            ->columns([
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Read-only mostly, but allow manual creation if needed
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
}
