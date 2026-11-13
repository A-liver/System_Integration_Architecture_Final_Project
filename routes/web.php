<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\Staff\IntegrationLogController;
use App\Http\Controllers\Staff\ReconciliationController;
use App\Http\Controllers\StubPaymentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return match (true) {
        Auth::guest() => redirect()->route('login'),
        Auth::user()->isStaff() => redirect()->route('staff.reconciliation'),
        default => redirect()->route('courses.index'),
    };
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/courses', [CatalogController::class, 'index'])->name('courses.index');

    Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations.index');
    Route::post('/registrations', [RegistrationController::class, 'store'])->name('registrations.store');
    Route::get('/registrations/{registration}', [RegistrationController::class, 'show'])->name('registrations.show');
    Route::post('/registrations/{registration}/cancel', [RegistrationController::class, 'cancel'])->name('registrations.cancel');

    Route::post('/registrations/{registration}/payment', [PaymentController::class, 'start'])->name('payments.start');
    Route::get('/registrations/{registration}/payment/return', [PaymentController::class, 'returned'])->name('payments.return');
    Route::get('/registrations/{registration}/payment/cancelled', [PaymentController::class, 'cancelled'])->name('payments.cancelled');
    Route::post('/registrations/{registration}/payment/check', [PaymentController::class, 'check'])->name('payments.check');

    Route::get('/registrations/{registration}/payment/stub', [StubPaymentController::class, 'show'])->name('payments.stub');
    Route::post('/registrations/{registration}/payment/stub', [StubPaymentController::class, 'submit'])->name('payments.stub.submit');
});

Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation');
    Route::post('/reconciliation/run-due', [ReconciliationController::class, 'runDue'])->name('run-due');
    Route::post('/reconciliation/legacy', [ReconciliationController::class, 'toggleLegacy'])->name('legacy');
    Route::post('/reconciliation/{update}/retry', [ReconciliationController::class, 'retry'])->name('retry');
    Route::post('/reconciliation/{update}/reconcile', [ReconciliationController::class, 'reconcile'])->name('reconcile');
    Route::get('/integration-log', [IntegrationLogController::class, 'index'])->name('logs');
});
