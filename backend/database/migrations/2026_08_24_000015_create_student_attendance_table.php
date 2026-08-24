<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late']);
            $table->enum('method', ['manual', 'rfid'])->default('manual');
            $table->foreignId('recorded_by')->nullable()->constrained('staff');

            $table->unique(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance');
    }
};
