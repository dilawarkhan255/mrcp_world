<?php

use App\Services\CandidateStatsService;
use App\Services\KnowledgeTutorService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stats:refresh {--full : Recompute every question instead of those answered since the last run}', function (CandidateStatsService $stats) {
    $count = $stats->refresh((bool) $this->option('full'));
    $this->info("Candidate statistics refreshed for {$count} questions.");
    $facts = app(KnowledgeTutorService::class)->refreshDifficulty();
    $this->info("Knowledge Tutor difficulty updated for {$facts} facts.");
})->purpose('Refresh the candidate correct %, average time, subject averages and fact difficulty');

Schedule::command('stats:refresh')->everyTenMinutes()->withoutOverlapping(30);

Artisan::command('subscriptions:remind {--days=7 : Remind when access ends within this many days}', function (SubscriptionService $subscriptions) {
    $count = $subscriptions->remindExpiring((int) $this->option('days'));
    $this->info("Reminded {$count} students.");
})->purpose('Notify students whose subscription ends soon');

Schedule::command('subscriptions:remind')->dailyAt('09:00')->withoutOverlapping();
