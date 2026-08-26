<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_transactions', function (Blueprint $table) {
            $table->foreignId('class_fee_installment_id')->nullable()->after('type')
                ->constrained('class_fee_installments')->nullOnDelete();

            $table->unique(['student_id', 'class_fee_installment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('fee_transactions', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'class_fee_installment_id']);
            $table->dropConstrainedForeignId('class_fee_installment_id');
        });
    }
};
