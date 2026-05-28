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
        Schema::table('open_swap_requests', function (Blueprint $table) {
            $table->foreign('selected_offer_id')
                ->references('id')
                ->on('open_swap_offers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_swap_requests', function (Blueprint $table) {
            $table->dropForeign(['selected_offer_id']);
        });
    }
};
