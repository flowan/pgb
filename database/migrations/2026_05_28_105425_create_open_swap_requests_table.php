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
        Schema::create('open_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_exception_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->foreignId('requester_caregiver_id')->constrained('caregivers')->cascadeOnDelete();
            $table->string('status')->default('open');
            $table->unsignedBigInteger('selected_offer_id')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->jsonb('resulting_exception_ids')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_swap_requests');
    }
};
