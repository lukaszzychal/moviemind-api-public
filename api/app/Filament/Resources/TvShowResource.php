<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TvShowResource\Pages;
use App\Filament\Resources\TvShowResource\RelationManagers;
use App\Models\TvShow;
use App\Services\AiGenerationTriggerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TvShowResource extends Resource
{
    protected static ?string $model = TvShow::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

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
                Forms\Components\DatePicker::make('first_air_date'),
                Forms\Components\DatePicker::make('last_air_date'),
                Forms\Components\TextInput::make('number_of_seasons')
                    ->numeric(),
                Forms\Components\TextInput::make('number_of_episodes')
                    ->numeric(),
                Forms\Components\TextInput::make('show_type')
                    ->maxLength(50),
                Forms\Components\TextInput::make('tmdb_id')
                    ->numeric()
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_air_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('show_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('descriptions_count')
                    ->counts('descriptions')
                    ->label('Descriptions'),
                Tables\Columns\TextColumn::make('tmdb_id')
                    ->label('TMDb')
                    ->formatStateUsing(fn ($state) => $state ? 'Link' : '-')
                    ->url(fn ($state) => $state ? "https://www.themoviedb.org/tv/{$state}" : null)
                    ->openUrlInNewTab()
                    ->color(fn ($state) => $state ? 'primary' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_tmdb_id')
                    ->query(fn ($query) => $query->whereNotNull('tmdb_id'))
                    ->label('Has TMDb ID'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate')
                    ->label('Generate AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('locale')
                            ->options([
                                'en-US' => 'English (US)',
                                'pl-PL' => 'Polish',
                                'de-DE' => 'German',
                                'fr-FR' => 'French',
                                'es-ES' => 'Spanish',
                            ])
                            ->default('en-US')
                            ->required(),
                        Forms\Components\Select::make('context_tag')
                            ->options([
                                'default' => 'Default',
                                'short' => 'Short',
                                'detailed' => 'Detailed',
                                'funny' => 'Humorous',
                                'critical' => 'Critical',
                            ])
                            ->default('default')
                            ->required(),
                    ])
                    ->action(function (TvShow $record, array $data, AiGenerationTriggerService $service) {
                        $success = $service->trigger(
                            entityType: 'TV_SHOW',
                            slug: $record->slug,
                            locale: $data['locale'],
                            contextTag: $data['context_tag']
                        );

                        if ($success) {
                            Notification::make()
                                ->title('Generation queued successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Generation failed')
                                ->danger()
                                ->send();
                        }
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
            'index' => Pages\ListTvShows::route('/'),
            'create' => Pages\CreateTvShow::route('/create'),
            'edit' => Pages\EditTvShow::route('/{record}/edit'),
        ];
    }
}
