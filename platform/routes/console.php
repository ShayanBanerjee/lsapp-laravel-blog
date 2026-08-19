<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Unconfirmed accounts expire after a month, but only when they are inert —
 * see PruneUnverifiedAccounts for what counts as inert and why.
 */
Schedule::command('inkfathom:prune-unverified')->daily();
