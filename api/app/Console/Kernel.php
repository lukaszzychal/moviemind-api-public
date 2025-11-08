<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\PhpstanAutoFixCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

final class Kernel extends ConsoleKernel
{
    /**
     * @var class-string[]
     */
    protected $commands = [
        PhpstanAutoFixCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Define the application's command schedule here.
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
