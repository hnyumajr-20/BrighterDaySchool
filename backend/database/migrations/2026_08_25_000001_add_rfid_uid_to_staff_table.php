<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // Card UID for the Phase 9 RFID reader (PRD Section 9) —
            // added ahead of that phase so staff records already carry it
            // once the reader endpoint exists. Nullable: not every staff
            // member has a card issued yet.
            $table->string('rfid_uid', 64)->nullable()->unique()->after('cv_path');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('rfid_uid');
        });
    }
};
