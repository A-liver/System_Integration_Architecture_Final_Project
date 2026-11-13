<?php

namespace App\Services\Payments;

use App\Models\Registration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Test stub from the integration plan (section 2.2). Returns simulated
 * success, declined, pending, or temporary-failure responses.
 */
class StubPaymentGateway implements PaymentGateway
{
    public const OUTCOMES = [
        GatewayResult::COMPLETED => 'Payment succeeds',
        GatewayResult::DECLINED => 'Payment is declined',
        GatewayResult::PENDING => 'Payment stays pending until the next status check',
        GatewayResult::TEMPORARY_FAILURE => 'Provider has a temporary failure',
    ];

    public function name(): string
    {
        return 'stub';
    }

    public function createOrder(Registration $registration, string $requestId): GatewayOrder
    {
        $orderId = 'STUB-ORDER-'.Str::upper(Str::random(10));

        $this->put($orderId, [
            'outcome' => GatewayResult::COMPLETED,
            'amount' => $registration->total_cents,
            'currency' => $registration->currency,
            'capture_id' => null,
            'capture_calls' => 0,
        ]);

        return new GatewayOrder($orderId, route('payments.stub', ['registration' => $registration, 'token' => $orderId]));
    }

    public function script(string $orderId, string $outcome): void
    {
        $this->put($orderId, ['outcome' => $outcome] + $this->get($orderId));
    }

    public function captureCalls(string $orderId): int
    {
        return $this->get($orderId)['capture_calls'];
    }

    public function capture(string $orderId, string $requestId): GatewayResult
    {
        $order = $this->get($orderId);
        $order['capture_calls']++;

        // A temporary failure happens once, so a retry of the same capture can succeed.
        if ($order['outcome'] === GatewayResult::TEMPORARY_FAILURE) {
            $order['outcome'] = GatewayResult::COMPLETED;
            $this->put($orderId, $order);

            return new GatewayResult(GatewayResult::TEMPORARY_FAILURE, message: 'Stub provider timed out.');
        }

        if ($order['outcome'] === GatewayResult::COMPLETED) {
            $order['capture_id'] ??= 'STUB-CAPTURE-'.Str::upper(Str::random(10));
        }

        $this->put($orderId, $order);

        return $this->result($order);
    }

    public function verify(string $orderId): GatewayResult
    {
        $order = $this->get($orderId);

        if ($order['outcome'] === GatewayResult::PENDING) {
            $order['outcome'] = GatewayResult::COMPLETED;
            $order['capture_id'] = 'STUB-CAPTURE-'.Str::upper(Str::random(10));
            $this->put($orderId, $order);
        }

        return $this->result($order);
    }

    private function result(array $order): GatewayResult
    {
        return new GatewayResult($order['outcome'], $order['capture_id'], $order['amount'], $order['currency']);
    }

    private function get(string $orderId): array
    {
        return Cache::get("stub-order:{$orderId}") ?? throw new \RuntimeException("Unknown stub order {$orderId}");
    }

    private function put(string $orderId, array $order): void
    {
        Cache::put("stub-order:{$orderId}", $order, now()->addDay());
    }
}
