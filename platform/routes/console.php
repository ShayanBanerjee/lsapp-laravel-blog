<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Registration spam accumulates quietly. Nightly, off-peak, and conservative
// about what counts as abandoned — see App\Console\Commands\PruneUnverifiedUsers.
Schedule::command('users:prune-unverified')->dailyAt('03:15');
