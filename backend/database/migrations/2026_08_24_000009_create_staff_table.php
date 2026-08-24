<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('staff_no', 30)->unique()->nullable();
            $table->string('full_name', 150);
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('email', 255)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('cv_path', 255)->nullable();
            $table->string('contact', 20)->nullable();
            $table->text('address')->nullable();
            $table->enum('staff_role', ['registrar', 'accountant', 'teacher', 'librarian']);
            $table->bigInteger('salary_cents')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
