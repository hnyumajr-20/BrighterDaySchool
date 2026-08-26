<?php

namespace App\Services\Payments;

final readonly class PaymentGatewayResult
{
    public function __construct(
        public bool $successful,
        public ?string $gatewayTransactionId = null,
        public ?string $message = null,
    ) {}
}
