<?php

namespace App\Console\Commands;

use App\Services\PeriodTransitionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('periods:transition')]
#[Description('Transition academic periods (and their parent semesters/years) between upcoming, active, and closed.')]
class TransitionPeriods extends Command
{
    public function handle(PeriodTransitionService $service): void
    {
        $service->run();

        $this->info('Period transitions applied.');
    }
}
