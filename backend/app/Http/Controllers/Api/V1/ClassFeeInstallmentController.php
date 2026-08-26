<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassFeeInstallment;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassFeeInstallmentController extends Controller
{
    public function index(SchoolClass $class): JsonResponse
    {
        return response()->json(
            $class->classFeeInstallments()->orderBy('sequence')->get(),
        );
    }

    public function store(Request $request, SchoolClass $class): JsonResponse
    {
        $data = $request->validate([
            'amounts' => ['sometimes', 'array', 'size:3'],
            'amounts.*' => ['integer', 'min:1'],
            'due_dates' => ['required', 'array', 'size:3'],
            'due_dates.*' => ['date'],
        ]);

        $dueDates = array_values($data['due_dates']);
        for ($i = 1; $i < 3; $i++) {
            abort_if(
                $dueDates[$i] < $dueDates[$i - 1],
                422,
                'Installment due dates must be in order — installment '.($i + 1).' can\'t be due before installment '.$i.'.',
            );
        }

        $amounts = $data['amounts'] ?? $this->equalThirds($class->fee_amount_cents);

        $installments = collect($amounts)->values()->map(
            fn (int $amount, int $index) => ClassFeeInstallment::updateOrCreate(
                ['class_id' => $class->id, 'sequence' => $index + 1],
                ['amount_cents' => $amount, 'due_date' => $dueDates[$index]],
            ),
        );

        return response()->json($installments->sortBy('sequence')->values());
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function equalThirds(int $totalCents): array
    {
        $each = intdiv($totalCents, 3);

        return [$each, $each, $totalCents - ($each * 2)];
    }
}
