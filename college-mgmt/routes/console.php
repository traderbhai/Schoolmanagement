<?php

use App\Console\Commands\MarkOverdueFeedemands;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('admission:deadline-reminders')->dailyAt('08:00');
Schedule::command('admission:followup-reminders')->dailyAt('08:30');
Schedule::command('admission:close-expired-windows')->hourly();
Schedule::command('admission:run-automations')->everyFifteenMinutes();
Schedule::command('accounts:mark-overdue-demands')->dailyAt('00:30');
Schedule::command('fees:apply-late-fees')->dailyAt('01:00');
Schedule::command('library:apply-fines')->dailyAt('02:00');
