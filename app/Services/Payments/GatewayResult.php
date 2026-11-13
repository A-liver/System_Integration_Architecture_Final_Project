<?php

namespace App\Services\Payments;

final readonly class GatewayResult
{
    public const COMPLETED = 'completed';

    public const PENDING = 'pending';

    public const DECLINED = 'declined';

    public const TEMPORARY_FAILURE = 'temporary_failure';

    public function __construct(
        public string $status,
        public ?string $captureId = null,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public ?string $message = null,
    ) {}
}
