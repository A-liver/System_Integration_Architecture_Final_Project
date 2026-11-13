<?php

use App\Services\Registration\EnrollmentRecovery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('enrollment:retry-due', function (EnrollmentRecovery $recovery) {
    $this->info('Attempted '.$recovery->retryDue().' pending enrollment update(s).');
})->purpose('Retry paid enrollments that could not be written to the legacy records system');

Schedule::command('enrollment:retry-due')->everyMinute()->withoutOverlapping();
