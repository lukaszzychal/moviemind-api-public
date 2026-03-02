<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeatureFlagResource\Pages;
use App\Models\FeatureFlag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Config;

class FeatureFlagResource extends Resource
{
    protected static ?string $model = FeatureFlag::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    // Custom label
    protected static ?string $navigationLabel = 'Feature Flags';

    protected static ?string $slug = 'features'; // Match the test URL /admin/features

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function ($rule, $get) {
                            return $rule->where('scope', $get('scope'));
                        }
                    )
                    ->datalist(array_keys(config('features', []))),
                Forms\Components\Select::make('scope')
                    ->options(function () {
                        // Basic options for now, can be expanded to search users dynamically if needed
                        return [
                            '__laravel_null' => 'Global',
                        ] + \App\Models\User::pluck('name', 'id')->mapWithKeys(fn ($name, $id) => ["App\\Models\\User|{$id}" => "{$name} (ID: {$id})"])->toArray();
                    })
                    ->searchable()
                    ->default('__laravel_null')
                    ->helperText('Only Global scope is effective. User scope is not implemented in API or application logic (see TASK-FF-001).')
                    // Force name to re-validate when scope changes
                    ->live()
                    ->required(),
                Forms\Components\Toggle::make('value')
                    ->label('Enabled'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    // Display the "Nice Name" from config if available
                    ->formatStateUsing(fn ($state) => config("features.{$state}.name") ?? $state)
                    ->description(fn ($record) => config("features.{$record->name}.description")),

                Tables\Columns\TextColumn::make('scope')
                    ->label('Scope')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        '__laravel_null', '__global__' => 'Global',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        '__laravel_null', '__global__' => 'primary',
                        default => 'warning',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('value')
                    ->label('Effective State')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(function ($record) {
                        $force = config("features.{$record->name}.force");
                        if ($force !== null && $force !== '') {
                            $val = filter_var($force, FILTER_VALIDATE_BOOL);

                            return ($val ? 'Enabled' : 'Disabled').' (Locked)';
                        }

                        return $record->value ? 'Enabled' : 'Disabled';
                    })
                    ->icon(function ($record) {
                        $force = config("features.{$record->name}.force");

                        return ($force !== null && $force !== '') ? 'heroicon-m-lock-closed' : null;
                    }),

                Tables\Columns\ToggleColumn::make('value')
                    ->label('DB Toggle')
                    ->disabled(function ($record) {
                        $force = config("features.{$record->name}.force");

                        return $force !== null && $force !== '';
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatureFlags::route('/'),
            'create' => Pages\CreateFeatureFlag::route('/create'),
            'edit' => Pages\EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
