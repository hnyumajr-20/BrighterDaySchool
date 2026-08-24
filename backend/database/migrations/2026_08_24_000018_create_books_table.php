<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('author', 150)->nullable();
            $table->string('isbn', 20)->nullable();
            $table->smallInteger('copies_total')->default(1);
            $table->smallInteger('copies_available')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
