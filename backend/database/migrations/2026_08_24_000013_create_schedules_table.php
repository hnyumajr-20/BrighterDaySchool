<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_subject_id')->constrained('class_subjects');
            $table->foreignId('timetable_slot_id')->constrained('timetable_slots');

            $table->unique(['timetable_slot_id', 'class_subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
