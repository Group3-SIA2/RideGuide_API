<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*  schedules na naa: dailyAt('HH:MM'),
    hourlyAt(XX), everyMinute(), everyFiveMinutes(), 
    everyTenMinutes(), everyThirtyMinutes(), everyHour(), 
    daily(), weekly(), monthly(), yearly()
*/
Schedule::command('app:backup-database')->dailyAt('23:16');
