<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApplicationFeedbackResource\Pages;
use App\Models\ApplicationFeedback;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApplicationFeedbackResource extends Resource
{
    protected static ?string $model = ApplicationFeedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Application feedback';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?string $modelLabel = 'Feedback';

    protected static ?string $pluralModelLabel = 'Application feedback';

    protected static ?string $slug = 'application-feedback';

    /** Submissions are via public API only; no create from panel. */
    protected static bool $shouldRegisterNavigation = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Feedback (anonymous, no personal data)')
                    ->schema([
                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->disabled()
                            ->rows(5)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('category')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                ApplicationFeedback::STATUS_PENDING => 'Pending',
                                ApplicationFeedback::STATUS_READ => 'Read',
                                ApplicationFeedback::STATUS_ARCHIVED => 'Archived',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('message')
                    ->limit(60)
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ApplicationFeedback::STATUS_PENDING => 'warning',
                        ApplicationFeedback::STATUS_READ => 'success',
                        ApplicationFeedback::STATUS_ARCHIVED => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplicationFeedback::route('/'),
            'view' => Pages\ViewApplicationFeedback::route('/{record}'),
            'edit' => Pages\EditApplicationFeedback::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
