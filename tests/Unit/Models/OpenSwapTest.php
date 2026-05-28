<?php

namespace Tests\Unit\Models;

use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenSwapTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_swap_request_has_offers(): void
    {
        $request = OpenSwapRequest::factory()->create();
        OpenSwapOffer::factory()->count(3)->create([
            'open_swap_request_id' => $request->id,
        ]);

        $this->assertCount(3, $request->offers);
        $this->assertInstanceOf(OpenSwapOffer::class, $request->offers->first());
    }

    public function test_open_swap_request_defaults_to_open(): void
    {
        $request = OpenSwapRequest::factory()->create();

        $this->assertSame(OpenSwapRequestStatus::Open, $request->status);
    }

    public function test_open_swap_offer_defaults_to_pending(): void
    {
        $offer = OpenSwapOffer::factory()->create();

        $this->assertSame(OpenSwapOfferStatus::Pending, $offer->status);
    }

    public function test_request_can_reference_selected_offer(): void
    {
        $request = OpenSwapRequest::factory()->create();
        $offer = OpenSwapOffer::factory()->create([
            'open_swap_request_id' => $request->id,
        ]);

        $request->update(['selected_offer_id' => $offer->id]);

        $this->assertInstanceOf(OpenSwapOffer::class, $request->fresh()->selectedOffer);
        $this->assertTrue($request->fresh()->selectedOffer->is($offer));
    }
}
