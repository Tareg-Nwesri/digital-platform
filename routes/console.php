<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('posts:publish-due')->everyMinute()->withoutOverlapping();
Schedule::command('finance:ensure-periods')->daily();
Schedule::command('finance:verify')->dailyAt('02:30');

/*
 * Nightly backups of the database and uploads (code lives in git); old
 * backups are pruned first and the monitor mails the super admin when the
 * latest backup is missing or too old.
 */
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('07:00');

/*
 * Shared hosting has no long-running worker: the per-minute cron drains the
 * database queue instead.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
