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
        Schema::table('shift_takeover_offers', function (Blueprint $table) {
            $table->boolean('from_sickness')->default(false);
        });

        // Backfill: existing auto-created sickness offers can be detected by their notes
        \Illuminate\Support\Facades\DB::table('shift_takeover_offers')
            ->where('notes', 'Automatisch aangemaakt na ziekmelding')
            ->update(['from_sickness' => true]);
    }

    public function down(): void
    {
        Schema::table('shift_takeover_offers', function (Blueprint $table) {
            $table->dropColumn('from_sickness');
        });
    }
};
