<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Descriptive name for this key'),
                Forms\Components\Select::make('plan_id')
                    ->relationship('plan', 'display_name')
                    ->nullable()
                    ->label('Subscription Plan'),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Expiration Date')
                    ->nullable(),
                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->default(true),
                Forms\Components\Toggle::make('is_public')
                    ->label('Is Public Demo Key')
                    ->helperText('Mark this key as available for public demo endpoints.')
                    ->live(),
                Forms\Components\TextInput::make('public_plaintext_key')
                    ->label('Public Plaintext Key')
                    ->helperText(fn (string $operation) => $operation === 'create'
                        ? 'Provide a custom key (e.g., mm_demo_portfolio_staging). Leave blank to auto-generate.'
                        : 'Update the public key value. Changing it will re-hash and replace the key in the database.'
                    )
                    ->hidden(fn (Forms\Get $get): bool => $get('is_public') !== true)
                    ->maxLength(255),
                // Key and Prefix are handled by logic/hidden on create
                Forms\Components\TextInput::make('key_prefix')
                    ->disabled()
                    ->visibleOn('view')
                    ->hiddenOn(['create', 'edit']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('key_prefix')
                    ->label('Prefix')
                    ->searchable(),
                Tables\Columns\TextColumn::make('plan.display_name')
                    ->label('Plan')
                    ->sortable()
                    ->placeholder('None'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_public')
                    ->boolean()
                    ->label('Public'),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (ApiKey $record) => $record->isExpired() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(), // Keys are usually immutable aside from name/status
                Tables\Actions\DeleteAction::make()
                    ->label('Revoke')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Revoke Selected'),
                ]),
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
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
