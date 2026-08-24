<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20);
            $table->smallInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
        });

        DB::statement('ALTER TABLE timetable_slots ADD CONSTRAINT timetable_slots_day_of_week_check CHECK (day_of_week BETWEEN 1 AND 7)');
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
