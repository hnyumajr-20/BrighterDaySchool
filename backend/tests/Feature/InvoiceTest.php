<?php

namespace Tests\Feature;

use App\Jobs\SendLoggedEmailJob;
use App\Models\AcademicYear;
use App\Models\FeeTransaction;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceTest extends TestCase
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

    private function makeStudent(array $overrides = []): Student
    {
        $parent = Guardian::create(['full_name' => 'A Parent', 'phone' => '077'.random_int(1000000, 9999999)]);

        return Student::create(array_merge([
            'full_name' => 'Invoice Student', 'dob' => '2012-01-01', 'gender' => 'female',
            'email' => 'invoice.student.'.uniqid().'@brighterday.test', 'parent_id' => $parent->id,
            'is_transfer_student' => false, 'status' => 'approved',
        ], $overrides));
    }

    /**
     * Builds an invoice (plus its linked charge FeeTransaction) directly via
     * Eloquent rather than an HTTP call — used when a test needs to act as a
     * *different* user for the actual assertion, since making two requests
     * as different users within one test method makes Sanctum's auth guard
     * cache the first-resolved user for the second request too.
     */
    private function makeInvoice(Student $student, array $overrides = []): Invoice
    {
        $activeYear = $this->makeActiveYear();

        $invoice = Invoice::create(array_merge([
            'invoice_no' => 'INV-2026-'.random_int(1000, 9999),
            'student_id' => $student->id,
            'type' => 'tuition',
            'amount_cents' => 100000,
            'status' => 'unpaid',
            'academic_year_id' => $activeYear->id,
        ], $overrides));

        FeeTransaction::create([
            'student_id' => $student->id,
            'amount_cents' => $invoice->amount_cents,
            'type' => 'charge',
            'invoice_id' => $invoice->id,
            'note' => "Invoice {$invoice->invoice_no} ({$invoice->type})",
            'academic_year_id' => $activeYear->id,
        ]);

        return $invoice;
    }

    public function test_admin_cannot_create_invoice(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', ['student_id' => $student->id, 'type' => 'tuition', 'amount_cents' => 100000]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_pay_invoice(): void
    {
        $student = $this->makeStudent();
        $invoice = $this->makeInvoice($student);
        $token = $this->tokenFor('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice->id}/pay", ['payment_method' => 'cash']);

        $response->assertForbidden();
    }

    public function test_admin_can_view_invoices_and_report(): void
    {
        $token = $this->tokenFor('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/invoices');

        $response->assertOk();
    }

    public function test_accountant_can_create_and_pay_cash_invoice(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('accountant');

        $invoice = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', ['student_id' => $student->id, 'type' => 'tuition', 'amount_cents' => 250000])
            ->assertCreated()
            ->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice['id']}/pay", ['payment_method' => 'cash']);

        $response->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('payment_method', 'cash');
        $this->assertDatabaseCount('fee_transactions', 2);
        $this->assertEquals(0, $student->feeTransactions()->sum('amount_cents'));
    }

    public function test_paying_with_orange_money_records_gateway_transaction_id(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('accountant');

        $invoice = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', ['student_id' => $student->id, 'type' => 'tuition', 'amount_cents' => 250000])
            ->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice['id']}/pay", [
                'payment_method' => 'orange_money',
                'payer_phone' => '0770123456',
            ]);

        $response->assertOk()
            ->assertJsonPath('payment_method', 'orange_money')
            ->assertJsonPath('payer_phone', '0770123456');
        $this->assertMatchesRegularExpression('/^OM-\d{4}-\d{4}$/', $response->json('gateway_transaction_id'));
    }

    public function test_paying_registration_invoice_for_pending_student_auto_approves_admission(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $this->makeActiveYear();
        $parent = Guardian::create(['full_name' => 'A Parent', 'phone' => '077'.random_int(1000000, 9999999)]);
        $student = Student::create([
            'full_name' => 'Pending Applicant', 'dob' => '2012-01-01', 'gender' => 'male',
            'email' => 'pending.applicant.'.uniqid().'@brighterday.test', 'parent_id' => $parent->id,
            'is_transfer_student' => false, 'status' => 'pending',
        ]);
        $token = $this->tokenFor('accountant');

        $invoice = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', ['student_id' => $student->id, 'type' => 'registration', 'amount_cents' => 50000])
            ->assertCreated()
            ->json();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice['id']}/pay", ['payment_method' => 'cash']);

        $response->assertOk()->assertJsonPath('admission_auto_approved', true);

        $student = $student->fresh();
        $this->assertEquals('approved', $student->status);
        $this->assertNotNull($student->admission_no);
        $this->assertStringStartsWith('BDS-', $student->admission_no);
        $this->assertNotNull($student->user_id);

        $this->assertDatabaseHas('email_log', ['type' => 'admission_letter', 'status' => 'queued']);
        Queue::assertPushed(SendLoggedEmailJob::class);
    }

    public function test_paying_an_already_paid_invoice_is_rejected(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent();
        $token = $this->tokenFor('accountant');

        $invoice = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', ['student_id' => $student->id, 'type' => 'tuition', 'amount_cents' => 100000])
            ->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice['id']}/pay", ['payment_method' => 'cash'])
            ->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice['id']}/pay", ['payment_method' => 'cash']);

        $response->assertUnprocessable();
    }

    public function test_registration_invoice_cannot_be_duplicated_while_unpaid(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent(['status' => 'pending']);
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', ['student_id' => $student->id, 'type' => 'registration', 'amount_cents' => 50000])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', ['student_id' => $student->id, 'type' => 'registration', 'amount_cents' => 50000]);

        $response->assertUnprocessable();
    }

    public function test_registration_invoice_cannot_be_created_for_an_approved_student(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent(['status' => 'approved']);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', ['student_id' => $student->id, 'type' => 'registration', 'amount_cents' => 50000]);

        $response->assertUnprocessable();
    }

    public function test_tuition_invoice_cannot_be_created_for_a_pending_student(): void
    {
        $this->makeActiveYear();
        $student = $this->makeStudent(['status' => 'pending']);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', ['student_id' => $student->id, 'type' => 'tuition', 'amount_cents' => 50000]);

        $response->assertUnprocessable();
    }

    public function test_rejecting_a_student_with_an_unpaid_registration_invoice_deletes_it(): void
    {
        $student = $this->makeStudent(['status' => 'pending']);
        $invoice = $this->makeInvoice($student, ['type' => 'registration', 'amount_cents' => 50000]);
        $token = $this->tokenFor('registrar');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/students/{$student->id}/reject")
            ->assertNoContent();

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }
}
