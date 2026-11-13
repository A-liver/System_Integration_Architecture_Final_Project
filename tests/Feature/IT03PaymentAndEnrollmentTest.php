<?php

namespace Tests\Feature;

use App\Models\EnrollmentUpdate;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentIntegration;
use App\Services\Payments\StubPaymentGateway;
use App\Services\Registration\EnrollmentRecovery;
use App\Services\Registration\RegistrationService;
use App\Services\SystemSettings;
use Illuminate\Support\Facades\DB;

class IT03PaymentAndEnrollmentTest extends IntegrationTestCase
{
    private function paymentFor(string $outcome = GatewayResult::COMPLETED): Payment
    {
        $registration = app(RegistrationService::class)->submit('2024-00001', [$this->course('GE 101')->id, $this->course('PE 101')->id]);
        $payment = app(PaymentIntegration::class)->start($registration);
        app(StubPaymentGateway::class)->script($payment->provider_order_id, $outcome);

        return $payment;
    }

    private function legacyRows(): int
    {
        return DB::connection('legacy')->table('ENRL_REC')->count();
    }

    public function test_verified_payment_updates_enrollment_in_legacy_format(): void
    {
        $payment = app(PaymentIntegration::class)->completeApproval($this->paymentFor());

        $this->assertSame(Payment::CAPTURED, $payment->status);
        $this->assertSame(Registration::ENROLLED, $payment->registration->fresh()->status);

        $row = DB::connection('legacy')->table('ENRL_REC')->where('CRS_CD', 'GE101')->first();
        $this->assertSame('202400001', $row->STU_NO);
        $this->assertSame('20262', $row->TERM_CD);
        $this->assertSame($payment->payment_reference, $row->PAY_REF);
    }

    public function test_legacy_outage_does_not_cause_another_charge_and_recovers(): void
    {
        $settings = app(SystemSettings::class);
        $stub = app(StubPaymentGateway::class);
        $recovery = app(EnrollmentRecovery::class);
        $integration = app(PaymentIntegration::class);

        $payment = $this->paymentFor();
        $settings->setLegacyOnline(false);

        $payment = $integration->completeApproval($payment);
        $reference = $payment->payment_reference;

        $this->assertNotNull($reference);
        $this->assertSame(Registration::ENROLLMENT_PENDING, $payment->registration->fresh()->status);
        $this->assertSame(EnrollmentUpdate::PENDING, EnrollmentUpdate::first()->status);
        $this->assertSame(0, $this->legacyRows());

        // A repeated browser return while still down must change nothing.
        $integration->completeApproval($payment);
        $this->assertSame(1, $stub->captureCalls($payment->provider_order_id));

        $settings->setLegacyOnline(true);
        $this->travel(2)->minutes();
        $this->assertSame(1, $recovery->retryDue());

        $this->assertSame(Registration::ENROLLED, $payment->registration->fresh()->status);
        $this->assertSame(2, $this->legacyRows());
        $this->assertSame($reference, EnrollmentUpdate::first()->payment_reference);
        $this->assertSame(1, Payment::whereNotNull('payment_reference')->count());
        $this->assertSame(1, $stub->captureCalls($payment->provider_order_id));
        $this->assertSame(1, Notice::where('student_number', '2024-00001')->count());
    }

    public function test_retry_limit_hands_over_to_staff_who_can_finish_it(): void
    {
        config(['registration.max_enrollment_attempts' => 2]);
        $payment = $this->paymentFor();
        app(SystemSettings::class)->setLegacyOnline(false);

        $payment = app(PaymentIntegration::class)->completeApproval($payment);
        $recovery = app(EnrollmentRecovery::class);

        $this->travel(5)->minutes();
        $recovery->retryDue();

        $update = EnrollmentUpdate::first();
        $this->assertSame(EnrollmentUpdate::NEEDS_STAFF, $update->status);
        $this->assertSame(Registration::NEEDS_STAFF, $payment->registration->fresh()->status);

        $this->assertSame(0, $recovery->retryDue(), 'Exhausted updates stay out of the automatic queue.');

        app(SystemSettings::class)->setLegacyOnline(true);
        $this->actingAs($this->staffUser())
            ->post(route('staff.retry', $update))
            ->assertRedirect();

        $this->assertSame(Registration::ENROLLED, $payment->registration->fresh()->status);
        $this->assertSame(1, app(StubPaymentGateway::class)->captureCalls($payment->provider_order_id));
    }

