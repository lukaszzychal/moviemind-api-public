<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Laravel\Pennant\Feature;

class FeatureFlags extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'System';

    protected static string $view = 'filament.pages.feature-flags';

    public ?array $data = [];

    public function mount(): void
    {
        $this->loadFlags();
    }

    public function loadFlags(): void
    {
        // Load flags from database overrides first, then fallback to config defaults
        // Use default scope (null) to match application code (Feature::active() without for())
        $this->form->fill(
            collect(config('pennant.metadata'))
                ->mapWithKeys(fn ($flag, $name) => [$name => (bool) Feature::active($name)])
                ->all()
        );
    }

    public function form(Form $form): Form
    {
        $schema = [];
        $flags = config('pennant.metadata', []);

        // Group flags by category while preserving flag names as keys
        $flagsByCategory = collect($flags)
            ->mapToGroups(fn ($flag, $name) => [$flag['category'] => [$name => $flag]])
            ->map(fn ($group) => $group->collapse());

        foreach ($flagsByCategory as $category => $flags) {
            $fields = [];
            foreach ($flags as $name => $flag) {
                $fields[] = Toggle::make($name)
                    ->label($name)
                    ->helperText($flag['description']);
            }
            $schema[] = Section::make(ucfirst(str_replace('_', ' ', $category)))
                ->schema($fields)
                ->columns(2);
        }

        return $form->schema($schema)->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->submit('save'),
            Action::make('resetAll')
                ->label('Reset All to Default')
                ->color('danger')
                ->requiresConfirmation()
                ->action('resetAllFlags'),
        ];
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $name => $state) {
            // Use default scope (null) to match application code (Feature::active() without for())
            if ((bool) $state) {
                Feature::activate($name);
            } else {
                Feature::deactivate($name);
            }
        }
        Notification::make()->title('Feature flags updated successfully.')->success()->send();
        $this->loadFlags();
    }

    public function resetAllFlags(): void
    {
        Artisan::call('pennant:purge');
        Notification::make()->title('All flags have been reset to their default values.')->success()->send();
        $this->loadFlags();
    }
}
