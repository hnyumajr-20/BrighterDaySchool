<?php

namespace App\Services\Payments;

use App\Models\Invoice;

/**
 * Stands in for the real Orange Money / Lonestar MTN gateway APIs. Every
 * charge succeeds and returns a fake-but-uniquely-formatted transaction id —
 * swap the binding in AppServiceProvider for a real implementation of
 * PaymentGatewayInterface when live credentials are available; nothing in
 * InvoiceController needs to change.
 */
class SimulatedMobileMoneyGateway implements PaymentGatewayInterface
{
    private const PREFIXES = [
        'orange_money' => 'OM',
        'lonestar_mtn' => 'MTN',
    ];

    public function charge(string $provider, string $phoneNumber, int $amountCents, string $reference): PaymentGatewayResult
    {
        return new PaymentGatewayResult(
            successful: true,
            gatewayTransactionId: $this->generateUniqueTransactionId($provider),
        );
    }

    private function generateUniqueTransactionId(string $provider): string
    {
        $prefix = self::PREFIXES[$provider] ?? 'MM';

        do {
            $candidate = $prefix.'-'.now()->year.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Invoice::where('gateway_transaction_id', $candidate)->exists());

        return $candidate;
    }
}
