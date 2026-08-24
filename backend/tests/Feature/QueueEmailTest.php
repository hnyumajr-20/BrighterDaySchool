<?php

namespace Tests\Feature;

use App\Jobs\SendLoggedEmailJob;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_dispatches_job_without_blocking_the_request(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'queued@brighterday.test']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'queued@brighterday.test',
        ]);

        $response->assertOk();
        Queue::assertPushed(SendLoggedEmailJob::class);
    }

    public function test_job_sends_mail_and_marks_email_log_as_sent(): void
    {
        // Testing env uses the "array" mail driver — the job runs for real
        // (no network I/O) so this proves the full send + log-update path.
        $user = User::factory()->create();
        $emailLog = EmailLog::create([
            'user_id' => $user->id,
            'type' => 'password_reset',
            'status' => 'queued',
        ]);

        (new SendLoggedEmailJob(
            $emailLog->id,
            $user->email ?? 'test@brighterday.test',
            'Test subject',
            'Test body',
        ))->handle();

        $emailLog->refresh();
        $this->assertEquals('sent', $emailLog->status);
        $this->assertNotNull($emailLog->sent_at);
    }
}
