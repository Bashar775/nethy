<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('backup:run')->daily()->at('17:20'); // Run backup daily at 1:00 AM
Schedule::command('backup:clean')->daily()->at('01:30'); // Run cleanup daily shortly after