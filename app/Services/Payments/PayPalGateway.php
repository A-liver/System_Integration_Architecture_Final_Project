<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;
use App\Models\Registration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PayPalGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paypal';
    }

    public function createOrder(Registration $registration, string $requestId): GatewayOrder
    {
        try {
            $response = $this->client()
                ->withHeaders(['PayPal-Request-Id' => $requestId])
                ->post('/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => $registration->reference,
                        'invoice_id' => $requestId,
                        'description' => 'Enrollment '.$registration->reference,
                        'amount' => [
                            'currency_code' => $registration->currency,
                            'value' => number_format($registration->total_cents / 100, 2, '.', ''),
                        ],
                    ]],
                    'payment_source' => ['paypal' => ['experience_context' => [
                        'user_action' => 'PAY_NOW',
                        'shipping_preference' => 'NO_SHIPPING',
                        'return_url' => route('payments.return', $registration),
                        'cancel_url' => route('payments.cancelled', $registration),
                    ]]],
                ]);
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException('PayPal did not respond. Try again in a moment.', previous: $e);
        }

        if (! $response->successful()) {
            throw new PaymentGatewayException('PayPal could not start this payment. Try again in a moment.');
        }

        $link = collect($response->json('links'))->first(fn ($l) => in_array($l['rel'] ?? '', ['payer-action', 'approve'], true));

        if (! $link) {
            throw new PaymentGatewayException('PayPal did not return an approval link.');
        }

        return new GatewayOrder($response->json('id'), $link['href']);
    }

    public function capture(string $orderId, string $requestId): GatewayResult
    {
        try {
            $response = $this->client()
                ->withHeaders(['PayPal-Request-Id' => $requestId])
                ->withBody('{}', 'application/json')
                ->post("/v2/checkout/orders/{$orderId}/capture");
        } catch (ConnectionException|PaymentGatewayException) {
            return new GatewayResult(GatewayResult::TEMPORARY_FAILURE, message: 'PayPal did not respond.');
        }

        if ($response->status() === 422) {
            return $this->issue($response) === 'ORDER_ALREADY_CAPTURED'
                ? $this->verify($orderId)
                : new GatewayResult(GatewayResult::DECLINED, message: 'PayPal declined this payment.');
        }

        if (! $response->successful()) {
            return new GatewayResult(GatewayResult::TEMPORARY_FAILURE, message: 'PayPal returned an error.');
        }

        return $this->fromOrder($response->json());
    }

    public function verify(string $orderId): GatewayResult
    {
        try {
            $response = $this->client()->get("/v2/checkout/orders/{$orderId}");
        } catch (ConnectionException|PaymentGatewayException) {
            return new GatewayResult(GatewayResult::TEMPORARY_FAILURE, message: 'PayPal did not respond.');
        }

        if (! $response->successful()) {
            return new GatewayResult(GatewayResult::TEMPORARY_FAILURE, message: 'PayPal returned an error.');
        }

        return $this->fromOrder($response->json());
    }

    private function fromOrder(array $order): GatewayResult
    {
        $capture = data_get($order, 'purchase_units.0.payments.captures.0');

        if ($capture === null) {
            return new GatewayResult(GatewayResult::PENDING, message: 'PayPal has not captured this order yet.');
        }

        $status = match ($capture['status'] ?? '') {
            'COMPLETED' => GatewayResult::COMPLETED,
            'PENDING' => GatewayResult::PENDING,
            default => GatewayResult::DECLINED,
        };

        return new GatewayResult(
            $status,
            $capture['id'] ?? null,
            (int) round(((float) data_get($capture, 'amount.value', 0)) * 100),
            data_get($capture, 'amount.currency_code'),
        );
    }

    private function issue(Response $response): ?string
    {
        return $response->json('details.0.issue');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.paypal.base_url'))
            ->timeout(config('services.paypal.timeout'))
            ->connectTimeout(5)
            ->acceptJson()
            ->withToken($this->accessToken());
    }

    private function accessToken(): string
    {
        $id = config('services.paypal.client_id');
        $secret = config('services.paypal.client_secret');

        if (! $id || ! $secret) {
            throw new PaymentGatewayException('PayPal sandbox credentials are not configured yet. The registrar must set them before payments can run.');
        }

        $token = Cache::get('paypal.access_token');
        if ($token) {
            return $token;
        }

        $response = Http::baseUrl(config('services.paypal.base_url'))
            ->timeout(config('services.paypal.timeout'))
            ->connectTimeout(5)
            ->asForm()
            ->withBasicAuth($id, $secret)
            ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (! $response->successful()) {
            throw new PaymentGatewayException('PayPal rejected the sandbox credentials or is unavailable.');
        }

        Cache::put('paypal.access_token', $response->json('access_token'), max(60, $response->json('expires_in') - 60));

        return $response->json('access_token');
    }
}
