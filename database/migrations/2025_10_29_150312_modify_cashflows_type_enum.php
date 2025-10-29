<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cashflows', function (Blueprint $table) {
            DB::statement("ALTER TABLE cashflows MODIFY COLUMN type ENUM('income','expense','equity') NOT NULL");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashflows', function (Blueprint $table) {
            DB::statement("ALTER TABLE cashflows MODIFY COLUMN type ENUM('income','expense') NOT NULL");
        });
    }
};
