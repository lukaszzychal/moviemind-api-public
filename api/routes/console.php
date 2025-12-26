<?php

use App\Jobs\GenerateAiMetricsReportJob;
use App\Jobs\ResetMonthlyUsageJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule: Reset monthly usage tracking (runs on the 1st day of each month at 00:00)
Schedule::job(new ResetMonthlyUsageJob)->monthlyOn(1, '00:00');

// Schedule: Generate AI metrics reports
// Daily report (runs every day at 02:00)
Schedule::job(new GenerateAiMetricsReportJob('daily'))->dailyAt('02:00');

// Weekly report (runs every Monday at 03:00)
Schedule::job(new GenerateAiMetricsReportJob('weekly'))->weeklyOn(1, '03:00');

// Monthly report (runs on the 1st day of each month at 04:00)
Schedule::job(new GenerateAiMetricsReportJob('monthly'))->monthlyOn(1, '04:00');
