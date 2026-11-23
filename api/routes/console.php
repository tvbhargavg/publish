<?php

use App\Services\TunnelAuthorizer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('tunnels:reap', function (TunnelAuthorizer $a) {
    $this->info('Closed '.$a->reapStale().' stale tunnel record(s).');
})->purpose('Close tunnel history rows that are no longer in the registry');

Schedule::command('tunnels:reap')->everyMinute()->withoutOverlapping();
