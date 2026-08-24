<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books');
            $table->foreignId('student_id')->constrained('students');
            $table->date('issued_at')->useCurrent();
            $table->date('due_date');
            $table->date('returned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_loans');
    }
};
