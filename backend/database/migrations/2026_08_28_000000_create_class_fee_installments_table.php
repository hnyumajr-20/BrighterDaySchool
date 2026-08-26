<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_fee_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->bigInteger('amount_cents');
            $table->timestamps();

            $table->unique(['class_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_fee_installments');
    }
};
