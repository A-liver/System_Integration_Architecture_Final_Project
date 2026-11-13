<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\StubPaymentGateway;
use App\Services\Records\LegacyStudentRecords;
use App\Services\Records\StudentRecords;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StudentRecords::class, LegacyStudentRecords::class);

        $this->app->bind(PaymentGateway::class, fn ($app) => config('registration.payment_driver') === 'stub'
            ? $app->make(StubPaymentGateway::class)
            : $app->make(PayPalGateway::class));
    }
}
