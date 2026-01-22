<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonResource\Pages;
use App\Filament\Resources\PersonResource\RelationManagers;
use App\Models\Person;
use App\Services\AiGenerationTriggerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\DatePicker::make('birth_date'),
                Forms\Components\TextInput::make('birthplace')
                    ->maxLength(255),
                Forms\Components\TextInput::make('tmdb_id')
                    ->numeric()
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('birth_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('birthplace')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bios_count')
                    ->counts('bios')
                    ->label('Bios'),
                Tables\Columns\TextColumn::make('tmdb_id')
                    ->label('TMDb')
                    ->formatStateUsing(fn ($state) => $state ? 'Link' : '-')
                    ->url(fn ($state) => $state ? "https://www.themoviedb.org/person/{$state}" : null)
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
                    ->action(function (Person $record, array $data, AiGenerationTriggerService $service) {
                        $success = $service->trigger(
                            entityType: 'PERSON',
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
            RelationManagers\BiosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPeople::route('/'),
            'create' => Pages\CreatePerson::route('/create'),
            'edit' => Pages\EditPerson::route('/{record}/edit'),
        ];
    }
}
