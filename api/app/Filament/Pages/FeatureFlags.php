<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class FeatureFlags extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static string $view = 'filament.pages.feature-flags';

    protected static ?string $navigationLabel = 'Feature Flags';

    protected static ?string $title = 'Feature Flags Management';

    protected static ?int $navigationSort = 10;

    public array $flags = [];

    public function mount(): void
    {
        $this->loadFlags();
    }

    protected function loadFlags(): void
    {
        $allFlags = config('pennant.flags', []);

        foreach ($allFlags as $key => $config) {
            $this->flags[$key] = $config['default'] ?? false;
        }
    }

    public function form(Form $form): Form
    {
        $allFlags = config('pennant.flags', []);
        $schema = [];

        $categories = [
            'core_ai' => 'Core AI Features',
            'ai_quality' => 'AI Quality & Safety',
            'localization' => 'Localization',
            'experiments' => 'Experimental Features',
            'admin' => 'Admin Features',
            'api' => 'API Features',
            'caching' => 'Caching',
            'webhooks' => 'Webhooks',
            'public' => 'Public Features',
            'security' => 'Security',
        ];

        foreach ($categories as $categoryKey => $categoryLabel) {
            $categoryFlags = array_filter($allFlags, fn ($flag) => ($flag['category'] ?? '') === $categoryKey);

            if (empty($categoryFlags)) {
                continue;
            }

            $toggles = [];
            foreach ($categoryFlags as $key => $config) {
                $toggles[] = Toggle::make("flags.{$key}")
                    ->label($this->formatFlagName($key))
                    ->helperText($config['description'] ?? '')
                    ->disabled(! ($config['togglable'] ?? true))
                    ->inline(false);
            }

            $schema[] = Section::make($categoryLabel)
                ->schema($toggles)
                ->collapsible()
                ->columns(2);
        }

        return $form
            ->schema($schema)
            ->statePath('flags');
    }

    protected function formatFlagName(string $key): string
    {
        return str_replace('_', ' ', ucwords($key, '_'));
    }

    public function save(): void
    {
        Notification::make()
            ->title('Feature flags updated')
            ->success()
            ->body('Note: Changes are in-memory only. To persist, update config/pennant.php')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save Changes')
                ->action('save')
                ->color('primary'),
        ];
    }
}
