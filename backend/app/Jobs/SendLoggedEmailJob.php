<?php

namespace App\Jobs;

use App\Models\EmailLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendLoggedEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $emailLogId,
        public string $toEmail,
        public string $subject,
        public string $body,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
    ) {}

    public function handle(): void
    {
        Mail::raw($this->body, function ($message) {
            $message->to($this->toEmail)->subject($this->subject);

            if ($this->attachmentPath && Storage::disk('local')->exists($this->attachmentPath)) {
                $message->attachData(
                    Storage::disk('local')->get($this->attachmentPath),
                    $this->attachmentName ?? basename($this->attachmentPath),
                    ['mime' => 'application/pdf'],
                );
            }
        });

        EmailLog::whereKey($this->emailLogId)->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        EmailLog::whereKey($this->emailLogId)->update([
            'status' => 'failed',
        ]);
    }
}
