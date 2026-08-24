<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->string('name', 30);
            $table->smallInteger('sequence');
            $table->boolean('is_exam_period')->default(false);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['upcoming', 'active', 'closed'])->default('upcoming');
        });

        DB::statement('ALTER TABLE periods ADD CONSTRAINT periods_sequence_check CHECK (sequence IN (1, 2, 3))');
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