    public function test_declined_payment_keeps_seats_and_allows_a_new_attempt(): void
    {
        $payment = app(PaymentIntegration::class)->completeApproval($this->paymentFor(GatewayResult::DECLINED));

        $this->assertSame(Payment::DECLINED, $payment->status);
        $registration = $payment->registration->fresh();
        $this->assertSame(Registration::AWAITING_PAYMENT, $registration->status);

        $second = app(PaymentIntegration::class)->start($registration);
        $this->assertNotSame($payment->provider_order_id, $second->provider_order_id);
    }

    public function test_pending_payment_settles_on_status_check(): void
    {
        $integration = app(PaymentIntegration::class);
        $payment = $integration->completeApproval($this->paymentFor(GatewayResult::PENDING));

        $this->assertSame(Payment::PENDING, $payment->status);
        $this->assertSame(Registration::PAYMENT_PENDING, $payment->registration->fresh()->status);

        $payment = $integration->checkStatus($payment);

        $this->assertSame(Payment::CAPTURED, $payment->status);
        $this->assertSame(Registration::ENROLLED, $payment->registration->fresh()->status);
    }

    public function test_temporary_failure_retries_the_same_capture(): void
    {
        $integration = app(PaymentIntegration::class);
        $payment = $integration->completeApproval($this->paymentFor(GatewayResult::TEMPORARY_FAILURE));

        $this->assertSame(Payment::APPROVED, $payment->status);
        $this->assertNull($payment->payment_reference);

        $payment = $integration->checkStatus($payment);

        $this->assertSame(Payment::CAPTURED, $payment->status);
        $this->assertSame(1, Payment::count());
    }

    public function test_cancel_releases_seats_and_is_blocked_once_payment_is_in_flight(): void
    {
        $service = app(RegistrationService::class);
        $before = $this->course('GE 101')->seats_taken;

        $registration = $service->submit('2024-00001', [$this->course('GE 101')->id]);
        $this->assertSame($before + 1, $this->course('GE 101')->seats_taken);

        $service->cancel($registration);
        $this->assertSame($before, $this->course('GE 101')->seats_taken);
        $this->assertSame(Registration::CANCELLED, $registration->fresh()->status);

        $payment = $this->paymentFor(GatewayResult::PENDING);
        app(PaymentIntegration::class)->completeApproval($payment);

        $this->expectException(\App\Exceptions\RegistrationRejected::class);
        $service->cancel($payment->registration->fresh());
    }

    public function test_a_student_cannot_open_another_students_registration(): void
    {
        $registration = $this->paymentFor()->registration;

        $this->actingAs($this->studentUser('2024-00002'))
            ->get(route('registrations.show', $registration))
            ->assertForbidden();
    }

    public function test_portal_pages_render(): void
    {
        $registration = $this->paymentFor()->registration;

        $this->actingAs($this->studentUser())->get(route('courses.index'))->assertOk()->assertSee('Enrollment slip');
        $this->actingAs($this->studentUser())->get(route('registrations.index'))->assertOk()->assertSee($registration->reference);
        $this->actingAs($this->studentUser())->get(route('registrations.show', $registration))->assertOk();
        $this->actingAs($this->staffUser())->get(route('staff.reconciliation'))->assertOk();
        $this->actingAs($this->staffUser())->get(route('staff.logs'))->assertOk();
        $this->actingAs($this->studentUser())->get(route('staff.logs'))->assertForbidden();
    }
}
