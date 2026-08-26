<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_attendance_windows', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->timestampTz('check_in_opens_at');
            $table->timestampTz('check_in_closes_at');
            $table->timestampTz('check_out_opens_at');
            $table->timestampTz('check_out_closes_at');
            $table->foreignId('opened_by')->nullable()->constrained('staff');
            $table->timestampTz('absentees_marked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance_windows');
    }
};
