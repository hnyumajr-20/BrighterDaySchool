<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->string('name', 30);
            $table->smallInteger('sequence');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['upcoming', 'active', 'closed'])->default('upcoming');
        });

        DB::statement('ALTER TABLE semesters ADD CONSTRAINT semesters_sequence_check CHECK (sequence IN (1, 2))');
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
