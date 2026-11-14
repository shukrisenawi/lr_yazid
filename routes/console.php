<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('shop:expire-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('shop:reconcile-payments')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('vip:expire-memberships')->hourly()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
