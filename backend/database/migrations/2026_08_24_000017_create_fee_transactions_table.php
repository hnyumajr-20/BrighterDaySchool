<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->bigInteger('amount_cents');
            $table->enum('type', ['charge', 'payment', 'discount', 'adjustment']);
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('staff');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_transactions');
    }
};
