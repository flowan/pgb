<?php

namespace Tests\Unit\Models;

use App\Enums\ShiftSwapRequestStatus;
use App\Models\Caregiver;
use App\Models\ShiftSwapRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftSwapRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_belongs_to_requester_and_target(): void
    {
        $requester = Caregiver::factory()->create();
        $target = Caregiver::factory()->create();

        $request = ShiftSwapRequest::factory()->create([
            'requester_caregiver_id' => $requester->id,
            'target_caregiver_id' => $target->id,
        ]);

        $this->assertInstanceOf(Caregiver::class, $request->requester);
        $this->assertTrue($request->requester->is($requester));
        $this->assertInstanceOf(Caregiver::class, $request->target);
        $this->assertTrue($request->target->is($target));
    }

    public function test_request_defaults_to_pending(): void
    {
        $request = ShiftSwapRequest::factory()->create();

        $this->assertSame(ShiftSwapRequestStatus::Pending, $request->status);
    }

    public function test_resulting_exception_ids_is_array_cast(): void
    {
        $request = ShiftSwapRequest::factory()->create([
            'resulting_exception_ids' => [1, 2],
        ]);

        $this->assertSame([1, 2], $request->fresh()->resulting_exception_ids);
    }
}
