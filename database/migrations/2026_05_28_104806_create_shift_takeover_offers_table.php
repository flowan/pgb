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
        Schema::create('shift_takeover_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_exception_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->foreignId('offered_by_caregiver_id')->constrained('caregivers')->cascadeOnDelete();
            $table->string('status')->default('open');
            $table->foreignId('claimed_by_caregiver_id')->nullable()->constrained('caregivers')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('resulting_exception_id')->nullable()->constrained('schedule_exceptions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_takeover_offers');
    }
};
