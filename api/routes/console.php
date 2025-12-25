<?php

use App\Jobs\ResetMonthlyUsageJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule: Reset monthly usage tracking (runs on the 1st day of each month at 00:00)
Schedule::job(new ResetMonthlyUsageJob)->monthlyOn(1, '00:00');
