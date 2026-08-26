<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassFeeInstallmentTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(string $role): string
    {
        return User::factory()->role($role)->create()->createToken('api')->plainTextToken;
    }

    private function makeClass(int $feeCents): SchoolClass
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);

        return SchoolClass::create(['name' => 'JSS1', 'arm' => 'A', 'fee_amount_cents' => $feeCents, 'academic_year_id' => $year->id]);
    }

    /**
     * @return array<int, string>
     */
    private function defaultDueDates(): array
    {
        return ['2026-10-01', '2026-11-01', '2026-12-01'];
    }

    public function test_default_plan_splits_the_fee_into_equal_thirds(): void
    {
        $class = $this->makeClass(4500000);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/fee-installments", ['due_dates' => $this->defaultDueDates()]);

        $response->assertOk();
        $amounts = collect($response->json())->pluck('amount_cents')->all();
        $this->assertEquals([1500000, 1500000, 1500000], $amounts);
    }

    public function test_default_plan_absorbs_the_rounding_remainder_into_the_last_installment(): void
    {
        $class = $this->makeClass(1000); // not evenly divisible by 3
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/fee-installments", ['due_dates' => $this->defaultDueDates()]);

        $response->assertOk();
        $amounts = collect($response->json())->pluck('amount_cents')->all();
        $this->assertEquals([333, 333, 334], $amounts);
        $this->assertEquals(1000, array_sum($amounts));
    }

    public function test_accountant_can_set_custom_installment_amounts(): void
    {
        $class = $this->makeClass(4500000);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/fee-installments", [
                'amounts' => [2000000, 1500000, 1000000],
                'due_dates' => $this->defaultDueDates(),
            ]);

        $response->assertOk();
        $amounts = collect($response->json())->pluck('amount_cents')->all();
        $this->assertEquals([2000000, 1500000, 1000000], $amounts);
        $dueDates = collect($response->json())->pluck('due_date')->map(fn ($d) => substr($d, 0, 10))->all();
        $this->assertEquals($this->defaultDueDates(), $dueDates);
    }

    public function test_saving_a_plan_again_overwrites_the_existing_amounts_and_dates_in_place(): void
    {
        $class = $this->makeClass(4500000);
        $token = $this->tokenFor('accountant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/fee-installments", ['due_dates' => $this->defaultDueDates()])
            ->assertOk();

        $newDueDates = ['2027-01-01', '2027-02-01', '2027-03-01'];
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/fee-installments", [
                'amounts' => [1000000, 1000000, 2500000],
                'due_dates' => $newDueDates,
            ]);

        $response->assertOk();
        $this->assertDatabaseCount('class_fee_installments', 3);
        $amounts = collect($response->json())->pluck('amount_cents')->all();
        $this->assertEquals([1000000, 1000000, 2500000], $amounts);
        $dueDates = collect($response->json())->pluck('due_date')->map(fn ($d) => substr($d, 0, 10))->all();
        $this->assertEquals($newDueDates, $dueDates);
    }

    public function test_due_dates_are_required(): void
    {
        $class = $this->makeClass(4500000);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/fee-installments");

        $response->assertUnprocessable()->assertJsonValidationErrors(['due_dates']);
    }

    public function test_due_dates_must_be_in_chronological_order(): void
    {
        $class = $this->makeClass(4500000);
        $token = $this->tokenFor('accountant');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/fee-installments", [
                'due_dates' => ['2026-11-01', '2026-10-01', '2026-12-01'],
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('class_fee_installments', 0);
    }

    public function test_index_returns_empty_array_when_no_plan_is_set(): void
    {
        $class = $this->makeClass(4500000);
        $token = $this->tokenFor('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/classes/{$class->id}/fee-installments");

        $response->assertOk()->assertExactJson([]);
    }

    public function test_registrar_can_read_but_not_write_installment_plans(): void
    {
        $class = $this->makeClass(4500000);
        $token = $this->tokenFor('registrar');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/classes/{$class->id}/fee-installments")->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/classes/{$class->id}/fee-installments", ['due_dates' => $this->defaultDueDates()]);

        $response->assertForbidden();
    }
}
