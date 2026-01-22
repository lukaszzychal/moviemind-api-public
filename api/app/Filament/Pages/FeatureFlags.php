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
use Illuminate\Support\Facades\DB;
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
        // Use Feature::for('default') to ensure we check the default scope
        $this->form->fill(
            collect(config('pennant.metadata'))
                ->mapWithKeys(fn ($flag, $name) => [$name => (bool) Feature::for('default')->active($name)])
                ->all()
        );
    }

    public function form(Form $form): Form
    {
        $schema = [];
        $flags = config('pennant.metadata', []);

        // Get overridden flags for default scope only
        $overriddenFlags = DB::table('features')
            ->where('scope', '__laravel_null')
            ->pluck('name')
            ->toArray();

        // Group flags by category while preserving flag names as keys
        $flagsByCategory = collect($flags)
            ->mapToGroups(fn ($flag, $name) => [$flag['category'] => [$name => $flag]])
            ->map(fn ($group) => $group->collapse());

        foreach ($flagsByCategory as $category => $flags) {
            $fields = [];
            foreach ($flags as $name => $flag) {
                $isOverridden = in_array($name, $overriddenFlags);
                $fields[] = Toggle::make($name)
                    ->label($name)
                    ->helperText($flag['description'])
                    ->hint($isOverridden ? 'Overridden' : 'Default')
                    ->hintColor($isOverridden ? 'warning' : 'gray');
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
            // Use for() to ensure we're setting for default scope
            if ((bool) $state) {
                Feature::for('default')->activate($name);
            } else {
                Feature::for('default')->deactivate($name);
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
