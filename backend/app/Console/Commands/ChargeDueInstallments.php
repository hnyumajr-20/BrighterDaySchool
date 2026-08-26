<?php

namespace App\Console\Commands;

use App\Services\ClassFeeInstallmentChargeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('installments:charge')]
#[Description('Auto-charge any approved student not yet billed for an installment whose due date has arrived.')]
class ChargeDueInstallments extends Command
{
    public function handle(ClassFeeInstallmentChargeService $service): void
    {
        $service->run();

        $this->info('Due installments charged.');
    }
}
