<?php

namespace App\Services\Payments;

final readonly class GatewayOrder
{
    public function __construct(public string $orderId, public string $approvalUrl) {}
}
