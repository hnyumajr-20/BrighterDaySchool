<?php

namespace App\Console\Commands;

use App\Services\AttendanceWindowService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:transition')]
#[Description('Open today\'s staff check-in window if due, and auto-mark absentees once it closes.')]
class TransitionAttendanceWindows extends Command
{
    public function handle(AttendanceWindowService $service): void
    {
        $service->run();

        $this->info('Attendance window transitions applied.');
    }
}
