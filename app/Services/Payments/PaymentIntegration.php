<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;
use App\Models\EnrollmentUpdate;
use App\Models\IntegrationLog;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Registration\EnrollmentRecovery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Application co-operation middleware: ties the verified payment result to
 * the enrollment update, and guarantees one confirmed reference per payment.
 */
class PaymentIntegration
{
    public function __construct(
        private PaymentGateway $gateway,
        private EnrollmentRecovery $recovery,
    ) {}

    /** @throws PaymentGatewayException */
    public function start(Registration $registration): Payment
    {
        if ($registration->status !== Registration::AWAITING_PAYMENT) {
            throw new PaymentGatewayException('This registration is not waiting for payment.');
        }

        $open = $registration->payments()->where('status', Payment::CREATED)->latest('id')->first();
        if ($open) {
            return $open;
        }

        $attempt = $registration->payments()->count() + 1;

        try {
            $order = $this->gateway->createOrder($registration, "{$registration->reference}-{$attempt}");
        } catch (PaymentGatewayException $e) {
            IntegrationLog::record('payment', 'create_order', 'failed', $e->getMessage(), $registration->reference);
            throw $e;
        }

        IntegrationLog::record('payment', 'create_order', 'ok', $this->gateway->name(), $registration->reference);

        return $registration->payments()->create([
            'provider' => $this->gateway->name(),
            'provider_order_id' => $order->orderId,
            'approval_url' => $order->approvalUrl,
            'status' => Payment::CREATED,
            'amount_cents' => $registration->total_cents,
            'currency' => $registration->currency,
        ]);
    }

    /** The student approved at the provider. Capture, verify, then enroll. */
    public function completeApproval(Payment $payment): Payment
    {
        return $this->locked($payment, function () use ($payment) {
            $payment->refresh();
            $registration = $payment->registration;

            if (! in_array($payment->status, [Payment::CREATED, Payment::APPROVED], true) || $registration->status === Registration::CANCELLED) {
                return $payment;
            }

            $payment->update(['status' => Payment::APPROVED]);
            $registration->update(['status' => Registration::PAYMENT_PENDING]);

            $result = $this->gateway->capture($payment->provider_order_id, "capture-{$payment->id}");
            IntegrationLog::record('payment', 'capture', $result->status, $result->message, $registration->reference);

            if ($result->status === GatewayResult::COMPLETED) {
                $result = $this->gateway->verify($payment->provider_order_id);
                IntegrationLog::record('payment', 'verify', $result->status, $result->message, $registration->reference);
            }

            return $this->apply($payment, $result);
        });
    }

    /** Re-reads the provider for a payment that has not settled. Never starts a new charge. */
    public function checkStatus(Payment $payment): Payment
    {
        if ($payment->status === Payment::APPROVED) {
            return $this->completeApproval($payment);
        }

        return $this->locked($payment, function () use ($payment) {
            $payment->refresh();

            if ($payment->status !== Payment::PENDING) {
                return $payment;
            }

            $result = $this->gateway->verify($payment->provider_order_id);
            IntegrationLog::record('payment', 'verify', $result->status, $result->message, $payment->registration->reference);

            return $this->apply($payment, $result);
        });
    }

    private function apply(Payment $payment, GatewayResult $result): Payment
    {
        $registration = $payment->registration;

        switch ($result->status) {
            case GatewayResult::COMPLETED:
                if ($result->captureId === null || $result->amountCents !== $payment->amount_cents || $result->currency !== $payment->currency) {
                    $payment->update(['status' => Payment::FAILED, 'last_message' => 'Verified amount did not match the registration.']);
                    IntegrationLog::record('payment', 'verify', 'mismatch', 'Amount or currency differs from the registration', $registration->reference);
                    break;
                }
                $this->confirm($payment, $result->captureId);
                break;

            case GatewayResult::PENDING:
                $payment->update(['status' => Payment::PENDING, 'last_message' => $result->message]);
                break;

            case GatewayResult::DECLINED:
                $payment->update(['status' => Payment::DECLINED, 'last_message' => $result->message ?? 'The payment was declined.']);
                $registration->update(['status' => Registration::AWAITING_PAYMENT]);
                break;

            default:
                $payment->update(['last_message' => $result->message ?? 'The payment provider had a temporary problem.']);
        }

        return $payment->refresh();
    }

    private function confirm(Payment $payment, string $reference): void
    {
        $update = DB::transaction(function () use ($payment, $reference) {
            $payment->update([
                'status' => Payment::CAPTURED,
                'payment_reference' => $reference,
                'confirmed_at' => now(),
                'last_message' => null,
            ]);
            $payment->registration->update(['status' => Registration::ENROLLMENT_PENDING]);

            return EnrollmentUpdate::firstOrCreate(
                ['registration_id' => $payment->registration_id],
                ['payment_reference' => $reference, 'status' => EnrollmentUpdate::PENDING],
            );
        });

        $this->recovery->attempt($update);
    }

    private function locked(Payment $payment, callable $callback): Payment
    {
        return Cache::lock("payment:{$payment->id}", 30)->block(15, $callback);
    }
}
