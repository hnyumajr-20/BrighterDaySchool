<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\FeeTransaction;
use App\Models\Guardian;
use App\Models\SalaryPayment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalaryPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tokenFor(string $role): string
    {
        return User::factory()->role($role)->create()->createToken('api')->plainTextToken;
    }

    private function makeActiveYear(): AcademicYear
    {
        return AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
    }

    private function makeApprovedStudentWithPayment(int $paidCents): void
    {
        $year = $this->makeActiveYear();
        $class = SchoolClass::create(['name' => 'JSS1', 'arm' => 'A', 'fee_amount_cents' => 9000000, 'academic_year_id' => $year->id]);
        $parent = Guardian::create(['full_name' => 'A Parent', 'phone' => '077'.random_int(1000000, 9999999)]);
        $student = Student::create([
            'full_name' => 'Fee Student', 'dob' => '2012-01-01', 'gender' => 'female',
            'email' => 'fee.student.'.uniqid().'@brighterday.test', 'parent_id' => $parent->id, 'class_id' => $class->id,
            'is_transfer_student' => false, 'status' => 'approved',
        ]);

        FeeTransaction::create([
            'student_id' => $student->id, 'amount_cents' => -$paidCents, 'type' => 'payment', 'academic_year_id' => $year->id,
        ]);
    }

    public function test_recording_a_salary_payment_creates_the_row(): void
    {
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'salary_cents' => 50000000, 'status' => 'active']);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 50000000, 'note' => 'August salary']);

        $response->assertCreated()->assertJsonPath('amount_cents', 50000000);
        $this->assertDatabaseHas('salary_payments', ['staff_id' => $staff->id, 'amount_cents' => 50000000]);
    }

    public function test_cannot_pay_an_inactive_staff_member(): void
    {
        $staff = Staff::create(['full_name' => 'Former Staff', 'staff_role' => 'teacher', 'status' => 'inactive']);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 10000]);

        $response->assertUnprocessable();
    }

    public function test_summary_reflects_collected_fees_and_salary_paid(): void
    {
        $this->makeApprovedStudentWithPayment(6000000);
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'salary_cents' => 100000000, 'status' => 'active']);
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 2000000])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/finance/accountant-summary');

        $response->assertOk();
        $response->assertJsonPath('fees_collected_cents', 6000000);
        $response->assertJsonPath('salary_paid_cents', 2000000);
        $response->assertJsonPath('available_cents', 4000000);
    }

    public function test_salary_payment_is_not_blocked_by_exceeding_the_available_balance(): void
    {
        $this->makeApprovedStudentWithPayment(1000000);
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'salary_cents' => 100000000, 'status' => 'active']);
        $token = $this->tokenFor('accountant');

        // Available balance is only $10, but a $50 payment should still succeed.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 5000000]);

        $response->assertCreated();

        $summary = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/finance/accountant-summary');
        $summary->assertJsonPath('available_cents', -4000000);
    }

    public function test_history_can_be_filtered_by_staff(): void
    {
        $staffA = Staff::create(['full_name' => 'Staff A', 'staff_role' => 'teacher', 'salary_cents' => 100000000, 'status' => 'active']);
        $staffB = Staff::create(['full_name' => 'Staff B', 'staff_role' => 'librarian', 'salary_cents' => 100000000, 'status' => 'active']);
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staffA->id, 'amount_cents' => 1000000])->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staffB->id, 'amount_cents' => 2000000])->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/salary-payments?staff_id={$staffA->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertEquals($staffA->id, $response->json('0.staff_id'));
    }

    public function test_registrar_can_read_but_not_record_salary_payments(): void
    {
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'status' => 'active']);
        $token = $this->tokenFor('registrar');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/salary-payments')->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 100000]);

        $response->assertForbidden();
    }

    public function test_teacher_cannot_access_salary_payments(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('teacher'))
            ->getJson('/api/v1/salary-payments');

        $response->assertForbidden();
    }

    public function test_daily_summary_returns_fourteen_zero_filled_days_of_payouts(): void
    {
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'salary_cents' => 100000000, 'status' => 'active']);
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 3000000])
            ->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 2000000])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/salary-payments/daily-summary');

        $response->assertOk();
        $series = $response->json();
        $this->assertCount(14, $series);

        $today = date('Y-m-d');
        $todayEntry = collect($series)->firstWhere('date', $today);
        $this->assertEquals(5000000, $todayEntry['paid_cents']);
        $this->assertEquals(0, $series[0]['paid_cents']);
    }

    public function test_multiple_partial_payments_can_exactly_complete_the_monthly_salary(): void
    {
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'salary_cents' => 5000000, 'status' => 'active']);
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 3000000])
            ->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 2000000])
            ->assertCreated();

        $overview = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/salary-payments/staff-overview');

        $row = collect($overview->json())->firstWhere('id', $staff->id);
        $this->assertEquals(5000000, $row['paid_this_month_cents']);
        $this->assertEquals(0, $row['remaining_this_month_cents']);
    }

    public function test_a_payment_exceeding_the_remaining_monthly_salary_is_rejected_with_the_exact_amount_left(): void
    {
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'salary_cents' => 5000000, 'status' => 'active']);
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 3000000])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 2500000]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('$20,000.00 left', $response->json('message'));
        $this->assertDatabaseCount('salary_payments', 1);
    }

    public function test_a_further_payment_after_the_month_is_fully_paid_is_rejected(): void
    {
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'salary_cents' => 5000000, 'status' => 'active']);
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 5000000])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 1]);

        $response->assertUnprocessable();
        $this->assertStringContainsString('already received their full salary', $response->json('message'));
    }

    public function test_the_monthly_cap_resets_in_a_new_calendar_month(): void
    {
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'salary_cents' => 5000000, 'status' => 'active']);
        $token = $this->tokenFor('accountant');

        // created_at defaults from the DB server's own clock (useCurrent()),
        // which does not respect Carbon::setTestNow — force it explicitly
        // so this payment lands in "last month" regardless of when the
        // test suite actually runs.
        $payment = SalaryPayment::create(['staff_id' => $staff->id, 'amount_cents' => 5000000]);
        $payment->forceFill(['created_at' => Carbon::parse('2026-08-15')])->save();

        Carbon::setTestNow('2026-09-01');
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/salary-payments', ['staff_id' => $staff->id, 'amount_cents' => 5000000]);

        $response->assertCreated();
    }
}
