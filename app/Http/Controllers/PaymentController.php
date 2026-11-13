<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Payments\PaymentIntegration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function start(Request $request, Registration $registration, PaymentIntegration $payments): RedirectResponse
    {
        $this->authorizeOwner($request, $registration);

        try {
            $payment = $payments->start($registration);
        } catch (PaymentGatewayException $e) {
            return back()->with('problems', [$e->getMessage()]);
        }

        return redirect()->away($payment->approval_url);
    }

    public function returned(Request $request, Registration $registration, PaymentIntegration $payments): RedirectResponse
    {
        $this->authorizeOwner($request, $registration);

        $payment = $registration->payments()->where('provider_order_id', (string) $request->query('token'))->firstOrFail();
        $payment = $payments->completeApproval($payment);

        return $this->toSlip($registration, $payment);
    }

    public function cancelled(Request $request, Registration $registration): RedirectResponse
    {
        $this->authorizeOwner($request, $registration);

        return redirect()->route('registrations.show', $registration)
            ->with('status', 'You left the payment page without paying. Your seats are still reserved.');
    }

    public function check(Request $request, Registration $registration, PaymentIntegration $payments): RedirectResponse
    {
        $this->authorizeOwner($request, $registration);

        $payment = $registration->payments()->whereIn('status', [Payment::APPROVED, Payment::PENDING])->latest('id')->firstOrFail();

        return $this->toSlip($registration, $payments->checkStatus($payment));
    }

    private function toSlip(Registration $registration, Payment $payment): RedirectResponse
    {
        $registration->refresh();
        $redirect = redirect()->route('registrations.show', $registration);

        return match (true) {
            $registration->status === Registration::ENROLLED => $redirect->with('status', 'Payment confirmed. You are enrolled.'),
            $registration->status === Registration::ENROLLMENT_PENDING => $redirect->with('status', 'Payment confirmed. Your enrollment will be recorded as soon as the student records system responds. You will not be charged again.'),
            $payment->status === Payment::DECLINED => $redirect->with('problems', ['The payment was declined. No charge was made. You can try again.']),
            $payment->status === Payment::PENDING => $redirect->with('status', 'Your payment is still processing. Check back here for the result.'),
            $payment->status === Payment::FAILED => $redirect->with('problems', ['We could not verify this payment. Contact the registrar before paying again.']),
            default => $redirect->with('problems', ['The payment provider had a temporary problem. Check the payment again; this will not start a new charge.']),
        };
    }

    private function authorizeOwner(Request $request, Registration $registration): void
    {
        abort_unless($registration->student_number === $request->user()->student_number, 403);
    }
}
