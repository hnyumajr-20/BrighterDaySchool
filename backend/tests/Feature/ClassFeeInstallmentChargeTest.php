<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassFeeInstallment;
use App\Models\FeeTransaction;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ClassFeeInstallmentChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClassFeeInstallmentChargeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): ClassFeeInstallmentChargeService
    {
        return new ClassFeeInstallmentChargeService;
    }

    private function makeActiveYear(): AcademicYear
    {
        return AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
    }

    private function makeClass(AcademicYear $year, int $feeCents = 4500000): SchoolClass
    {
        return SchoolClass::create(['name' => 'JSS1', 'arm' => 'A', 'fee_amount_cents' => $feeCents, 'academic_year_id' => $year->id]);
    }

    private function makeApprovedStudent(SchoolClass $class, ?string $email = null): Student
    {
        $parent = Guardian::create(['full_name' => 'A Parent', 'phone' => '077'.random_int(1000000, 9999999)]);

        return Student::create([
            'full_name' => 'Fee Student', 'dob' => '2012-01-01', 'gender' => 'female',
            'email' => $email ?? 'fee.student.'.uniqid().'@brighterday.test', 'parent_id' => $parent->id,
            'class_id' => $class->id, 'is_transfer_student' => false, 'status' => 'approved',
        ]);
    }

    public function test_charges_an_approved_student_once_the_due_date_arrives(): void
    {
        $year = $this->makeActiveYear();
        $class = $this->makeClass($year);
        $student = $this->makeApprovedStudent($class);
        $installment = ClassFeeInstallment::create([
            'class_id' => $class->id, 'sequence' => 2, 'amount_cents' => 1500000, 'due_date' => '2026-10-15',
        ]);

        Carbon::setTestNow('2026-10-15');
        $this->service()->run();

        $this->assertDatabaseHas('fee_transactions', [
            'student_id' => $student->id, 'class_fee_installment_id' => $installment->id, 'amount_cents' => 1500000,
        ]);
    }

    public function test_does_not_charge_before_the_due_date(): void
    {
        $year = $this->makeActiveYear();
        $class = $this->makeClass($year);
        $this->makeApprovedStudent($class);
        ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000, 'due_date' => '2026-10-15']);

        Carbon::setTestNow('2026-10-14');
        $this->service()->run();

        $this->assertDatabaseCount('fee_transactions', 0);
    }

    public function test_does_not_double_charge_on_a_second_run(): void
    {
        $year = $this->makeActiveYear();
        $class = $this->makeClass($year);
        $this->makeApprovedStudent($class);
        ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000, 'due_date' => '2026-10-15']);

        Carbon::setTestNow('2026-10-16');
        $this->service()->run();
        $this->service()->run();

        $this->assertDatabaseCount('fee_transactions', 1);
    }

    public function test_skips_installments_with_no_due_date_set(): void
    {
        $year = $this->makeActiveYear();
        $class = $this->makeClass($year);
        $this->makeApprovedStudent($class);
        ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000, 'due_date' => null]);

        Carbon::setTestNow('2027-01-01');
        $this->service()->run();

        $this->assertDatabaseCount('fee_transactions', 0);
    }

    public function test_does_not_charge_when_no_academic_year_is_active(): void
    {
        $year = $this->makeActiveYear();
        $class = $this->makeClass($year);
        $this->makeApprovedStudent($class);
        ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000, 'due_date' => '2026-10-15']);
        $year->update(['status' => 'closed']);

        Carbon::setTestNow('2026-10-16');
        $this->service()->run();

        $this->assertDatabaseCount('fee_transactions', 0);
    }

    public function test_catches_a_student_approved_into_the_class_after_the_due_date_already_passed(): void
    {
        $year = $this->makeActiveYear();
        $class = $this->makeClass($year);
        $installment = ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000, 'due_date' => '2026-10-15']);

        Carbon::setTestNow('2026-10-20');
        $this->service()->run();
        $this->assertDatabaseCount('fee_transactions', 0); // nobody in the class yet

        $lateStudent = $this->makeApprovedStudent($class, 'late.joiner@brighterday.test');
        $this->service()->run();

        $this->assertDatabaseHas('fee_transactions', [
            'student_id' => $lateStudent->id, 'class_fee_installment_id' => $installment->id,
        ]);
    }

    public function test_does_not_recharge_a_student_who_already_has_a_charge_for_that_installment(): void
    {
        $year = $this->makeActiveYear();
        $class = $this->makeClass($year);
        $student = $this->makeApprovedStudent($class);
        $installment = ClassFeeInstallment::create(['class_id' => $class->id, 'sequence' => 1, 'amount_cents' => 1500000, 'due_date' => '2026-10-15']);

        FeeTransaction::create([
            'student_id' => $student->id, 'amount_cents' => 1500000, 'type' => 'charge',
            'class_fee_installment_id' => $installment->id, 'academic_year_id' => $year->id,
        ]);

        Carbon::setTestNow('2026-10-16');
        $this->service()->run();

        $this->assertDatabaseCount('fee_transactions', 1);
    }
}
