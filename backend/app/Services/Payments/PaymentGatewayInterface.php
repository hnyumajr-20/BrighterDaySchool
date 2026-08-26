<?php

namespace App\Services\Payments;

interface PaymentGatewayInterface
{
    /**
     * Charge a mobile money account for an invoice. $provider is the
     * invoice's payment_method ('orange_money' or 'lonestar_mtn'), $reference
     * is the invoice_no so the gateway call can be traced back to it.
     */
    public function charge(string $provider, string $phoneNumber, int $amountCents, string $reference): PaymentGatewayResult;
}
