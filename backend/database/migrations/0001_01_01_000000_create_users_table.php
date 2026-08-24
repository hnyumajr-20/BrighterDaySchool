<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['admin', 'registrar', 'accountant', 'teacher', 'librarian', 'student', 'parent']);
            $table->string('username', 30)->unique();
            $table->string('email', 255)->unique()->nullable();
            $table->string('password_hash', 255);
            $table->boolean('must_change_password')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestampsTz();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
