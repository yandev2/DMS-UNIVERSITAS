<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('documents:send-expiry-reminders')
    ->dailyAt('07:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('bulk-exports:clean-expired')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('system:run-scheduled-backup')
    ->everyMinute()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(60)
    ->onOneServer();
