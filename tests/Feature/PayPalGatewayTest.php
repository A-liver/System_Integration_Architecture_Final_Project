<?php

namespace Tests\Feature;

use App\Exceptions\PaymentGatewayException;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PayPalGateway;
use App\Services\Registration\RegistrationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PayPalGatewayTest extends IntegrationTestCase
{
    private function configure(): void
    {
        config([
            'services.paypal.client_id' => 'test-id',
            'services.paypal.client_secret' => 'test-secret',
            'services.paypal.base_url' => 'https://paypal.test',
        ]);
    }

    private function token(): array
    {
        return ['paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'abc', 'expires_in' => 3600])];
    }

    public function test_missing_credentials_give_a_clear_error(): void
    {
        config(['services.paypal.client_id' => null, 'services.paypal.client_secret' => null]);
        $registration = app(RegistrationService::class)->submit('2024-00001', [$this->course('GE 101')->id]);

        $this->expectException(PaymentGatewayException::class);
        $this->expectExceptionMessage('credentials are not configured');
        app(PayPalGateway::class)->createOrder($registration, 'REQ-1');
    }

    public function test_create_order_sends_amount_and_returns_approval_link(): void
    {
        $this->configure();
        Http::fake($this->token() + ['paypal.test/v2/checkout/orders' => Http::response([
            'id' => 'ORDER1',
            'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve/ORDER1']],
        ], 200)]);
        $registration = app(RegistrationService::class)->submit('2024-00001', [$this->course('GE 101')->id]);

        $order = app(PayPalGateway::class)->createOrder($registration, 'REQ-1');

        $this->assertSame('ORDER1', $order->orderId);
        $this->assertSame('https://paypal.test/approve/ORDER1', $order->approvalUrl);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/checkout/orders')
            && $r->hasHeader('PayPal-Request-Id', 'REQ-1')
            && $r['purchase_units'][0]['amount']['value'] === '7000.00'
            && $r['purchase_units'][0]['amount']['currency_code'] === 'PHP');
    }

    public function test_completed_capture_maps_reference_and_amount(): void
    {
        $this->configure();
        Http::fake($this->token() + ['paypal.test/v2/checkout/orders/ORDER1/capture' => Http::response([
            'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP1', 'status' => 'COMPLETED', 'amount' => ['value' => '7000.00', 'currency_code' => 'PHP']]]]]],
        ], 201)]);

        $result = app(PayPalGateway::class)->capture('ORDER1', 'capture-1');

        $this->assertSame(GatewayResult::COMPLETED, $result->status);
        $this->assertSame('CAP1', $result->captureId);
        $this->assertSame(700000, $result->amountCents);
    }

    public function test_declined_instrument_is_reported_as_declined(): void
    {
        $this->configure();
        Http::fake($this->token() + ['paypal.test/v2/checkout/orders/ORDER1/capture' => Http::response(['details' => [['issue' => 'INSTRUMENT_DECLINED']]], 422)]);

        $this->assertSame(GatewayResult::DECLINED, app(PayPalGateway::class)->capture('ORDER1', 'capture-1')->status);
    }

    public function test_already_captured_order_reads_the_existing_capture(): void
    {
        $this->configure();
        Http::fake($this->token() + [
            'paypal.test/v2/checkout/orders/ORDER1/capture' => Http::response(['details' => [['issue' => 'ORDER_ALREADY_CAPTURED']]], 422),
            'paypal.test/v2/checkout/orders/ORDER1' => Http::response([
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP1', 'status' => 'COMPLETED', 'amount' => ['value' => '10.00', 'currency_code' => 'PHP']]]]]],
            ]),
        ]);

        $this->assertSame('CAP1', app(PayPalGateway::class)->capture('ORDER1', 'capture-1')->captureId);
    }

    public function test_timeout_is_a_temporary_failure_not_a_hang(): void
    {
        $this->configure();
        Http::fake($this->token() + ['paypal.test/v2/checkout/orders/ORDER1/capture' => fn () => throw new ConnectionException('timed out')]);

        $this->assertSame(GatewayResult::TEMPORARY_FAILURE, app(PayPalGateway::class)->capture('ORDER1', 'capture-1')->status);
    }
}
