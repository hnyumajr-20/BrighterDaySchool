<?php

namespace Tests\Feature;

use App\Jobs\SendLoggedEmailJob;
use App\Models\AcademicYear;
use App\Models\ClassFeeInstallment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(string $role): string
    {
        return User::factory()->role($role)->create()->createToken('api')->plainTextToken;
    }

    private function makeParent(): Guardian
    {
        return Guardian::create(['full_name' => 'Parent Guardian', 'phone' => '077'.random_int(1000000, 9999999)]);
    }

    private function makeClass(): SchoolClass
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);

        return SchoolClass::create([
            'name' => 'JSS1', 'arm' => 'A', 'fee_amount_cents' => 4500000, 'academic_year_id' => $year->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validIntakePayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Comfort Johnson',
            'dob' => '2012-03-10',
            'gender' => 'female',
            'email' => 'comfort.johnson@brighterday.test',
            'contact' => '0880001111',
            'address' => 'Paynesville, Monrovia',
            'parent_id' => $this->makeParent()->id,
            'class_id' => $this->makeClass()->id,
            'is_transfer_student' => false,
        ], $overrides);
    }

    public function test_registrar_can_submit_a_student_intake_as_pending(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('registrar'))
            ->post('/api/v1/students', $this->validIntakePayload());

        $response->assertCreated()->assertJsonPath('status', 'pending');
        $this->assertDatabaseHas('students', ['full_name' => 'Comfort Johnson', 'status' => 'pending']);

        $student = Student::where('full_name', 'Comfort Johnson')->firstOrFail();
        $this->assertNull($student->user_id);
        $this->assertNull($student->admission_no);
    }

    public function test_transfer_student_requires_a_transcript(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('admin'))
            ->post('/api/v1/students', $this->validIntakePayload([
                'email' => 'transfer.missing@brighterday.test',
                'is_transfer_student' => true,
            ]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['transcript']);
    }

    public function test_transfer_student_intake_succeeds_with_transcript(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('admin'))
            ->post('/api/v1/students', $this->validIntakePayload([
                'email' => 'transfer.ok@brighterday.test',
                'is_transfer_student' => true,
                'transcript' => UploadedFile::fake()->create('transcript.pdf', 400, 'application/pdf'),
                'photo' => UploadedFile::fake()->image('photo.jpg')->size(300),
            ]));

        $response->assertCreated();
        $student = Student::where('email', 'transfer.ok@brighterday.test')->firstOrFail();
        $this->assertNotNull($student->transcript_path);
        $this->assertNotNull($student->image_path);
        Storage::disk('local')->assertExists($student->transcript_path);
    }

    public function test_admin_can_list_admissions_filtered_by_status(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $token = $this->tokenFor('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'pending.one@brighterday.test']))
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admissions?status=pending');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    public function test_approving_a_student_creates_a_user_and_queues_the_admission_email(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->tokenFor('registrar');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'approve.me@brighterday.test']))
            ->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/approve");

        $response->assertOk()->assertJsonPath('status', 'approved');

        $student = Student::find($created['id']);
        $this->assertNotNull($student->admission_no);
        $this->assertStringStartsWith('BDS-', $student->admission_no);
        $this->assertNotNull($student->user_id);

        $user = User::find($student->user_id);
        $this->assertEquals('student', $user->role);
        $this->assertEquals($student->admission_no, $user->username);
        $this->assertTrue($user->must_change_password);

        $this->assertDatabaseHas('email_log', [
            'user_id' => $user->id,
            'type' => 'admission_letter',
            'status' => 'queued',
        ]);

        $letterPath = "admission-letters/{$student->admission_no}.pdf";
        Storage::disk('local')->assertExists($letterPath);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($letterPath));

        Queue::assertPushed(SendLoggedEmailJob::class, function ($job) use ($letterPath) {
            return $job->attachmentPath === $letterPath
                && str_ends_with($job->attachmentName, 'admission-letter.pdf');
        });
    }

    public function test_approving_a_student_into_a_class_with_an_installment_plan_charges_only_installment_one(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->tokenFor('admin');

        $class = $this->makeClass();
        ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000]);
        ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 2, 'amount_cents' => 1500000]);
        ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 3, 'amount_cents' => 1500000]);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'installment.plan@brighterday.test', 'class_id' => $class->id]))
            ->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/approve")->assertOk();

        $this->assertDatabaseCount('fee_transactions', 1);
        $this->assertDatabaseHas('fee_transactions', [
            'student_id' => $created['id'],
            'amount_cents' => 1500000,
        ]);
        $installmentOne = ClassFeeInstallment::where('class_id', $class->id)->where('sequence', 1)->first();
        $this->assertEquals(
            $installmentOne->id,
            \App\Models\FeeTransaction::where('student_id', $created['id'])->value('class_fee_installment_id'),
        );
    }

    public function test_approving_a_student_whose_email_already_has_a_login_fails_gracefully(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->tokenFor('admin');
        User::factory()->create(['email' => 'clashing@brighterday.test']);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'clashing@brighterday.test']))
            ->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/approve");

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertDatabaseHas('students', ['id' => $created['id'], 'status' => 'pending']);
    }

    public function test_a_non_pending_student_cannot_be_approved_again(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->tokenFor('admin');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'double.approve@brighterday.test']))
            ->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/approve")->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/approve");

        $response->assertUnprocessable();
    }

    public function test_rejecting_a_pending_student_deletes_the_admission(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $token = $this->tokenFor('registrar');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload([
                'email' => 'reject.me@brighterday.test',
                'is_transfer_student' => true,
                'transcript' => UploadedFile::fake()->create('transcript.pdf', 400, 'application/pdf'),
                'photo' => UploadedFile::fake()->image('photo.jpg')->size(300),
            ]))->json();

        $student = Student::find($created['id']);
        $imagePath = $student->image_path;
        $transcriptPath = $student->transcript_path;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/reject");

        $response->assertNoContent();
        $this->assertDatabaseMissing('students', ['id' => $created['id']]);
        $this->assertNull(User::where('email', 'reject.me@brighterday.test')->first());
        Storage::disk('public')->assertMissing($imagePath);
        Storage::disk('local')->assertMissing($transcriptPath);
    }

    public function test_an_approved_student_cannot_be_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->tokenFor('admin');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'approved.reject@brighterday.test']))
            ->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/approve")->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/reject");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('students', ['id' => $created['id'], 'status' => 'approved']);
    }

    public function test_admin_can_edit_an_admissions_bio_data(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $token = $this->tokenFor('admin');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'edit.me@brighterday.test']))
            ->json();

        $newClass = $this->makeClass();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/students/{$created['id']}", [
                'full_name' => 'Comfort J. Johnson',
                'contact' => '0889999999',
                'class_id' => $newClass->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('full_name', 'Comfort J. Johnson')
            ->assertJsonPath('contact', '0889999999')
            ->assertJsonPath('class_id', $newClass->id);
    }

    public function test_editing_an_approved_students_email_keeps_their_login_in_sync(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->tokenFor('admin');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'sync.me@brighterday.test']))
            ->json();

        $approved = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/approve")->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/students/{$created['id']}", ['email' => 'sync.me.updated@brighterday.test'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $approved['user_id'], 'email' => 'sync.me.updated@brighterday.test']);
    }

    public function test_editing_an_admission_rejects_an_email_already_used_by_another_login(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $token = $this->tokenFor('admin');
        User::factory()->create(['email' => 'taken.by.someone@brighterday.test']);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'edit.email.clash@brighterday.test']))
            ->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/students/{$created['id']}", ['email' => 'taken.by.someone@brighterday.test']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_delete_an_admission_and_it_removes_the_linked_login(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->tokenFor('admin');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload([
                'email' => 'delete.me@brighterday.test',
                'is_transfer_student' => true,
                'transcript' => UploadedFile::fake()->create('transcript.pdf', 400, 'application/pdf'),
            ]))->json();

        $approved = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/approve")->json();

        $transcriptPath = Student::find($created['id'])->transcript_path;
        $letterPath = "admission-letters/{$approved['admission_no']}.pdf";
        Storage::disk('local')->assertExists($letterPath);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/students/{$created['id']}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('students', ['id' => $created['id']]);
        $this->assertDatabaseMissing('users', ['id' => $approved['user_id']]);
        Storage::disk('local')->assertMissing($transcriptPath);
        Storage::disk('local')->assertMissing($letterPath);
    }

    public function test_a_deleted_students_email_can_be_reused_by_a_new_application_and_approved(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->tokenFor('admin');

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'reused@brighterday.test']))
            ->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$first['id']}/approve")->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/students/{$first['id']}")->assertNoContent();

        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload([
                'full_name' => 'Second Applicant',
                'email' => 'reused@brighterday.test',
            ]))->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$second['id']}/approve");

        $response->assertOk()->assertJsonPath('status', 'approved');
    }

    public function test_admin_can_download_the_admission_letter_after_approval(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $token = $this->tokenFor('admin');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'letter.owner@brighterday.test']))
            ->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$created['id']}/approve")->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/students/{$created['id']}/admission-letter");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_admission_letter_download_404s_before_approval(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $token = $this->tokenFor('admin');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'no.letter.yet@brighterday.test']))
            ->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/students/{$created['id']}/admission-letter");

        $response->assertNotFound();
    }

    public function test_student_can_be_reassigned_to_a_different_class(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $token = $this->tokenFor('admin');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'reassign.me@brighterday.test']))
            ->json();

        $newClass = $this->makeClass();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/students/{$created['id']}/class", ['class_id' => $newClass->id]);

        $response->assertOk()->assertJsonPath('class_id', $newClass->id);
    }

    public function test_admin_can_download_a_transfer_students_transcript(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $token = $this->tokenFor('admin');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload([
                'email' => 'transcript.owner@brighterday.test',
                'is_transfer_student' => true,
                'transcript' => UploadedFile::fake()->create('transcript.pdf', 400, 'application/pdf'),
            ]))->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/students/{$created['id']}/transcript");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_teacher_and_accountant_cannot_access_admissions(): void
    {
        $teacherResponse = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('teacher'))
            ->getJson('/api/v1/admissions');
        $teacherResponse->assertForbidden();

        $accountantResponse = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('accountant'))
            ->getJson('/api/v1/admissions');
        $accountantResponse->assertForbidden();
    }

    public function test_daily_summary_returns_fourteen_zero_filled_days_with_intake_counts(): void
    {
        $token = $this->tokenFor('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'daily.one@brighterday.test']))
            ->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/students', $this->validIntakePayload(['email' => 'daily.two@brighterday.test']))
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admissions/daily-summary');

        $response->assertOk();
        $series = $response->json();
        $this->assertCount(14, $series);

        $today = date('Y-m-d');
        $todayEntry = collect($series)->firstWhere('date', $today);
        $this->assertEquals(2, $todayEntry['count']);
        $this->assertEquals($today, $series[13]['date']);
        $this->assertEquals(0, $series[0]['count']);
    }
}
