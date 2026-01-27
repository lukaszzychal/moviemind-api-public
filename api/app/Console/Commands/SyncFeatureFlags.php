<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FeatureFlag;
use Illuminate\Console\Command;
use Laravel\Pennant\Feature;

class SyncFeatureFlags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'features:sync 
                            {--prune : Remove flags from database that are no longer in config}
                            {--force-update : Reset existing flags to their config defaults (Dangerous)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize feature flags from config to database to ensure they appear in UI';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configFlags = config('features', []);
        $configNames = array_keys($configFlags);

        $this->info('Starting feature flag synchronization...');

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'pruned' => 0,
        ];

        // 1. Sync Config -> DB
        foreach ($configFlags as $name => $metadata) {
            $exists = FeatureFlag::where('name', $name)->exists();

            if (! $exists) {
                // Feature missing from DB - Create it
                // We use Pennant to activate/deactivate, which creates the record
                $defaultValue = $metadata['default'] ?? false;

                if ($defaultValue) {
                    Feature::activate($name);
                } else {
                    Feature::deactivate($name);
                }

                $this->info("Created flag: {$name} (Default: ".($defaultValue ? 'TRUE' : 'FALSE').')');
                $stats['created']++;
            } else {
                // Feature exists
                if ($this->option('force-update')) {
                    $defaultValue = $metadata['default'] ?? false;
                    if ($defaultValue) {
                        Feature::activate($name);
                    } else {
                        Feature::deactivate($name);
                    }
                    $this->info("Reset flag: {$name}");
                    $stats['updated']++;
                } else {
                    $this->output->write('.', false);
                    $stats['skipped']++;
                }
            }
        }

        $this->newLine();

        // 2. Prune obsolete flags (if requested)
        if ($this->option('prune')) {
            $dbFlags = FeatureFlag::pluck('name')->toArray();
            $toPrune = array_diff($dbFlags, $configNames);

            if (count($toPrune) > 0) {
                $this->warn('Pruning obsolete flags:');
                foreach ($toPrune as $name) {
                    Feature::forget($name);
                    // Double check if record is gone, usually forget removes it for some drivers
                    // For Database driver, forget deletes the row.
                    $this->line("- {$name}");
                    $stats['pruned']++;
                }
            } else {
                $this->info('No obsolete flags to prune.');
            }
        }

        $this->table(
            ['Action', 'Count'],
            [
                ['Created', $stats['created']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
                ['Pruned', $stats['pruned']],
            ]
        );

        return Command::SUCCESS;
    }
}
