<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Services\Payments\StubPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StubPaymentController extends Controller
{
    public function show(Request $request, Registration $registration): View
    {
        $this->guard($request, $registration);

        return view('payments.stub', [
            'registration' => $registration,
            'token' => (string) $request->query('token'),
            'outcomes' => StubPaymentGateway::OUTCOMES,
        ]);
    }

    public function submit(Request $request, Registration $registration, StubPaymentGateway $stub): RedirectResponse
    {
        $this->guard($request, $registration);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'outcome' => ['required', Rule::in(array_keys(StubPaymentGateway::OUTCOMES))],
        ]);

        $order = $registration->payments()->where('provider_order_id', $data['token'])->firstOrFail();
        $stub->script($order->provider_order_id, $data['outcome']);

        return redirect()->route('payments.return', ['registration' => $registration, 'token' => $order->provider_order_id]);
    }

    private function guard(Request $request, Registration $registration): void
    {
        abort_unless(config('registration.payment_driver') === 'stub', 404);
        abort_unless($registration->student_number === $request->user()->student_number, 403);
    }
}
