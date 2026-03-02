<?php

namespace App\Filament\Resources\PersonResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BiosRelationManager extends RelationManager
{
    protected static string $relationship = 'bios';

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
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
