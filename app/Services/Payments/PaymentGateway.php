<?php

namespace App\Services\Payments;

use App\Models\Registration;

interface PaymentGateway
{
    public function name(): string;

    /**
     * @throws \App\Exceptions\PaymentGatewayException
     */
    public function createOrder(Registration $registration, string $requestId): GatewayOrder;

    /** Repeating a capture with the same request id must not charge twice. */
    public function capture(string $orderId, string $requestId): GatewayResult;

    /** Asks the provider for the current truth about an order. */
    public function verify(string $orderId): GatewayResult;
}
