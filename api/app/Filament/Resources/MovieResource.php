<?php

namespace App\Filament\Resources;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Filament\Resources\MovieResource\Pages;
use App\Filament\Resources\MovieResource\RelationManagers;
use App\Models\Movie;
use App\Models\MovieReport;
use App\Services\AiGenerationTriggerService;
use App\Services\MovieReportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MovieResource extends Resource
{
    protected static ?string $model = Movie::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('release_year')
                    ->numeric()
                    ->minValue(1800)
                    ->maxValue(2100),
                Forms\Components\TextInput::make('director')
                    ->maxLength(255),
                Forms\Components\TagsInput::make('genres'),
                Forms\Components\TextInput::make('tmdb_id')
                    ->numeric()
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('release_year')->sortable(),
                Tables\Columns\TextColumn::make('director')->searchable(),
                Tables\Columns\TextColumn::make('descriptions_count')->counts('descriptions')->label('Descriptions'),
                Tables\Columns\TextColumn::make('tmdb_id')
                    ->label('TMDb')
                    ->formatStateUsing(fn ($state) => $state ? 'Link' : '-')
                    ->url(fn ($state) => $state ? "https://www.themoviedb.org/movie/{$state}" : null)
                    ->openUrlInNewTab()
                    ->color(fn ($state) => $state ? 'primary' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_tmdb_id')->query(fn ($query) => $query->whereNotNull('tmdb_id'))->label('Has TMDb ID'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate')
                    ->label('Generate AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('locale')->options(['en-US' => 'English (US)', 'pl-PL' => 'Polish', 'de-DE' => 'German', 'fr-FR' => 'French', 'es-ES' => 'Spanish'])->default('en-US')->required(),
                        Forms\Components\Select::make('context_tag')->options(['DEFAULT' => 'Default', 'modern' => 'Modern', 'humorous' => 'Humorous', 'critical' => 'Critical'])->default('DEFAULT')->required(),
                    ])
                    ->action(function (Movie $record, array $data, AiGenerationTriggerService $service) {
                        \Illuminate\Support\Facades\Log::info('MovieResource: Generate AI action', [
                            'slug' => $record->slug,
                            'locale_from_form' => $data['locale'] ?? 'NOT_SET',
                            'context_tag_from_form' => $data['context_tag'] ?? 'NOT_SET',
                            'all_data' => $data,
                        ]);
                        $result = $service->trigger(
                            entityType: 'MOVIE',
                            slug: $record->slug,
                            locale: $data['locale'] ?? 'en-US',
                            contextTag: $data['context_tag'] ?? 'DEFAULT'
                        );

                        if ($result && isset($result['job_id'])) {
                            $message = $result['message'] ?? 'Generation queued successfully';
                            Notification::make()->title($message)->body('Job ID: '.$result['job_id'])->success()->send();
                        } else {
                            Notification::make()->title('Generation failed')->danger()->send();
                        }
                    }),
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
                    ->action(function (Movie $record, array $data, MovieReportService $reportService) {
                        $report = MovieReport::create([
                            'movie_id' => $record->id,
                            'description_id' => null,
                            'type' => ReportType::from($data['type']),
                            'message' => $data['message'],
                            'suggested_fix' => $data['suggested_fix'] ?? null,
                            'status' => ReportStatus::PENDING,
                        ]);

                        $priorityScore = $reportService->calculatePriorityScore($report);
                        $report->update(['priority_score' => $priorityScore]);

                        // Also update priority scores for other pending reports of same type
                        MovieReport::where('movie_id', $record->id)
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DescriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovies::route('/'),
            'create' => Pages\CreateMovie::route('/create'),
            'edit' => Pages\EditMovie::route('/{record}/edit'),
        ];
    }
}
