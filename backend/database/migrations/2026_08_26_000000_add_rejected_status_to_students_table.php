<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE students DROP CONSTRAINT students_status_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE students DROP CONSTRAINT students_status_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status IN ('pending', 'approved'))");
    }
};
