<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('availability_slots', function (Blueprint $table) {
            $table->jsonb('claimed_dates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('availability_slots', function (Blueprint $table) {
            $table->dropColumn('claimed_dates');
        });
    }
};
