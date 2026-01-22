<?php

namespace App\Console\Commands;

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class PrepareE2ETestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:prepare-e2e {--seed : Whether to seed the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare the environment for E2E tests (seed admin user, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! App::environment(['local', 'testing'])) {
            $this->error('This command can only be run in local or testing environments.');

            return 1;
        }

        $this->info('Preparing environment for E2E tests...');

        // Always ensure admin user exists
        $this->info('Seeding admin user...');
        $this->call('db:seed', ['--class' => AdminUserSeeder::class]);

        // Optionally run full seeder
        if ($this->option('seed')) {
            $this->info('Running full database seeder...');
            $this->call('db:seed', ['--class' => DatabaseSeeder::class]);
        }

        $this->info('Environment prepared successfully.');

        return 0;
    }
}
