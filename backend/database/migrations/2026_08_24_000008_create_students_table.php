<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('admission_no', 30)->unique()->nullable();
            $table->string('full_name', 150);
            $table->date('dob');
            $table->enum('gender', ['male', 'female']);
            $table->string('email', 255)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_transfer_student')->default(false);
            $table->string('transcript_path', 255)->nullable();
            $table->string('contact', 20)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('parents');
            $table->foreignId('class_id')->nullable()->constrained('classes');
            $table->enum('status', ['pending', 'approved'])->default('pending');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
