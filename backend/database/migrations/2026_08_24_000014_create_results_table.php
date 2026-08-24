<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('period_id')->constrained('periods');
            $table->decimal('score', 5, 2);
            $table->foreignId('recorded_by')->nullable()->constrained('staff');

            $table->unique(['student_id', 'subject_id', 'period_id']);
        });

        DB::statement('ALTER TABLE results ADD CONSTRAINT results_score_check CHECK (score >= 0 AND score <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
