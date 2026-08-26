<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->unique();
            $table->foreignId('student_id')->constrained('students');
            $table->enum('type', ['registration', 'tuition', 'other'])->default('other');
            $table->bigInteger('amount_cents');
            $table->text('note')->nullable();
            $table->enum('status', ['unpaid', 'paid', 'cancelled'])->default('unpaid');
            $table->enum('payment_method', ['cash', 'orange_money', 'lonestar_mtn'])->nullable();
            $table->string('gateway_transaction_id', 40)->nullable()->unique();
            $table->string('payer_phone', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff');
            $table->foreignId('confirmed_by')->nullable()->constrained('staff');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
