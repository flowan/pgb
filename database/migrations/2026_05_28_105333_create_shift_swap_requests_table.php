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
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_caregiver_id')->constrained('caregivers')->cascadeOnDelete();
            $table->foreignId('requester_schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->foreignId('requester_schedule_exception_id')->nullable()->constrained('schedule_exceptions')->nullOnDelete();
            $table->date('requester_date');
            $table->foreignId('target_caregiver_id')->constrained('caregivers')->cascadeOnDelete();
            $table->foreignId('target_schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->foreignId('target_schedule_exception_id')->nullable()->constrained('schedule_exceptions')->nullOnDelete();
            $table->date('target_date');
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->jsonb('resulting_exception_ids')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
    }
};
