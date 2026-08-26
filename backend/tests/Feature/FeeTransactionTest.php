<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassFeeInstallment;
use App\Models\FeeTransaction;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(string $role): string
    {
        return User::factory()->role($role)->create()->createToken('api')->plainTextToken;
    }

    private function makeActiveYear(): AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active'],
        );
    }

    private function makeClass(int $feeCents = 4500000): SchoolClass
    {
        return SchoolClass::create([
            'name' => 'JSS1', 'arm' => 'A', 'fee_amount_cents' => $feeCents, 'academic_year_id' => $this->makeActiveYear()->id,
        ]);
    }

    private function makeStudent(array $overrides = []): Student
    {
        $parent = Guardian::create(['full_name' => 'A Parent', 'phone' => '077'.random_int(1000000, 9999999)]);

        return Student::create(array_merge([
            'full_name' => 'Fee Student', 'dob' => '2012-01-01', 'gender' => 'female',
            'email' => 'fee.student.'.uniqid().'@brighterday.test', 'parent_id' => $parent->id,
            'is_transfer_student' => false, 'status' => 'approved',
        ], $overrides));
    }

    public function test_recording_a_charge_increases_the_balance(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', [
                'student_id' => $student->id, 'amount_cents' => 4500000, 'type' => 'charge',
            ]);

        $response->assertCreated()->assertJsonPath('amount_cents', 4500000);
        $this->assertEquals(4500000, $student->feeTransactions()->sum('amount_cents'));
    }

    public function test_recording_a_payment_stores_a_negative_amount_and_reduces_the_balance(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 4500000, 'type' => 'charge'])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 3000000, 'type' => 'payment']);

        $response->assertCreated()->assertJsonPath('amount_cents', -3000000);
        $this->assertEquals(1500000, $student->feeTransactions()->sum('amount_cents'));
    }

    public function test_recording_a_discount_also_stores_a_negative_amount(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 500000, 'type' => 'discount']);

        $response->assertCreated()->assertJsonPath('amount_cents', -500000);
    }

    public function test_cannot_record_a_transaction_for_a_non_approved_student(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent(['status' => 'pending']);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 100000, 'type' => 'charge']);

        $response->assertUnprocessable();
    }

    public function test_cannot_record_a_transaction_with_no_active_academic_year(): void
    {
        $student = $this->makeStudent();
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 100000, 'type' => 'charge']);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('fee_transactions', 0);
    }

    public function test_students_overview_computes_balance_and_excludes_non_approved_students(): void
    {
        $this->makeActiveYear();
        $approved = $this->makeStudent();
        $this->makeStudent(['status' => 'pending', 'email' => 'pending.'.uniqid().'@brighterday.test']);
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $approved->id, 'amount_cents' => 4500000, 'type' => 'charge'])
            ->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $approved->id, 'amount_cents' => 1000000, 'type' => 'payment'])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/fee-transactions/students');

        $response->assertOk();
        $rows = collect($response->json());
        $this->assertCount(1, $rows);
        $this->assertEquals(3500000, $rows->first()['balance_cents']);
    }

    public function test_students_overview_defaults_balance_to_zero_with_no_transactions(): void
    {
        $this->makeStudent();
        $token = $this->tokenFor('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/fee-transactions/students');

        $response->assertOk()->assertJsonPath('0.balance_cents', 0);
    }

    public function test_balance_endpoint_sums_all_transactions_for_a_student(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 4500000, 'type' => 'charge'])
            ->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 4500000, 'type' => 'payment'])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/students/{$student->id}/balance");

        $response->assertOk()->assertJsonPath('balance_cents', 0);
    }

    public function test_history_endpoint_returns_a_students_transactions_newest_first(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('registrar');

        FeeTransaction::create([
            'student_id' => $student->id, 'amount_cents' => 4500000, 'type' => 'charge',
            'academic_year_id' => AcademicYear::first()->id,
        ]);
        FeeTransaction::create([
            'student_id' => $student->id, 'amount_cents' => -1000000, 'type' => 'payment',
            'academic_year_id' => AcademicYear::first()->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/fee-transactions?student_id={$student->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json());
        $this->assertEquals('payment', $response->json('0.type'));
    }

    public function test_registrar_can_read_but_not_record_transactions(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('registrar');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/fee-transactions/students')->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 100000, 'type' => 'charge']);

        $response->assertForbidden();
    }

    public function test_teacher_and_librarian_cannot_access_fee_transactions_at_all(): void
    {
        $teacherResponse = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('teacher'))
            ->getJson('/api/v1/fee-transactions/students');
        $teacherResponse->assertForbidden();

        $librarianResponse = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('librarian'))
            ->getJson('/api/v1/fee-transactions/students');
        $librarianResponse->assertForbidden();
    }

    public function test_charging_an_installment_tags_the_transaction(): void
    {
        $this->makeActiveYear();
        $class = $this->makeClass(4500000);
        $installment = ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000]);
        $student = $this->makeStudent(['class_id' => $class->id]);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', [
                'student_id' => $student->id, 'amount_cents' => 1500000, 'type' => 'charge',
                'class_fee_installment_id' => $installment->id,
            ]);

        $response->assertCreated()->assertJsonPath('class_fee_installment_id', $installment->id);
    }

    public function test_cannot_charge_the_same_installment_twice_for_the_same_student(): void
    {
        $this->makeActiveYear();
        $class = $this->makeClass(4500000);
        $installment = ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000]);
        $student = $this->makeStudent(['class_id' => $class->id]);
        $token = $this->tokenFor('accountant');

        $payload = [
            'student_id' => $student->id, 'amount_cents' => 1500000, 'type' => 'charge',
            'class_fee_installment_id' => $installment->id,
        ];
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/fee-transactions', $payload)->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/fee-transactions', $payload);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('fee_transactions', 1);
    }

    public function test_cannot_tag_a_payment_with_an_installment(): void
    {
        $this->makeActiveYear();
        $class = $this->makeClass(4500000);
        $installment = ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000]);
        $student = $this->makeStudent(['class_id' => $class->id]);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', [
                'student_id' => $student->id, 'amount_cents' => 1500000, 'type' => 'payment',
                'class_fee_installment_id' => $installment->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_charge_an_installment_from_a_different_class(): void
    {
        $year = $this->makeActiveYear();
        $class = $this->makeClass(4500000);
        $otherClass = SchoolClass::create(['name' => 'JSS1', 'arm' => 'B', 'fee_amount_cents' => 3000000, 'academic_year_id' => $year->id]);
        $installment = ClassFeeInstallment::create(['class_id' => $otherClass->id, 'sequence' => 1, 'amount_cents' => 1000000]);
        $student = $this->makeStudent(['class_id' => $class->id]);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', [
                'student_id' => $student->id, 'amount_cents' => 1000000, 'type' => 'charge',
                'class_fee_installment_id' => $installment->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_approving_a_student_with_a_class_auto_charges_the_class_fee(): void
    {
        $this->makeActiveYear();
        $class = $this->makeClass(4500000);
        $student = $this->makeStudent(['status' => 'pending', 'class_id' => $class->id]);
        $token = $this->tokenFor('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$student->id}/approve")->assertOk();

        $this->assertDatabaseHas('fee_transactions', [
            'student_id' => $student->id, 'amount_cents' => 4500000, 'type' => 'charge',
        ]);
    }

    public function test_approving_a_student_with_no_class_does_not_create_a_charge(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent(['status' => 'pending']);
        $token = $this->tokenFor('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$student->id}/approve")->assertOk();

        $this->assertDatabaseCount('fee_transactions', 0);
    }

    public function test_approving_a_student_with_no_active_year_still_succeeds_without_a_charge(): void
    {
        $class = $this->makeClass(4500000);
        AcademicYear::first()->update(['status' => 'closed']);
        $student = $this->makeStudent(['status' => 'pending', 'class_id' => $class->id]);
        $token = $this->tokenFor('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$student->id}/approve");

        $response->assertOk()->assertJsonPath('status', 'approved');
        $this->assertDatabaseCount('fee_transactions', 0);
    }

    public function test_daily_collections_returns_fourteen_zero_filled_days_summing_only_payments(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 4500000, 'type' => 'charge'])
            ->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 1000000, 'type' => 'payment'])
            ->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fee-transactions', ['student_id' => $student->id, 'amount_cents' => 500000, 'type' => 'payment'])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/fee-transactions/daily-collections');

        $response->assertOk();
        $series = $response->json();
        $this->assertCount(14, $series);

        $today = date('Y-m-d');
        $todayEntry = collect($series)->firstWhere('date', $today);
        // Only the two payments count, not the charge.
        $this->assertEquals(1500000, $todayEntry['collected_cents']);
        $this->assertEquals(0, $series[0]['collected_cents']);
    }
}
