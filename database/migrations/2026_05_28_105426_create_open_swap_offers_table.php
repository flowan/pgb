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
        Schema::create('open_swap_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('open_swap_request_id')->constrained('open_swap_requests')->cascadeOnDelete();
            $table->foreignId('offered_by_caregiver_id')->constrained('caregivers')->cascadeOnDelete();
            $table->foreignId('offered_schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->foreignId('offered_schedule_exception_id')->nullable()->constrained('schedule_exceptions')->nullOnDelete();
            $table->date('offered_date');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_swap_offers');
    }
};
