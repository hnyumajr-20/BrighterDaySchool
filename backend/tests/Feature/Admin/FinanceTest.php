<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\FeeTransaction;
use App\Models\Guardian;
use App\Models\SalaryPayment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(string $role): string
    {
        return User::factory()->role($role)->create()->createToken('api')->plainTextToken;
    }

    private function makeStudent(): Student
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $class = SchoolClass::create([
            'name' => 'JSS1', 'arm' => 'A', 'fee_amount_cents' => 4500000, 'academic_year_id' => $year->id,
        ]);
        $parent = Guardian::create(['full_name' => 'A Parent', 'phone' => '077'.random_int(1000000, 9999999)]);

        $student = Student::create([
            'full_name' => 'Fee Student', 'dob' => '2012-01-01', 'gender' => 'female',
            'email' => 'fee.student.'.uniqid().'@brighterday.test', 'parent_id' => $parent->id,
            'class_id' => $class->id, 'is_transfer_student' => false, 'status' => 'approved',
        ]);

        return $student->refresh()->load('schoolClass');
    }

    public function test_admin_sees_a_zeroed_overview_with_no_transactions_yet(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('admin'))
            ->getJson('/api/v1/finance/overview');

        $response->assertOk();
        $response->assertJsonPath('fees.total_billed_cents', 0);
        $response->assertJsonPath('fees.total_collected_cents', 0);
        $response->assertJsonPath('fees.outstanding_cents', 0);
    }

    public function test_overview_totals_charges_payments_and_discounts_correctly(): void
    {
        $student = $this->makeStudent();
        $year = AcademicYear::first();

        FeeTransaction::create([
            'student_id' => $student->id, 'amount_cents' => 4500000, 'type' => 'charge', 'academic_year_id' => $year->id,
        ]);
        FeeTransaction::create([
            'student_id' => $student->id, 'amount_cents' => -3000000, 'type' => 'payment', 'academic_year_id' => $year->id,
        ]);
        FeeTransaction::create([
            'student_id' => $student->id, 'amount_cents' => -500000, 'type' => 'discount', 'academic_year_id' => $year->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('admin'))
            ->getJson('/api/v1/finance/overview');

        $response->assertOk();
        $response->assertJsonPath('fees.total_billed_cents', 4500000);
        $response->assertJsonPath('fees.total_collected_cents', 3000000);
        $response->assertJsonPath('fees.total_discounts_cents', 500000);
        $response->assertJsonPath('fees.outstanding_cents', 1000000);
    }

    public function test_overview_sums_active_staff_salaries_as_monthly_payroll(): void
    {
        Staff::create(['full_name' => 'Active One', 'staff_role' => 'teacher', 'salary_cents' => 50000000, 'status' => 'active']);
        Staff::create(['full_name' => 'Active Two', 'staff_role' => 'registrar', 'salary_cents' => 40000000, 'status' => 'active']);
        Staff::create(['full_name' => 'Inactive One', 'staff_role' => 'librarian', 'salary_cents' => 60000000, 'status' => 'inactive']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('admin'))
            ->getJson('/api/v1/finance/overview');

        $response->assertOk();
        $response->assertJsonPath('payroll.monthly_total_cents', 90000000);
        $response->assertJsonPath('payroll.active_staff_count', 2);
    }

    public function test_overview_includes_salary_paid_and_available_balance(): void
    {
        $student = $this->makeStudent();
        $year = AcademicYear::first();
        $staff = Staff::create(['full_name' => 'Teacher One', 'staff_role' => 'teacher', 'status' => 'active']);

        FeeTransaction::create([
            'student_id' => $student->id, 'amount_cents' => -6000000, 'type' => 'payment', 'academic_year_id' => $year->id,
        ]);
        SalaryPayment::create(['staff_id' => $staff->id, 'amount_cents' => 2000000]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('admin'))
            ->getJson('/api/v1/finance/overview');

        $response->assertOk();
        $response->assertJsonPath('fees.total_collected_cents', 6000000);
        $response->assertJsonPath('payroll.salary_paid_cents', 2000000);
        $response->assertJsonPath('payroll.available_cents', 4000000);
    }

    public function test_non_admin_roles_cannot_access_the_finance_overview(): void
    {
        $accountantResponse = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('accountant'))
            ->getJson('/api/v1/finance/overview');
        $accountantResponse->assertForbidden();

        $registrarResponse = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('registrar'))
            ->getJson('/api/v1/finance/overview');
        $registrarResponse->assertForbidden();
    }
}
