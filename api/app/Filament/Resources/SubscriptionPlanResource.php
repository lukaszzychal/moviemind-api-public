<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Internal identifier (e.g. "pro")'),
                        Forms\Components\TextInput::make('display_name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Public name (e.g. "Pro Plan")'),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('monthly_limit')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('0 for unlimited'),
                                Forms\Components\TextInput::make('rate_limit_per_minute')
                                    ->required()
                                    ->numeric()
                                    ->default(60),
                            ]),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price_monthly')
                                    ->numeric()
                                    ->prefix('$')
                                    ->maxValue(999999.99),
                                Forms\Components\TextInput::make('price_yearly')
                                    ->numeric()
                                    ->prefix('$')
                                    ->maxValue(999999.99),
                            ]),
                        Forms\Components\TagsInput::make('features')
                            ->separator(',')
                            ->suggestions([
                                'ai_generate',
                                'bulk_export',
                                'advanced_search',
                            ])
                            ->columnSpanFull()
                            ->helperText('Press Enter to add feature tags. Required for /generate: "ai_generate"'),
                        Forms\Components\Toggle::make('is_active')
                            ->required()
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SubscriptionPlan $record): string => $record->name),
                Tables\Columns\TextColumn::make('monthly_limit')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Unlimited' : number_format($state)),
                Tables\Columns\TextColumn::make('rate_limit_per_minute')
                    ->numeric()
                    ->sortable()
                    ->label('Rate Limit (RPM)'),
                Tables\Columns\TextColumn::make('price_monthly')
                    ->money('USD')
                    ->sortable()
                    ->placeholder('Free'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
