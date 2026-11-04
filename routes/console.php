<?php

use App\Services\CandidateStatsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stats:refresh {--full : Recompute every question instead of those answered since the last run}', function (CandidateStatsService $stats) {
    $count = $stats->refresh((bool) $this->option('full'));
    $this->info("Candidate statistics refreshed for {$count} questions.");
})->purpose('Refresh the candidate correct %, average time and subject averages');

Schedule::command('stats:refresh')->everyTenMinutes()->withoutOverlapping(30);
