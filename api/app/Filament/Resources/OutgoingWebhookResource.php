<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OutgoingWebhookResource\Pages;
use App\Models\OutgoingWebhook;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OutgoingWebhookResource extends Resource
{
    protected static ?string $model = OutgoingWebhook::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Webhooks';

    protected static ?string $navigationLabel = 'Webhook history';

    public static function form(Form $form): Form
    {
        $eventTypeOptions = array_combine(
            $keys = array_keys(config('webhooks.outgoing_urls', [])),
            $keys
        ) ?: [];

        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('event_type')
                            ->required()
                            ->options($eventTypeOptions)
                            ->searchable()
                            ->helperText('Event types from config/webhooks.php (outgoing_urls).'),
                        Forms\Components\TextInput::make('url')
                            ->label('Webhook URL')
                            ->required()
                            ->maxLength(500)
                            ->helperText('Full URL to receive POST request (e.g. https://example.com/webhook).')
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('payload')
                            ->label('Request Payload')
                            ->helperText('Optional JSON key-value payload; leave empty for minimal payload.')
                            ->columnSpanFull(),
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->required()
                                    ->options([
                                        'pending' => 'Pending',
                                        'sent' => 'Sent',
                                        'failed' => 'Failed',
                                        'permanently_failed' => 'Permanently Failed',
                                    ])
                                    ->default('pending')
                                    ->helperText('DB enum: pending, sent, failed, permanently_failed.'),
                                Forms\Components\TextInput::make('attempts')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Number of delivery attempts (default 0 for new).'),
                                Forms\Components\TextInput::make('response_code')
                                    ->numeric()
                                    ->helperText('HTTP response code (set after delivery).'),
                            ]),
                        Forms\Components\Textarea::make('error_message')
                            ->columnSpanFull()
                            ->visible(fn (?OutgoingWebhook $record): bool => $record !== null && $record->error_message !== null),
                        Forms\Components\KeyValue::make('response_body')
                            ->label('Response Body')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_type')
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('url')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'sent' => 'success',
                        'failed' => 'danger',
                        'permanently_failed' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('attempts')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('response_code')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state >= 200 && $state < 300 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'permanently_failed' => 'Permanently Failed',
                    ]),
                Tables\Filters\SelectFilter::make('event_type')
                    ->options(fn () => OutgoingWebhook::distinct()->pluck('event_type', 'event_type')->toArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListOutgoingWebhooks::route('/'),
            'create' => Pages\CreateOutgoingWebhook::route('/create'),
            'view' => Pages\ViewOutgoingWebhook::route('/{record}'),
            'edit' => Pages\EditOutgoingWebhook::route('/{record}/edit'),
        ];
    }
}
