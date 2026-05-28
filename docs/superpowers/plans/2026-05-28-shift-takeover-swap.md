# Shift Takeover & Swap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Caregivers can see colleagues' shifts and propose takeover/swap changes via three flows with peer-to-peer approval and notifications.

**Architecture:** 4 new models (ShiftTakeoverOffer, ShiftSwapRequest, OpenSwapRequest, OpenSwapOffer) with status enums. Acceptance creates ScheduleException records of type `modified` — reuses existing rendering. Laravel built-in Notifications (database + mail channels). Daily scheduled command auto-expires past requests. Observers cascade-cancel when underlying shift is deleted.

**Tech Stack:** Laravel 13, Inertia.js, React, TypeScript, PostgreSQL, PHPUnit, Laravel Notifications

**Spec:** `docs/superpowers/specs/2026-05-28-shift-takeover-swap-design.md`

---

## Task Index

Phase 1 — Backend models:
- Task 1: ShiftTakeoverOffer model
- Task 2: ShiftSwapRequest model
- Task 3: OpenSwapRequest + OpenSwapOffer models

Phase 2 — Controllers & policies:
- Task 4: ShiftTakeoverOffer endpoints
- Task 5: ShiftSwapRequest endpoints
- Task 6: OpenSwapRequest endpoints

Phase 3 — Cross-cutting backend:
- Task 7: Observer updates + scheduled expiration command

Phase 4 — Notifications:
- Task 8: All notification classes + bell controller

Phase 5 — Frontend:
- Task 9: TypeScript types + colleague visibility on schedule
- Task 10: Notification bell component
- Task 11: Shift action dialogs + /my-requests page

---

(Plan continues — each task is detailed below. See individual task files referenced for full implementation steps.)

## Notes for the implementing agent

- Follow the existing patterns in the codebase exactly. Look at AvailabilitySlot model/controller/policy as the closest reference.
- All test files use PHPUnit (NOT Pest). Feature tests with Inertia rendering use `$this->withoutVite()` in `setUp()`.
- Frontend uses `useForm`, `router`, `Link` from `@inertiajs/react`. AppLayout is auto-applied via `app.tsx`. Pages use `.layout = (props) => ({ breadcrumbs: [...] })` for dynamic breadcrumbs.
- React TS types live in `resources/js/types/models.ts` re-exported via `resources/js/types/index.ts`.
- After every task: run `php artisan test`, build frontend with Node 22 (`source ~/.nvm/nvm.sh && nvm use 22 && npm run build`), commit.
- Schedule reassignment helper: when accepting any flow, create a `ScheduleException` of type `modified` with the NEW caregiver_id, the date, the original times, and the source schedule_id (if applicable). See helper `ShiftReassignmentService` defined in Task 4.

---

## Task 1: ShiftTakeoverOffer Model

**Files:**
- Create: `app/Enums/ShiftTakeoverOfferStatus.php`
- Create: `database/migrations/xxxx_create_shift_takeover_offers_table.php`
- Create: `app/Models/ShiftTakeoverOffer.php`
- Create: `database/factories/ShiftTakeoverOfferFactory.php`
- Modify: `app/Models/Caregiver.php` (add relationships)
- Modify: `app/Models/Schedule.php` (add hasMany)
- Modify: `app/Models/ScheduleException.php` (add hasMany)
- Create: `tests/Unit/Models/ShiftTakeoverOfferTest.php`

- [ ] **Step 1: Create the enum**

```php
<?php
// app/Enums/ShiftTakeoverOfferStatus.php
namespace App\Enums;

enum ShiftTakeoverOfferStatus: string
{
    case Open = 'open';
    case Claimed = 'claimed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
```

- [ ] **Step 2: Create migration**

```bash
php artisan make:model ShiftTakeoverOffer -mf
```

Edit the generated migration:

```php
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
```

- [ ] **Step 3: Write the model**

```php
<?php
// app/Models/ShiftTakeoverOffer.php
namespace App\Models;

use App\Enums\ShiftTakeoverOfferStatus;
use Database\Factories\ShiftTakeoverOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTakeoverOffer extends Model
{
    /** @use HasFactory<ShiftTakeoverOfferFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_id', 'schedule_exception_id', 'date',
        'offered_by_caregiver_id', 'status',
        'claimed_by_caregiver_id', 'claimed_at',
        'resulting_exception_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'claimed_at' => 'datetime',
            'status' => ShiftTakeoverOfferStatus::class,
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function scheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class);
    }

    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'offered_by_caregiver_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'claimed_by_caregiver_id');
    }

    public function resultingException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'resulting_exception_id');
    }
}
```

- [ ] **Step 4: Write factory**

```php
<?php
// database/factories/ShiftTakeoverOfferFactory.php
namespace Database\Factories;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\Caregiver;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftTakeoverOfferFactory extends Factory
{
    public function definition(): array
    {
        $schedule = Schedule::factory();
        return [
            'schedule_id' => $schedule,
            'schedule_exception_id' => null,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'offered_by_caregiver_id' => Caregiver::factory(),
            'status' => ShiftTakeoverOfferStatus::Open,
        ];
    }
}
```

- [ ] **Step 5: Add reverse relationships to existing models**

In `app/Models/Caregiver.php` add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function shiftTakeoverOffersOffered(): HasMany
{
    return $this->hasMany(ShiftTakeoverOffer::class, 'offered_by_caregiver_id');
}

public function shiftTakeoverOffersClaimed(): HasMany
{
    return $this->hasMany(ShiftTakeoverOffer::class, 'claimed_by_caregiver_id');
}
```

In `app/Models/Schedule.php` add:

```php
public function takeoverOffers(): HasMany
{
    return $this->hasMany(ShiftTakeoverOffer::class);
}
```

In `app/Models/ScheduleException.php` add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function takeoverOffers(): HasMany
{
    return $this->hasMany(ShiftTakeoverOffer::class);
}
```

- [ ] **Step 6: Write tests**

```php
<?php
// tests/Unit/Models/ShiftTakeoverOfferTest.php
namespace Tests\Unit\Models;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTakeoverOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_belongs_to_schedule_and_offerer(): void
    {
        $schedule = Schedule::factory()->create();
        $caregiver = Caregiver::factory()->create();
        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => $schedule->id,
            'offered_by_caregiver_id' => $caregiver->id,
        ]);

        $this->assertEquals($schedule->id, $offer->schedule->id);
        $this->assertEquals($caregiver->id, $offer->offeredBy->id);
    }

    public function test_offer_defaults_to_open_status(): void
    {
        $offer = ShiftTakeoverOffer::factory()->create();
        $this->assertEquals(ShiftTakeoverOfferStatus::Open, $offer->status);
    }

    public function test_offer_casts_date_and_status(): void
    {
        $offer = ShiftTakeoverOffer::factory()->create();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $offer->date);
        $this->assertInstanceOf(ShiftTakeoverOfferStatus::class, $offer->status);
    }
}
```

- [ ] **Step 7: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/ShiftTakeoverOfferTest.php
```

Expected: 3 tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Enums/ShiftTakeoverOfferStatus.php app/Models/ShiftTakeoverOffer.php app/Models/Caregiver.php app/Models/Schedule.php app/Models/ScheduleException.php database/migrations/*shift_takeover_offers* database/factories/ShiftTakeoverOfferFactory.php tests/Unit/Models/ShiftTakeoverOfferTest.php
git commit -m "feat: add ShiftTakeoverOffer model with enum, migration, factory"
```

---

## Task 2: ShiftSwapRequest Model

**Files:**
- Create: `app/Enums/ShiftSwapRequestStatus.php`
- Create: `database/migrations/xxxx_create_shift_swap_requests_table.php`
- Create: `app/Models/ShiftSwapRequest.php`
- Create: `database/factories/ShiftSwapRequestFactory.php`
- Create: `tests/Unit/Models/ShiftSwapRequestTest.php`

- [ ] **Step 1: Create the enum**

```php
<?php
// app/Enums/ShiftSwapRequestStatus.php
namespace App\Enums;

enum ShiftSwapRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
```

- [ ] **Step 2: Migration & model scaffolding**

```bash
php artisan make:model ShiftSwapRequest -mf
```

Edit migration:

```php
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
```

- [ ] **Step 3: Write model**

```php
<?php
// app/Models/ShiftSwapRequest.php
namespace App\Models;

use App\Enums\ShiftSwapRequestStatus;
use Database\Factories\ShiftSwapRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwapRequest extends Model
{
    /** @use HasFactory<ShiftSwapRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'requester_caregiver_id', 'requester_schedule_id', 'requester_schedule_exception_id', 'requester_date',
        'target_caregiver_id', 'target_schedule_id', 'target_schedule_exception_id', 'target_date',
        'status', 'responded_at', 'decline_reason', 'resulting_exception_ids',
    ];

    protected function casts(): array
    {
        return [
            'requester_date' => 'date',
            'target_date' => 'date',
            'responded_at' => 'datetime',
            'status' => ShiftSwapRequestStatus::class,
            'resulting_exception_ids' => 'array',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'requester_caregiver_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'target_caregiver_id');
    }

    public function requesterSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'requester_schedule_id');
    }

    public function requesterScheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'requester_schedule_exception_id');
    }

    public function targetSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'target_schedule_id');
    }

    public function targetScheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'target_schedule_exception_id');
    }
}
```

- [ ] **Step 4: Factory**

```php
<?php
// database/factories/ShiftSwapRequestFactory.php
namespace Database\Factories;

use App\Enums\ShiftSwapRequestStatus;
use App\Models\Caregiver;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftSwapRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'requester_caregiver_id' => Caregiver::factory(),
            'requester_schedule_id' => Schedule::factory(),
            'requester_date' => now()->addDays(5)->format('Y-m-d'),
            'target_caregiver_id' => Caregiver::factory(),
            'target_schedule_id' => Schedule::factory(),
            'target_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => ShiftSwapRequestStatus::Pending,
        ];
    }
}
```

- [ ] **Step 5: Tests**

```php
<?php
// tests/Unit/Models/ShiftSwapRequestTest.php
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
        $a = Caregiver::factory()->create();
        $b = Caregiver::factory()->create();
        $req = ShiftSwapRequest::factory()->create([
            'requester_caregiver_id' => $a->id,
            'target_caregiver_id' => $b->id,
        ]);

        $this->assertEquals($a->id, $req->requester->id);
        $this->assertEquals($b->id, $req->target->id);
    }

    public function test_request_defaults_to_pending(): void
    {
        $req = ShiftSwapRequest::factory()->create();
        $this->assertEquals(ShiftSwapRequestStatus::Pending, $req->status);
    }

    public function test_resulting_exception_ids_is_array_cast(): void
    {
        $req = ShiftSwapRequest::factory()->create(['resulting_exception_ids' => [1, 2]]);
        $this->assertEquals([1, 2], $req->fresh()->resulting_exception_ids);
    }
}
```

- [ ] **Step 6: Run migrate + tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/ShiftSwapRequestTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Enums/ShiftSwapRequestStatus.php app/Models/ShiftSwapRequest.php database/migrations/*shift_swap_requests* database/factories/ShiftSwapRequestFactory.php tests/Unit/Models/ShiftSwapRequestTest.php
git commit -m "feat: add ShiftSwapRequest model with enum, migration, factory"
```

---

## Task 3: OpenSwapRequest + OpenSwapOffer Models

**Files:**
- Create: `app/Enums/OpenSwapRequestStatus.php`
- Create: `app/Enums/OpenSwapOfferStatus.php`
- Create: 2 migrations
- Create: `app/Models/OpenSwapRequest.php`
- Create: `app/Models/OpenSwapOffer.php`
- Create: 2 factories
- Create: `tests/Unit/Models/OpenSwapTest.php`

- [ ] **Step 1: Create enums**

```php
<?php
// app/Enums/OpenSwapRequestStatus.php
namespace App\Enums;

enum OpenSwapRequestStatus: string
{
    case Open = 'open';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
```

```php
<?php
// app/Enums/OpenSwapOfferStatus.php
namespace App\Enums;

enum OpenSwapOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
```

- [ ] **Step 2: Generate models and migrations**

```bash
php artisan make:model OpenSwapRequest -mf
php artisan make:model OpenSwapOffer -mf
```

Edit the OpenSwapRequest migration (must come BEFORE the Offer migration since Offer references it; but OpenSwapRequest also references Offer via selected_offer_id which is added AFTER Offer is created — use a follow-up migration or nullable without constraint at creation, then add constraint later). Simplest: create both tables WITHOUT the back-reference foreign key, then create a third migration that adds the `selected_offer_id` FK.

Edit `xxxx_create_open_swap_requests_table.php`:

```php
public function up(): void
{
    Schema::create('open_swap_requests', function (Blueprint $table) {
        $table->id();
        $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('schedule_exception_id')->nullable()->constrained()->nullOnDelete();
        $table->date('date');
        $table->foreignId('requester_caregiver_id')->constrained('caregivers')->cascadeOnDelete();
        $table->string('status')->default('open');
        $table->unsignedBigInteger('selected_offer_id')->nullable(); // FK added in later migration
        $table->timestamp('fulfilled_at')->nullable();
        $table->jsonb('resulting_exception_ids')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}
```

Edit `xxxx_create_open_swap_offers_table.php`:

```php
public function up(): void
{
    Schema::create('open_swap_offers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('open_swap_request_id')->constrained()->cascadeOnDelete();
        $table->foreignId('offered_by_caregiver_id')->constrained('caregivers')->cascadeOnDelete();
        $table->foreignId('offered_schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
        $table->foreignId('offered_schedule_exception_id')->nullable()->constrained('schedule_exceptions')->nullOnDelete();
        $table->date('offered_date');
        $table->string('status')->default('pending');
        $table->timestamps();
    });
}
```

Create a third migration `php artisan make:migration add_selected_offer_fk_to_open_swap_requests_table --table=open_swap_requests` and edit:

```php
public function up(): void
{
    Schema::table('open_swap_requests', function (Blueprint $table) {
        $table->foreign('selected_offer_id')->references('id')->on('open_swap_offers')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('open_swap_requests', function (Blueprint $table) {
        $table->dropForeign(['selected_offer_id']);
    });
}
```

- [ ] **Step 3: Models**

```php
<?php
// app/Models/OpenSwapRequest.php
namespace App\Models;

use App\Enums\OpenSwapRequestStatus;
use Database\Factories\OpenSwapRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenSwapRequest extends Model
{
    /** @use HasFactory<OpenSwapRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_id', 'schedule_exception_id', 'date',
        'requester_caregiver_id', 'status', 'selected_offer_id',
        'fulfilled_at', 'resulting_exception_ids', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'fulfilled_at' => 'datetime',
            'status' => OpenSwapRequestStatus::class,
            'resulting_exception_ids' => 'array',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function scheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'requester_caregiver_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(OpenSwapOffer::class);
    }

    public function selectedOffer(): BelongsTo
    {
        return $this->belongsTo(OpenSwapOffer::class, 'selected_offer_id');
    }
}
```

```php
<?php
// app/Models/OpenSwapOffer.php
namespace App\Models;

use App\Enums\OpenSwapOfferStatus;
use Database\Factories\OpenSwapOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenSwapOffer extends Model
{
    /** @use HasFactory<OpenSwapOfferFactory> */
    use HasFactory;

    protected $fillable = [
        'open_swap_request_id', 'offered_by_caregiver_id',
        'offered_schedule_id', 'offered_schedule_exception_id',
        'offered_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'offered_date' => 'date',
            'status' => OpenSwapOfferStatus::class,
        ];
    }

    public function openSwapRequest(): BelongsTo
    {
        return $this->belongsTo(OpenSwapRequest::class);
    }

    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'offered_by_caregiver_id');
    }

    public function offeredSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'offered_schedule_id');
    }

    public function offeredScheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'offered_schedule_exception_id');
    }
}
```

- [ ] **Step 4: Factories**

```php
<?php
// database/factories/OpenSwapRequestFactory.php
namespace Database\Factories;

use App\Enums\OpenSwapRequestStatus;
use App\Models\Caregiver;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpenSwapRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'schedule_exception_id' => null,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'requester_caregiver_id' => Caregiver::factory(),
            'status' => OpenSwapRequestStatus::Open,
        ];
    }
}
```

```php
<?php
// database/factories/OpenSwapOfferFactory.php
namespace Database\Factories;

use App\Enums\OpenSwapOfferStatus;
use App\Models\Caregiver;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpenSwapOfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'open_swap_request_id' => OpenSwapRequest::factory(),
            'offered_by_caregiver_id' => Caregiver::factory(),
            'offered_schedule_id' => Schedule::factory(),
            'offered_schedule_exception_id' => null,
            'offered_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => OpenSwapOfferStatus::Pending,
        ];
    }
}
```

- [ ] **Step 5: Tests**

```php
<?php
// tests/Unit/Models/OpenSwapTest.php
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
        $req = OpenSwapRequest::factory()->create();
        OpenSwapOffer::factory()->count(3)->create(['open_swap_request_id' => $req->id]);
        $this->assertCount(3, $req->offers);
    }

    public function test_open_swap_request_defaults_to_open(): void
    {
        $req = OpenSwapRequest::factory()->create();
        $this->assertEquals(OpenSwapRequestStatus::Open, $req->status);
    }

    public function test_open_swap_offer_defaults_to_pending(): void
    {
        $offer = OpenSwapOffer::factory()->create();
        $this->assertEquals(OpenSwapOfferStatus::Pending, $offer->status);
    }

    public function test_request_can_reference_selected_offer(): void
    {
        $req = OpenSwapRequest::factory()->create();
        $offer = OpenSwapOffer::factory()->create(['open_swap_request_id' => $req->id]);
        $req->update(['selected_offer_id' => $offer->id]);
        $this->assertEquals($offer->id, $req->fresh()->selectedOffer->id);
    }
}
```

- [ ] **Step 6: Migrate and test**

```bash
php artisan migrate
php artisan test tests/Unit/Models/OpenSwapTest.php
php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add app/Enums/OpenSwap*.php app/Models/OpenSwap*.php database/migrations/*open_swap* database/factories/OpenSwap*Factory.php tests/Unit/Models/OpenSwapTest.php
git commit -m "feat: add OpenSwapRequest and OpenSwapOffer models"
```

---

## Task 4: ShiftTakeoverOffer Endpoints

**Files:**
- Create: `app/Services/ShiftReassignmentService.php`
- Create: `app/Policies/ShiftTakeoverOfferPolicy.php`
- Create: `app/Http/Requests/ShiftTakeoverOfferRequest.php`
- Create: `app/Http/Controllers/ShiftTakeoverOfferController.php`
- Create: `app/Http/Controllers/ShiftTakeoverClaimController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/ShiftTakeoverOfferTest.php`

- [ ] **Step 1: Create ShiftReassignmentService (reused across all flows)**

```php
<?php
// app/Services/ShiftReassignmentService.php
namespace App\Services;

use App\Enums\ScheduleExceptionType;
use App\Models\Schedule;
use App\Models\ScheduleException;

class ShiftReassignmentService
{
    /**
     * Create a 'modified' exception that reassigns a shift to a new caregiver.
     * Source can be a Schedule (recurring) or ScheduleException (one-time).
     * Returns the created ScheduleException.
     */
    public function reassign(
        Schedule|ScheduleException $source,
        string $date,
        int $newCaregiverId,
    ): ScheduleException {
        if ($source instanceof Schedule) {
            return ScheduleException::create([
                'schedule_id' => $source->id,
                'client_id' => $source->client_id,
                'caregiver_id' => $newCaregiverId,
                'date' => $date,
                'start_time' => $source->start_time,
                'end_time' => $source->end_time,
                'type' => ScheduleExceptionType::Modified,
            ]);
        }

        // It's a ScheduleException — create a new one that supersedes it
        return ScheduleException::create([
            'schedule_id' => $source->schedule_id,
            'client_id' => $source->client_id,
            'caregiver_id' => $newCaregiverId,
            'date' => $date,
            'start_time' => $source->start_time,
            'end_time' => $source->end_time,
            'type' => ScheduleExceptionType::Modified,
        ]);
    }
}
```

- [ ] **Step 2: Create policy**

```php
<?php
// app/Policies/ShiftTakeoverOfferPolicy.php
namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\ShiftTakeoverOffer;
use App\Models\User;

class ShiftTakeoverOfferPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Caregiver;
    }

    public function delete(User $user, ShiftTakeoverOffer $offer): bool
    {
        return $offer->offeredBy->user_id === $user->id;
    }

    public function claim(User $user, ShiftTakeoverOffer $offer): bool
    {
        if ($user->role !== UserRole::Caregiver) return false;
        // Must be a caregiver of the same client, and not the offerer
        $sourceClientId = $offer->schedule?->client_id ?? $offer->scheduleException?->client_id;
        if (!$sourceClientId) return false;
        $isColleague = Caregiver::where('user_id', $user->id)
            ->where('client_id', $sourceClientId)
            ->exists();
        return $isColleague && $offer->offeredBy->user_id !== $user->id;
    }
}
```

- [ ] **Step 3: Create form request**

```php
<?php
// app/Http/Requests/ShiftTakeoverOfferRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftTakeoverOfferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'schedule_id' => ['nullable', 'exists:schedules,id', 'required_without:schedule_exception_id'],
            'schedule_exception_id' => ['nullable', 'exists:schedule_exceptions,id', 'required_without:schedule_id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create offer controller (store + destroy)**

```php
<?php
// app/Http/Controllers/ShiftTakeoverOfferController.php
namespace App\Http\Controllers;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Enums\UserRole;
use App\Http\Requests\ShiftTakeoverOfferRequest;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ShiftTakeoverOfferController extends Controller
{
    public function store(ShiftTakeoverOfferRequest $request): RedirectResponse
    {
        $this->authorize('create', ShiftTakeoverOffer::class);
        $user = auth()->user();

        $clientId = $this->resolveClientId($request);
        $caregiver = Caregiver::where('user_id', $user->id)->where('client_id', $clientId)->first();
        if (!$caregiver) abort(403);

        // No duplicate open offer for the same shift+date
        $exists = ShiftTakeoverOffer::where('status', ShiftTakeoverOfferStatus::Open)
            ->where('date', $request->date)
            ->where(function ($q) use ($request) {
                $q->where('schedule_id', $request->schedule_id)
                  ->orWhere('schedule_exception_id', $request->schedule_exception_id);
            })
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['shift' => 'Er staat al een aanbod open voor deze shift.']);
        }

        $offer = ShiftTakeoverOffer::create([
            ...$request->validated(),
            'offered_by_caregiver_id' => $caregiver->id,
        ]);

        \App\Notifications\ShiftTakeoverOffered::notifyColleagues($offer);

        return back();
    }

    public function destroy(ShiftTakeoverOffer $offer): RedirectResponse
    {
        $this->authorize('delete', $offer);
        if ($offer->status !== ShiftTakeoverOfferStatus::Open) {
            throw ValidationException::withMessages(['status' => 'Alleen open aanbod kan worden ingetrokken.']);
        }
        $offer->update(['status' => ShiftTakeoverOfferStatus::Cancelled]);
        return back();
    }

    private function resolveClientId(ShiftTakeoverOfferRequest $request): int
    {
        if ($request->schedule_id) {
            return Schedule::findOrFail($request->schedule_id)->client_id;
        }
        return ScheduleException::findOrFail($request->schedule_exception_id)->client_id;
    }
}
```

- [ ] **Step 5: Create claim controller**

```php
<?php
// app/Http/Controllers/ShiftTakeoverClaimController.php
namespace App\Http\Controllers;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\Caregiver;
use App\Models\ShiftTakeoverOffer;
use App\Services\ShiftReassignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ShiftTakeoverClaimController extends Controller
{
    public function __construct(private ShiftReassignmentService $reassign) {}

    public function store(ShiftTakeoverOffer $offer): RedirectResponse
    {
        $this->authorize('claim', $offer);

        if ($offer->status !== ShiftTakeoverOfferStatus::Open) {
            throw ValidationException::withMessages(['status' => 'Dit aanbod is niet meer beschikbaar.']);
        }

        $clientId = $offer->schedule?->client_id ?? $offer->scheduleException->client_id;
        $claimer = Caregiver::where('user_id', auth()->id())
            ->where('client_id', $clientId)
            ->firstOrFail();

        $source = $offer->schedule ?? $offer->scheduleException;
        $exception = $this->reassign->reassign($source, $offer->date->format('Y-m-d'), $claimer->id);

        $offer->update([
            'status' => ShiftTakeoverOfferStatus::Claimed,
            'claimed_by_caregiver_id' => $claimer->id,
            'claimed_at' => now(),
            'resulting_exception_id' => $exception->id,
        ]);

        \App\Notifications\ShiftTakenOver::notify($offer);

        return back();
    }
}
```

- [ ] **Step 6: Add routes**

In `routes/web.php` add inside an existing `['auth', 'verified', 'role:caregiver']` group (or create one):

```php
use App\Http\Controllers\ShiftTakeoverOfferController;
use App\Http\Controllers\ShiftTakeoverClaimController;

Route::middleware(['auth', 'verified', 'role:caregiver'])->group(function () {
    Route::post('/shift-takeover-offers', [ShiftTakeoverOfferController::class, 'store'])->name('shift-takeover-offers.store');
    Route::delete('/shift-takeover-offers/{offer}', [ShiftTakeoverOfferController::class, 'destroy'])->name('shift-takeover-offers.destroy');
    Route::post('/shift-takeover-offers/{offer}/claim', [ShiftTakeoverClaimController::class, 'store'])->name('shift-takeover-offers.claim');
});
```

- [ ] **Step 7: Tests** (covers create, claim flow, ownership, duplicates)

```php
<?php
// tests/Feature/ShiftTakeoverOfferTest.php
namespace Tests\Feature;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ShiftTakeoverOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTakeoverOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_caregiver_can_offer_own_shift(): void
    {
        [$user, $caregiver, $client] = $this->setupCaregiver();
        $schedule = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $caregiver->id]);

        $response = $this->actingAs($user)->post('/shift-takeover-offers', [
            'schedule_id' => $schedule->id,
            'date' => '2026-06-15',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_takeover_offers', [
            'schedule_id' => $schedule->id,
            'offered_by_caregiver_id' => $caregiver->id,
            'status' => 'open',
        ]);
    }

    public function test_colleague_can_claim_offer_and_creates_exception(): void
    {
        [$userA, $caregiverA, $client] = $this->setupCaregiver();
        [$userB, $caregiverB] = $this->setupCaregiver($client);
        $schedule = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $caregiverA->id]);
        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => $schedule->id,
            'offered_by_caregiver_id' => $caregiverA->id,
            'date' => '2026-06-15',
        ]);

        $response = $this->actingAs($userB)->post("/shift-takeover-offers/{$offer->id}/claim");

        $response->assertRedirect();
        $offer->refresh();
        $this->assertEquals(ShiftTakeoverOfferStatus::Claimed, $offer->status);
        $this->assertEquals($caregiverB->id, $offer->claimed_by_caregiver_id);
        $this->assertDatabaseHas('schedule_exceptions', [
            'schedule_id' => $schedule->id,
            'caregiver_id' => $caregiverB->id,
            'date' => '2026-06-15',
            'type' => 'modified',
        ]);
    }

    public function test_offerer_cannot_claim_own_offer(): void
    {
        [$user, $caregiver, $client] = $this->setupCaregiver();
        $schedule = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $caregiver->id]);
        $offer = ShiftTakeoverOffer::factory()->create([
            'schedule_id' => $schedule->id,
            'offered_by_caregiver_id' => $caregiver->id,
        ]);

        $response = $this->actingAs($user)->post("/shift-takeover-offers/{$offer->id}/claim");
        $response->assertForbidden();
    }

    public function test_offerer_can_cancel_own_offer(): void
    {
        [$user, $caregiver, $client] = $this->setupCaregiver();
        $offer = ShiftTakeoverOffer::factory()->create([
            'offered_by_caregiver_id' => $caregiver->id,
        ]);

        $response = $this->actingAs($user)->delete("/shift-takeover-offers/{$offer->id}");

        $response->assertRedirect();
        $this->assertEquals(ShiftTakeoverOfferStatus::Cancelled, $offer->fresh()->status);
    }

    public function test_cannot_claim_already_claimed_offer(): void
    {
        [$userA, $caregiverA, $client] = $this->setupCaregiver();
        [$userB, $caregiverB] = $this->setupCaregiver($client);
        $offer = ShiftTakeoverOffer::factory()->create([
            'offered_by_caregiver_id' => $caregiverA->id,
            'status' => ShiftTakeoverOfferStatus::Claimed,
        ]);

        $response = $this->actingAs($userB)->post("/shift-takeover-offers/{$offer->id}/claim");
        $response->assertSessionHasErrors('status');
    }

    private function setupCaregiver(?Client $client = null): array
    {
        $user = User::factory()->create(['role' => UserRole::Caregiver]);
        $client ??= Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);
        return [$user, $caregiver, $client];
    }
}
```

Note: Tests reference `\App\Notifications\ShiftTakeoverOffered` and `ShiftTakenOver` which don't exist yet (Task 8). For now, replace those notify calls in the controllers with empty stubs (`// TODO notify in Task 8`) OR use `Notification::fake()` to swallow them. Use **stubs** approach to keep tasks isolated: change the controllers to:

```php
// in store():
// notify will be added in Task 8

// in claim():
// notify will be added in Task 8
```

Remove the `\App\Notifications\ShiftTakeoverOffered::notifyColleagues($offer);` and `\App\Notifications\ShiftTakenOver::notify($offer);` lines for now. Task 8 will re-add them.

- [ ] **Step 8: Run tests and commit**

```bash
php artisan test
git add app/Services app/Policies/ShiftTakeoverOfferPolicy.php app/Http/Requests/ShiftTakeoverOfferRequest.php app/Http/Controllers/ShiftTakeoverOfferController.php app/Http/Controllers/ShiftTakeoverClaimController.php routes/web.php tests/Feature/ShiftTakeoverOfferTest.php
git commit -m "feat: add ShiftTakeoverOffer endpoints with claim flow"
```

---

## Task 5: ShiftSwapRequest Endpoints

**Files:**
- Create: `app/Policies/ShiftSwapRequestPolicy.php`
- Create: `app/Http/Requests/ShiftSwapRequestRequest.php`
- Create: `app/Http/Controllers/ShiftSwapRequestController.php`
- Create: `app/Http/Controllers/ShiftSwapResponseController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/ShiftSwapRequestTest.php`

- [ ] **Step 1: Policy**

```php
<?php
// app/Policies/ShiftSwapRequestPolicy.php
namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ShiftSwapRequest;
use App\Models\User;

class ShiftSwapRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Caregiver;
    }

    public function delete(User $user, ShiftSwapRequest $request): bool
    {
        return $request->requester->user_id === $user->id;
    }

    public function respond(User $user, ShiftSwapRequest $request): bool
    {
        return $request->target->user_id === $user->id;
    }
}
```

- [ ] **Step 2: Form request**

```php
<?php
// app/Http/Requests/ShiftSwapRequestRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShiftSwapRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'requester_schedule_id' => ['nullable', 'exists:schedules,id', 'required_without:requester_schedule_exception_id'],
            'requester_schedule_exception_id' => ['nullable', 'exists:schedule_exceptions,id', 'required_without:requester_schedule_id'],
            'requester_date' => ['required', 'date'],
            'target_caregiver_id' => ['required', 'exists:caregivers,id'],
            'target_schedule_id' => ['nullable', 'exists:schedules,id', 'required_without:target_schedule_exception_id'],
            'target_schedule_exception_id' => ['nullable', 'exists:schedule_exceptions,id', 'required_without:target_schedule_id'],
            'target_date' => ['required', 'date'],
        ];
    }
}
```

- [ ] **Step 3: Request controller**

```php
<?php
// app/Http/Controllers/ShiftSwapRequestController.php
namespace App\Http\Controllers;

use App\Enums\ShiftSwapRequestStatus;
use App\Http\Requests\ShiftSwapRequestRequest;
use App\Models\Caregiver;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftSwapRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ShiftSwapRequestController extends Controller
{
    public function store(ShiftSwapRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', ShiftSwapRequest::class);
        $user = auth()->user();

        // Determine requester caregiver based on the source shift's client
        $requesterClientId = $this->shiftClientId(
            $request->requester_schedule_id,
            $request->requester_schedule_exception_id
        );
        $requesterCaregiver = Caregiver::where('user_id', $user->id)
            ->where('client_id', $requesterClientId)
            ->first();
        if (!$requesterCaregiver) abort(403);

        // Target must belong to same client
        $target = Caregiver::findOrFail($request->target_caregiver_id);
        if ($target->client_id !== $requesterClientId) {
            throw ValidationException::withMessages(['target_caregiver_id' => 'Target moet bij dezelfde cliënt horen.']);
        }

        $swap = ShiftSwapRequest::create([
            ...$request->validated(),
            'requester_caregiver_id' => $requesterCaregiver->id,
            'status' => ShiftSwapRequestStatus::Pending,
        ]);

        // notify will be added in Task 8

        return back();
    }

    public function destroy(ShiftSwapRequest $request): RedirectResponse
    {
        $this->authorize('delete', $request);
        if ($request->status !== ShiftSwapRequestStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Alleen open verzoeken kunnen worden ingetrokken.']);
        }
        $request->update(['status' => ShiftSwapRequestStatus::Cancelled]);
        return back();
    }

    private function shiftClientId(?int $scheduleId, ?int $exceptionId): int
    {
        if ($scheduleId) return Schedule::findOrFail($scheduleId)->client_id;
        return ScheduleException::findOrFail($exceptionId)->client_id;
    }
}
```

- [ ] **Step 4: Response controller (accept + decline)**

```php
<?php
// app/Http/Controllers/ShiftSwapResponseController.php
namespace App\Http\Controllers;

use App\Enums\ShiftSwapRequestStatus;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\ShiftSwapRequest;
use App\Services\ShiftReassignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftSwapResponseController extends Controller
{
    public function __construct(private ShiftReassignmentService $reassign) {}

    public function accept(ShiftSwapRequest $shiftSwapRequest): RedirectResponse
    {
        $this->authorize('respond', $shiftSwapRequest);
        $this->ensurePending($shiftSwapRequest);

        $requesterSource = $shiftSwapRequest->requester_schedule_id
            ? Schedule::find($shiftSwapRequest->requester_schedule_id)
            : ScheduleException::find($shiftSwapRequest->requester_schedule_exception_id);
        $targetSource = $shiftSwapRequest->target_schedule_id
            ? Schedule::find($shiftSwapRequest->target_schedule_id)
            : ScheduleException::find($shiftSwapRequest->target_schedule_exception_id);

        $ex1 = $this->reassign->reassign(
            $requesterSource,
            $shiftSwapRequest->requester_date->format('Y-m-d'),
            $shiftSwapRequest->target_caregiver_id,
        );
        $ex2 = $this->reassign->reassign(
            $targetSource,
            $shiftSwapRequest->target_date->format('Y-m-d'),
            $shiftSwapRequest->requester_caregiver_id,
        );

        $shiftSwapRequest->update([
            'status' => ShiftSwapRequestStatus::Accepted,
            'responded_at' => now(),
            'resulting_exception_ids' => [$ex1->id, $ex2->id],
        ]);

        // notify in Task 8

        return back();
    }

    public function decline(Request $request, ShiftSwapRequest $shiftSwapRequest): RedirectResponse
    {
        $this->authorize('respond', $shiftSwapRequest);
        $this->ensurePending($shiftSwapRequest);

        $validated = $request->validate(['decline_reason' => 'nullable|string']);

        $shiftSwapRequest->update([
            'status' => ShiftSwapRequestStatus::Declined,
            'responded_at' => now(),
            'decline_reason' => $validated['decline_reason'] ?? null,
        ]);

        // notify in Task 8

        return back();
    }

    private function ensurePending(ShiftSwapRequest $r): void
    {
        if ($r->status !== ShiftSwapRequestStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Dit verzoek is niet meer open.']);
        }
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/web.php` inside the `role:caregiver` group:

```php
use App\Http\Controllers\ShiftSwapRequestController;
use App\Http\Controllers\ShiftSwapResponseController;

Route::post('/shift-swap-requests', [ShiftSwapRequestController::class, 'store'])->name('shift-swap-requests.store');
Route::delete('/shift-swap-requests/{shiftSwapRequest}', [ShiftSwapRequestController::class, 'destroy'])->name('shift-swap-requests.destroy');
Route::post('/shift-swap-requests/{shiftSwapRequest}/accept', [ShiftSwapResponseController::class, 'accept'])->name('shift-swap-requests.accept');
Route::post('/shift-swap-requests/{shiftSwapRequest}/decline', [ShiftSwapResponseController::class, 'decline'])->name('shift-swap-requests.decline');
```

- [ ] **Step 6: Tests**

```php
<?php
// tests/Feature/ShiftSwapRequestTest.php
namespace Tests\Feature;

use App\Enums\ShiftSwapRequestStatus;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftSwapRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_caregiver_creates_direct_swap_request(): void
    {
        [$userA, $cgA, $client] = $this->setup();
        [$userB, $cgB] = $this->setup($client);
        $schedA = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $cgA->id]);
        $schedB = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $cgB->id]);

        $response = $this->actingAs($userA)->post('/shift-swap-requests', [
            'requester_schedule_id' => $schedA->id,
            'requester_date' => '2026-06-10',
            'target_caregiver_id' => $cgB->id,
            'target_schedule_id' => $schedB->id,
            'target_date' => '2026-06-12',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_swap_requests', [
            'requester_caregiver_id' => $cgA->id,
            'target_caregiver_id' => $cgB->id,
            'status' => 'pending',
        ]);
    }

    public function test_target_can_accept_and_creates_two_exceptions(): void
    {
        [$userA, $cgA, $client] = $this->setup();
        [$userB, $cgB] = $this->setup($client);
        $schedA = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $cgA->id]);
        $schedB = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $cgB->id]);
        $swap = ShiftSwapRequest::factory()->create([
            'requester_caregiver_id' => $cgA->id, 'requester_schedule_id' => $schedA->id, 'requester_date' => '2026-06-10',
            'target_caregiver_id' => $cgB->id, 'target_schedule_id' => $schedB->id, 'target_date' => '2026-06-12',
        ]);

        $response = $this->actingAs($userB)->post("/shift-swap-requests/{$swap->id}/accept");

        $response->assertRedirect();
        $swap->refresh();
        $this->assertEquals(ShiftSwapRequestStatus::Accepted, $swap->status);
        $this->assertCount(2, $swap->resulting_exception_ids);
        $this->assertDatabaseHas('schedule_exceptions', ['schedule_id' => $schedA->id, 'caregiver_id' => $cgB->id, 'date' => '2026-06-10']);
        $this->assertDatabaseHas('schedule_exceptions', ['schedule_id' => $schedB->id, 'caregiver_id' => $cgA->id, 'date' => '2026-06-12']);
    }

    public function test_target_can_decline(): void
    {
        [$userA, $cgA, $client] = $this->setup();
        [$userB, $cgB] = $this->setup($client);
        $swap = ShiftSwapRequest::factory()->create([
            'requester_caregiver_id' => $cgA->id,
            'target_caregiver_id' => $cgB->id,
        ]);

        $response = $this->actingAs($userB)->post("/shift-swap-requests/{$swap->id}/decline", ['decline_reason' => 'Niet beschikbaar']);

        $response->assertRedirect();
        $swap->refresh();
        $this->assertEquals(ShiftSwapRequestStatus::Declined, $swap->status);
        $this->assertEquals('Niet beschikbaar', $swap->decline_reason);
    }

    public function test_non_target_cannot_respond(): void
    {
        [$userA, $cgA, $client] = $this->setup();
        [$userB, $cgB] = $this->setup($client);
        [$userC] = $this->setup($client);
        $swap = ShiftSwapRequest::factory()->create(['requester_caregiver_id' => $cgA->id, 'target_caregiver_id' => $cgB->id]);

        $response = $this->actingAs($userC)->post("/shift-swap-requests/{$swap->id}/accept");
        $response->assertForbidden();
    }

    public function test_requester_can_cancel(): void
    {
        [$userA, $cgA] = $this->setup();
        $swap = ShiftSwapRequest::factory()->create(['requester_caregiver_id' => $cgA->id]);

        $response = $this->actingAs($userA)->delete("/shift-swap-requests/{$swap->id}");

        $response->assertRedirect();
        $this->assertEquals(ShiftSwapRequestStatus::Cancelled, $swap->fresh()->status);
    }

    private function setup(?Client $client = null): array
    {
        $user = User::factory()->create(['role' => UserRole::Caregiver]);
        $client ??= Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);
        return [$user, $caregiver, $client];
    }
}
```

- [ ] **Step 7: Run tests and commit**

```bash
php artisan test tests/Feature/ShiftSwapRequestTest.php
git add app/Policies/ShiftSwapRequestPolicy.php app/Http/Requests/ShiftSwapRequestRequest.php app/Http/Controllers/ShiftSwap*Controller.php routes/web.php tests/Feature/ShiftSwapRequestTest.php
git commit -m "feat: add ShiftSwapRequest endpoints with accept/decline flow"
```

---

## Task 6: OpenSwapRequest Endpoints

**Files:**
- Create: `app/Policies/OpenSwapRequestPolicy.php`
- Create: `app/Policies/OpenSwapOfferPolicy.php`
- Create: `app/Http/Requests/OpenSwapRequestRequest.php`
- Create: `app/Http/Requests/OpenSwapOfferRequest.php`
- Create: `app/Http/Controllers/OpenSwapRequestController.php`
- Create: `app/Http/Controllers/OpenSwapOfferController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/OpenSwapTest.php`

- [ ] **Step 1: Policies**

```php
<?php
// app/Policies/OpenSwapRequestPolicy.php
namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\OpenSwapRequest;
use App\Models\User;

class OpenSwapRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Caregiver;
    }

    public function delete(User $user, OpenSwapRequest $request): bool
    {
        return $request->requester->user_id === $user->id;
    }

    public function acceptOffer(User $user, OpenSwapRequest $request): bool
    {
        return $request->requester->user_id === $user->id;
    }

    public function bid(User $user, OpenSwapRequest $request): bool
    {
        if ($user->role !== UserRole::Caregiver) return false;
        $clientId = $request->schedule?->client_id ?? $request->scheduleException?->client_id;
        if (!$clientId) return false;
        if ($request->requester->user_id === $user->id) return false;
        return Caregiver::where('user_id', $user->id)->where('client_id', $clientId)->exists();
    }
}
```

```php
<?php
// app/Policies/OpenSwapOfferPolicy.php
namespace App\Policies;

use App\Models\OpenSwapOffer;
use App\Models\User;

class OpenSwapOfferPolicy
{
    public function withdraw(User $user, OpenSwapOffer $offer): bool
    {
        return $offer->offeredBy->user_id === $user->id;
    }
}
```

- [ ] **Step 2: Form requests**

```php
<?php
// app/Http/Requests/OpenSwapRequestRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenSwapRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'schedule_id' => ['nullable', 'exists:schedules,id', 'required_without:schedule_exception_id'],
            'schedule_exception_id' => ['nullable', 'exists:schedule_exceptions,id', 'required_without:schedule_id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

```php
<?php
// app/Http/Requests/OpenSwapOfferRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenSwapOfferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'offered_schedule_id' => ['nullable', 'exists:schedules,id', 'required_without:offered_schedule_exception_id'],
            'offered_schedule_exception_id' => ['nullable', 'exists:schedule_exceptions,id', 'required_without:offered_schedule_id'],
            'offered_date' => ['required', 'date'],
        ];
    }
}
```

- [ ] **Step 3: Request controller**

```php
<?php
// app/Http/Controllers/OpenSwapRequestController.php
namespace App\Http\Controllers;

use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Http\Requests\OpenSwapRequestRequest;
use App\Models\Caregiver;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Services\ShiftReassignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class OpenSwapRequestController extends Controller
{
    public function __construct(private ShiftReassignmentService $reassign) {}

    public function store(OpenSwapRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', OpenSwapRequest::class);
        $user = auth()->user();

        $clientId = $request->schedule_id
            ? Schedule::findOrFail($request->schedule_id)->client_id
            : ScheduleException::findOrFail($request->schedule_exception_id)->client_id;
        $caregiver = Caregiver::where('user_id', $user->id)->where('client_id', $clientId)->first();
        if (!$caregiver) abort(403);

        OpenSwapRequest::create([
            ...$request->validated(),
            'requester_caregiver_id' => $caregiver->id,
        ]);

        // notify in Task 8
        return back();
    }

    public function destroy(OpenSwapRequest $openSwapRequest): RedirectResponse
    {
        $this->authorize('delete', $openSwapRequest);
        if ($openSwapRequest->status !== OpenSwapRequestStatus::Open) {
            throw ValidationException::withMessages(['status' => 'Alleen open verzoeken kunnen worden ingetrokken.']);
        }
        $openSwapRequest->update(['status' => OpenSwapRequestStatus::Cancelled]);
        return back();
    }

    public function acceptOffer(OpenSwapRequest $openSwapRequest, \App\Models\OpenSwapOffer $offer): RedirectResponse
    {
        $this->authorize('acceptOffer', $openSwapRequest);
        if ($openSwapRequest->status !== OpenSwapRequestStatus::Open) {
            throw ValidationException::withMessages(['status' => 'Dit verzoek is niet meer open.']);
        }
        if ($offer->open_swap_request_id !== $openSwapRequest->id) {
            abort(404);
        }

        $reqSource = $openSwapRequest->schedule ?? $openSwapRequest->scheduleException;
        $offSource = $offer->offeredSchedule ?? $offer->offeredScheduleException;

        $ex1 = $this->reassign->reassign($reqSource, $openSwapRequest->date->format('Y-m-d'), $offer->offered_by_caregiver_id);
        $ex2 = $this->reassign->reassign($offSource, $offer->offered_date->format('Y-m-d'), $openSwapRequest->requester_caregiver_id);

        $openSwapRequest->update([
            'status' => OpenSwapRequestStatus::Fulfilled,
            'selected_offer_id' => $offer->id,
            'fulfilled_at' => now(),
            'resulting_exception_ids' => [$ex1->id, $ex2->id],
        ]);
        $offer->update(['status' => OpenSwapOfferStatus::Accepted]);

        // Decline other offers
        $openSwapRequest->offers()
            ->where('id', '!=', $offer->id)
            ->where('status', OpenSwapOfferStatus::Pending)
            ->update(['status' => OpenSwapOfferStatus::Declined]);

        // notify in Task 8

        return back();
    }
}
```

- [ ] **Step 4: Offer controller**

```php
<?php
// app/Http/Controllers/OpenSwapOfferController.php
namespace App\Http\Controllers;

use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Http\Requests\OpenSwapOfferRequest;
use App\Models\Caregiver;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class OpenSwapOfferController extends Controller
{
    public function store(OpenSwapOfferRequest $request, OpenSwapRequest $openSwapRequest): RedirectResponse
    {
        $this->authorize('bid', $openSwapRequest);
        if ($openSwapRequest->status !== OpenSwapRequestStatus::Open) {
            throw ValidationException::withMessages(['status' => 'Dit verzoek is niet meer open.']);
        }

        $clientId = $openSwapRequest->schedule?->client_id ?? $openSwapRequest->scheduleException->client_id;
        $caregiver = Caregiver::where('user_id', auth()->id())->where('client_id', $clientId)->firstOrFail();

        OpenSwapOffer::create([
            ...$request->validated(),
            'open_swap_request_id' => $openSwapRequest->id,
            'offered_by_caregiver_id' => $caregiver->id,
        ]);

        // notify in Task 8
        return back();
    }

    public function destroy(OpenSwapOffer $offer): RedirectResponse
    {
        $this->authorize('withdraw', $offer);
        if ($offer->status !== OpenSwapOfferStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Alleen openstaande aanbiedingen kunnen worden ingetrokken.']);
        }
        $offer->update(['status' => OpenSwapOfferStatus::Withdrawn]);
        return back();
    }
}
```

- [ ] **Step 5: Add routes**

```php
use App\Http\Controllers\OpenSwapRequestController;
use App\Http\Controllers\OpenSwapOfferController;

Route::post('/open-swap-requests', [OpenSwapRequestController::class, 'store'])->name('open-swap-requests.store');
Route::delete('/open-swap-requests/{openSwapRequest}', [OpenSwapRequestController::class, 'destroy'])->name('open-swap-requests.destroy');
Route::post('/open-swap-requests/{openSwapRequest}/offers', [OpenSwapOfferController::class, 'store'])->name('open-swap-requests.offers.store');
Route::post('/open-swap-requests/{openSwapRequest}/offers/{offer}/accept', [OpenSwapRequestController::class, 'acceptOffer'])->name('open-swap-requests.offers.accept');
Route::delete('/open-swap-offers/{offer}', [OpenSwapOfferController::class, 'destroy'])->name('open-swap-offers.destroy');
```

- [ ] **Step 6: Tests** — minimum scenarios

```php
<?php
// tests/Feature/OpenSwapTest.php
namespace Tests\Feature;

use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenSwapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_caregiver_creates_open_swap_request(): void
    {
        [$user, $cg, $client] = $this->setup();
        $sched = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $cg->id]);

        $response = $this->actingAs($user)->post('/open-swap-requests', [
            'schedule_id' => $sched->id,
            'date' => '2026-06-10',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('open_swap_requests', [
            'schedule_id' => $sched->id,
            'requester_caregiver_id' => $cg->id,
            'status' => 'open',
        ]);
    }

    public function test_colleague_can_bid_and_requester_can_accept(): void
    {
        [$userA, $cgA, $client] = $this->setup();
        [$userB, $cgB] = $this->setup($client);
        $schedA = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $cgA->id]);
        $schedB = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $cgB->id]);
        $req = OpenSwapRequest::factory()->create([
            'schedule_id' => $schedA->id, 'date' => '2026-06-10', 'requester_caregiver_id' => $cgA->id,
        ]);

        // B bids
        $this->actingAs($userB)->post("/open-swap-requests/{$req->id}/offers", [
            'offered_schedule_id' => $schedB->id,
            'offered_date' => '2026-06-12',
        ])->assertRedirect();

        $offer = OpenSwapOffer::where('open_swap_request_id', $req->id)->first();
        $this->assertNotNull($offer);

        // A accepts
        $this->actingAs($userA)->post("/open-swap-requests/{$req->id}/offers/{$offer->id}/accept")->assertRedirect();

        $req->refresh();
        $this->assertEquals(OpenSwapRequestStatus::Fulfilled, $req->status);
        $this->assertEquals($offer->id, $req->selected_offer_id);
        $this->assertEquals(OpenSwapOfferStatus::Accepted, $offer->fresh()->status);
        $this->assertCount(2, $req->resulting_exception_ids);
    }

    public function test_accepting_one_offer_declines_others(): void
    {
        [$userA, $cgA, $client] = $this->setup();
        [$userB, $cgB] = $this->setup($client);
        [$userC, $cgC] = $this->setup($client);
        $req = OpenSwapRequest::factory()->create(['requester_caregiver_id' => $cgA->id]);
        $offerB = OpenSwapOffer::factory()->create(['open_swap_request_id' => $req->id, 'offered_by_caregiver_id' => $cgB->id]);
        $offerC = OpenSwapOffer::factory()->create(['open_swap_request_id' => $req->id, 'offered_by_caregiver_id' => $cgC->id]);

        $this->actingAs($userA)->post("/open-swap-requests/{$req->id}/offers/{$offerB->id}/accept");

        $this->assertEquals(OpenSwapOfferStatus::Accepted, $offerB->fresh()->status);
        $this->assertEquals(OpenSwapOfferStatus::Declined, $offerC->fresh()->status);
    }

    private function setup(?Client $client = null): array
    {
        $user = User::factory()->create(['role' => UserRole::Caregiver]);
        $client ??= Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);
        return [$user, $caregiver, $client];
    }
}
```

- [ ] **Step 7: Run tests + commit**

```bash
php artisan test
git add app/Policies/OpenSwap*.php app/Http/Requests/OpenSwap*.php app/Http/Controllers/OpenSwap*.php routes/web.php tests/Feature/OpenSwapTest.php
git commit -m "feat: add OpenSwapRequest and OpenSwapOffer endpoints"
```

---

## Task 7: Observer updates + scheduled expiration

**Files:**
- Modify: `app/Observers/ScheduleObserver.php`
- Modify: `app/Observers/ScheduleExceptionObserver.php`
- Create: `app/Console/Commands/ExpireShiftRequests.php`
- Modify: `routes/console.php` (Laravel 11+ uses this for scheduling)
- Create: `tests/Feature/ShiftRequestObserverTest.php`
- Create: `tests/Feature/ExpireShiftRequestsTest.php`

- [ ] **Step 1: Extend ScheduleObserver to cancel open requests on delete**

Open `app/Observers/ScheduleObserver.php` and update the `deleting` method to also cancel any open shift requests referencing this schedule:

```php
<?php
namespace App\Observers;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Enums\ShiftSwapRequestStatus;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\AvailabilitySlot;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTakeoverOffer;

class ScheduleObserver
{
    public function deleting(Schedule $schedule): void
    {
        // existing AvailabilitySlot reset
        AvailabilitySlot::where('schedule_id', $schedule->id)->update([
            'status' => AvailabilitySlotStatus::Open,
            'claimed_by' => null,
            'claimed_at' => null,
            'schedule_id' => null,
        ]);

        // Cancel open takeover offers
        ShiftTakeoverOffer::where('schedule_id', $schedule->id)
            ->where('status', ShiftTakeoverOfferStatus::Open)
            ->update(['status' => ShiftTakeoverOfferStatus::Cancelled]);

        // Cancel pending swap requests on either side
        ShiftSwapRequest::where('status', ShiftSwapRequestStatus::Pending)
            ->where(function ($q) use ($schedule) {
                $q->where('requester_schedule_id', $schedule->id)
                  ->orWhere('target_schedule_id', $schedule->id);
            })
            ->update(['status' => ShiftSwapRequestStatus::Cancelled]);

        // Cancel open OpenSwapRequest
        OpenSwapRequest::where('schedule_id', $schedule->id)
            ->where('status', OpenSwapRequestStatus::Open)
            ->update(['status' => OpenSwapRequestStatus::Cancelled]);

        // Withdraw pending OpenSwapOffer
        OpenSwapOffer::where('offered_schedule_id', $schedule->id)
            ->where('status', OpenSwapOfferStatus::Pending)
            ->update(['status' => OpenSwapOfferStatus::Withdrawn]);
    }
}
```

- [ ] **Step 2: Same for ScheduleExceptionObserver**

```php
<?php
namespace App\Observers;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\OpenSwapOfferStatus;
use App\Enums\OpenSwapRequestStatus;
use App\Enums\ShiftSwapRequestStatus;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\AvailabilitySlot;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use App\Models\ScheduleException;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTakeoverOffer;

class ScheduleExceptionObserver
{
    public function deleting(ScheduleException $exception): void
    {
        AvailabilitySlot::where('schedule_exception_id', $exception->id)->update([
            'status' => AvailabilitySlotStatus::Open,
            'claimed_by' => null,
            'claimed_at' => null,
            'schedule_exception_id' => null,
        ]);

        ShiftTakeoverOffer::where('schedule_exception_id', $exception->id)
            ->where('status', ShiftTakeoverOfferStatus::Open)
            ->update(['status' => ShiftTakeoverOfferStatus::Cancelled]);

        ShiftSwapRequest::where('status', ShiftSwapRequestStatus::Pending)
            ->where(function ($q) use ($exception) {
                $q->where('requester_schedule_exception_id', $exception->id)
                  ->orWhere('target_schedule_exception_id', $exception->id);
            })
            ->update(['status' => ShiftSwapRequestStatus::Cancelled]);

        OpenSwapRequest::where('schedule_exception_id', $exception->id)
            ->where('status', OpenSwapRequestStatus::Open)
            ->update(['status' => OpenSwapRequestStatus::Cancelled]);

        OpenSwapOffer::where('offered_schedule_exception_id', $exception->id)
            ->where('status', OpenSwapOfferStatus::Pending)
            ->update(['status' => OpenSwapOfferStatus::Withdrawn]);
    }
}
```

- [ ] **Step 3: Observer tests**

```php
<?php
// tests/Feature/ShiftRequestObserverTest.php
namespace Tests\Feature;

use App\Enums\OpenSwapRequestStatus;
use App\Enums\ShiftSwapRequestStatus;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\OpenSwapRequest;
use App\Models\Schedule;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftRequestObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_schedule_cancels_open_takeover_offer(): void
    {
        $schedule = Schedule::factory()->create();
        $offer = ShiftTakeoverOffer::factory()->create(['schedule_id' => $schedule->id, 'status' => ShiftTakeoverOfferStatus::Open]);

        $schedule->delete();

        $this->assertEquals(ShiftTakeoverOfferStatus::Cancelled, $offer->fresh()->status);
    }

    public function test_deleting_schedule_cancels_pending_swap_request(): void
    {
        $schedule = Schedule::factory()->create();
        $swap = ShiftSwapRequest::factory()->create(['requester_schedule_id' => $schedule->id, 'status' => ShiftSwapRequestStatus::Pending]);

        $schedule->delete();

        $this->assertEquals(ShiftSwapRequestStatus::Cancelled, $swap->fresh()->status);
    }

    public function test_deleting_schedule_cancels_open_swap_request(): void
    {
        $schedule = Schedule::factory()->create();
        $req = OpenSwapRequest::factory()->create(['schedule_id' => $schedule->id, 'status' => OpenSwapRequestStatus::Open]);

        $schedule->delete();

        $this->assertEquals(OpenSwapRequestStatus::Cancelled, $req->fresh()->status);
    }
}
```

- [ ] **Step 4: Scheduled command**

```bash
php artisan make:command ExpireShiftRequests
```

```php
<?php
// app/Console/Commands/ExpireShiftRequests.php
namespace App\Console\Commands;

use App\Enums\OpenSwapRequestStatus;
use App\Enums\ShiftSwapRequestStatus;
use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\OpenSwapRequest;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Console\Command;

class ExpireShiftRequests extends Command
{
    protected $signature = 'shift-requests:expire';
    protected $description = 'Expire shift takeover offers, swap requests, and open swap requests past their date';

    public function handle(): int
    {
        $today = now()->toDateString();

        $a = ShiftTakeoverOffer::where('status', ShiftTakeoverOfferStatus::Open)
            ->whereDate('date', '<', $today)
            ->update(['status' => ShiftTakeoverOfferStatus::Expired]);

        $b = ShiftSwapRequest::where('status', ShiftSwapRequestStatus::Pending)
            ->whereDate('requester_date', '<', $today)
            ->whereDate('target_date', '<', $today)
            ->update(['status' => ShiftSwapRequestStatus::Expired]);

        $c = OpenSwapRequest::where('status', OpenSwapRequestStatus::Open)
            ->whereDate('date', '<', $today)
            ->update(['status' => OpenSwapRequestStatus::Expired]);

        $this->info("Expired: $a takeovers, $b swaps, $c open swaps");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Schedule the command**

In `routes/console.php` add:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('shift-requests:expire')->dailyAt('01:00');
```

- [ ] **Step 6: Command test**

```php
<?php
// tests/Feature/ExpireShiftRequestsTest.php
namespace Tests\Feature;

use App\Enums\ShiftTakeoverOfferStatus;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireShiftRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_past_takeover_offers(): void
    {
        $past = ShiftTakeoverOffer::factory()->create(['status' => ShiftTakeoverOfferStatus::Open, 'date' => '2020-01-01']);
        $future = ShiftTakeoverOffer::factory()->create(['status' => ShiftTakeoverOfferStatus::Open, 'date' => '2099-12-31']);

        $this->artisan('shift-requests:expire')->assertSuccessful();

        $this->assertEquals(ShiftTakeoverOfferStatus::Expired, $past->fresh()->status);
        $this->assertEquals(ShiftTakeoverOfferStatus::Open, $future->fresh()->status);
    }
}
```

- [ ] **Step 7: Run all tests + commit**

```bash
php artisan test
git add app/Observers app/Console/Commands/ExpireShiftRequests.php routes/console.php tests/Feature/ShiftRequestObserverTest.php tests/Feature/ExpireShiftRequestsTest.php
git commit -m "feat: observer cleanup + scheduled expiration for shift requests"
```

---

## Task 8: Notifications

**Files:**
- Create: `database/migrations/xxxx_create_notifications_table.php` (via `php artisan notifications:table`)
- Create: 9 notification classes in `app/Notifications/`
- Create: `app/Http/Controllers/NotificationController.php`
- Modify: 5 controllers (re-add the notify calls stubbed in earlier tasks)
- Modify: `routes/web.php`
- Create: `tests/Feature/NotificationsTest.php`

- [ ] **Step 1: Create notifications table**

```bash
php artisan notifications:table
php artisan migrate
```

- [ ] **Step 2: Add a helper trait or static method**

Create the 9 notification classes. Each implements `database` + `mail` channels. For brevity, the implementer should create them all following the pattern below.

```php
<?php
// app/Notifications/ShiftTakeoverOffered.php
namespace App\Notifications;

use App\Models\Caregiver;
use App\Models\ShiftTakeoverOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftTakeoverOffered extends Notification
{
    use Queueable;

    public function __construct(public ShiftTakeoverOffer $offer) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shift = $this->offer->schedule ?? $this->offer->scheduleException;
        return (new MailMessage)
            ->subject('Een collega biedt een shift aan voor overname')
            ->greeting("Hoi {$notifiable->name},")
            ->line("{$this->offer->offeredBy->name} biedt een shift aan voor overname op {$this->offer->date->format('d-m-Y')}.")
            ->line("Tijd: {$shift->start_time} – {$shift->end_time}")
            ->action('Bekijk in mijn rooster', url('/my-schedule'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shift_takeover_offered',
            'offer_id' => $this->offer->id,
            'offered_by' => $this->offer->offeredBy->name,
            'date' => $this->offer->date->format('Y-m-d'),
        ];
    }

    /** Notify all colleagues of the same client (excluding the offerer). */
    public static function notifyColleagues(ShiftTakeoverOffer $offer): void
    {
        $clientId = $offer->schedule?->client_id ?? $offer->scheduleException->client_id;
        $colleagues = Caregiver::where('client_id', $clientId)
            ->whereNotNull('user_id')
            ->where('id', '!=', $offer->offered_by_caregiver_id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($colleagues as $user) {
            $user->notify(new self($offer));
        }
    }
}
```

Create similar classes (with role-specific message text) for:
- `ShiftTakenOver(ShiftTakeoverOffer)` — `::notify($offer)` — notifies offerer + budget holder
- `ShiftSwapRequested(ShiftSwapRequest)` — `::notify($req)` — notifies target
- `ShiftSwapResponded(ShiftSwapRequest, bool $accepted)` — `::notify($req, $accepted)` — notifies requester + budget holder (if accepted)
- `OpenSwapRequested(OpenSwapRequest)` — `::notifyColleagues($req)` — notifies colleagues
- `OpenSwapOfferReceived(OpenSwapOffer)` — `::notify($offer)` — notifies requester
- `OpenSwapAccepted(OpenSwapOffer)` — `::notify($offer)` — notifies offer's offerer + budget holder
- `OpenSwapDeclined(OpenSwapOffer)` — `::notify($offer)` — notifies offer's offerer
- `ShiftRequestAutoCancelled(string $type, int $id)` — generic for observer-triggered cancels

For each, implement `via`, `toMail`, `toArray`, and a static helper that resolves the right recipients.

- [ ] **Step 3: Re-add notify calls in earlier controllers**

In `ShiftTakeoverOfferController::store()` after the `ShiftTakeoverOffer::create([...])`:

```php
\App\Notifications\ShiftTakeoverOffered::notifyColleagues($offer);
```

In `ShiftTakeoverClaimController::store()` after update:

```php
\App\Notifications\ShiftTakenOver::notify($offer);
```

In `ShiftSwapRequestController::store()` after create:

```php
\App\Notifications\ShiftSwapRequested::notify($swap);
```

In `ShiftSwapResponseController::accept()` after update:

```php
\App\Notifications\ShiftSwapResponded::notify($shiftSwapRequest, true);
```

In `ShiftSwapResponseController::decline()` after update:

```php
\App\Notifications\ShiftSwapResponded::notify($shiftSwapRequest, false);
```

In `OpenSwapRequestController::store()`:

```php
\App\Notifications\OpenSwapRequested::notifyColleagues($openSwapRequest);
```

In `OpenSwapRequestController::acceptOffer()` after update:

```php
\App\Notifications\OpenSwapAccepted::notify($offer);
foreach ($openSwapRequest->offers()->where('id', '!=', $offer->id)->where('status', \App\Enums\OpenSwapOfferStatus::Declined)->get() as $declined) {
    \App\Notifications\OpenSwapDeclined::notify($declined);
}
```

In `OpenSwapOfferController::store()`:

```php
\App\Notifications\OpenSwapOfferReceived::notify($offer);
```

In observers (for auto-cancel), use `ShiftRequestAutoCancelled` to notify affected parties.

- [ ] **Step 4: Notification controller (bell dropdown + mark read)**

```php
<?php
// app/Http/Controllers/NotificationController.php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();
        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->limit(10)->get(),
        ]);
    }

    public function markRead(string $id): RedirectResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    }
}
```

Add routes:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});
```

- [ ] **Step 5: Tests**

```php
<?php
// tests/Feature/NotificationsTest.php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ShiftTakeoverOffer;
use App\Models\User;
use App\Notifications\ShiftTakeoverOffered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_takeover_offer_notifies_colleagues(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $userA = User::factory()->create(['role' => UserRole::Caregiver]);
        $userB = User::factory()->create(['role' => UserRole::Caregiver]);
        $cgA = Caregiver::factory()->create(['client_id' => $client->id, 'user_id' => $userA->id]);
        $cgB = Caregiver::factory()->create(['client_id' => $client->id, 'user_id' => $userB->id]);
        $schedule = Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $cgA->id]);

        $this->actingAs($userA)->post('/shift-takeover-offers', [
            'schedule_id' => $schedule->id,
            'date' => '2026-06-15',
        ]);

        Notification::assertSentTo($userB, ShiftTakeoverOffered::class);
        Notification::assertNotSentTo($userA, ShiftTakeoverOffered::class);
    }

    public function test_notification_bell_endpoint(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/notifications');
        $response->assertOk()->assertJsonStructure(['unread_count', 'notifications']);
    }
}
```

- [ ] **Step 6: Run tests and commit**

```bash
php artisan test
git add database/migrations/*notifications* app/Notifications app/Http/Controllers/NotificationController.php app/Http/Controllers/Shift*Controller.php app/Http/Controllers/OpenSwap*Controller.php app/Observers routes/web.php tests/Feature/NotificationsTest.php
git commit -m "feat: notifications for all shift request flows + bell endpoint"
```

---

## Task 9: Frontend types + colleague visibility

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `app/Http/Controllers/CaregiverScheduleController.php`
- Modify: `resources/js/components/schedule/week-view.tsx`
- Modify: `resources/js/components/schedule/month-view.tsx`
- Modify: `resources/js/pages/caregiver-schedule/index.tsx`

- [ ] **Step 1: Add TypeScript types**

In `resources/js/types/models.ts` add:

```typescript
export interface ShiftTakeoverOffer {
    id: number;
    schedule_id: number | null;
    schedule_exception_id: number | null;
    date: string;
    offered_by_caregiver_id: number;
    status: 'open' | 'claimed' | 'cancelled' | 'expired';
    claimed_by_caregiver_id: number | null;
    claimed_at: string | null;
    notes: string | null;
    offered_by?: Caregiver;
    claimed_by?: Caregiver;
}

export interface ShiftSwapRequest {
    id: number;
    requester_caregiver_id: number;
    requester_schedule_id: number | null;
    requester_schedule_exception_id: number | null;
    requester_date: string;
    target_caregiver_id: number;
    target_schedule_id: number | null;
    target_schedule_exception_id: number | null;
    target_date: string;
    status: 'pending' | 'accepted' | 'declined' | 'cancelled' | 'expired';
    responded_at: string | null;
    decline_reason: string | null;
    requester?: Caregiver;
    target?: Caregiver;
}

export interface OpenSwapRequest {
    id: number;
    schedule_id: number | null;
    schedule_exception_id: number | null;
    date: string;
    requester_caregiver_id: number;
    status: 'open' | 'fulfilled' | 'cancelled' | 'expired';
    selected_offer_id: number | null;
    notes: string | null;
    requester?: Caregiver;
    offers?: OpenSwapOffer[];
}

export interface OpenSwapOffer {
    id: number;
    open_swap_request_id: number;
    offered_by_caregiver_id: number;
    offered_schedule_id: number | null;
    offered_schedule_exception_id: number | null;
    offered_date: string;
    status: 'pending' | 'accepted' | 'declined' | 'withdrawn';
    offered_by?: Caregiver;
}
```

- [ ] **Step 2: Update CaregiverScheduleController to load all colleagues' shifts**

Replace the entire `__invoke()` method body with:

```php
public function __invoke(): Response
{
    $user = auth()->user();
    $caregiverRecords = Caregiver::where('user_id', $user->id)->with('client')->get();
    $myCaregiverIds = $caregiverRecords->pluck('id')->toArray();

    $clients = $caregiverRecords->map(function (Caregiver $caregiver) {
        $client = $caregiver->client;
        // Load ALL schedules and exceptions for this client (not just for this caregiver)
        $client->load([
            'schedules.caregiver',
            'scheduleExceptions.caregiver',
        ]);
        $client->setRelation(
            'availabilitySlots',
            $client->availabilitySlots()->where('status', 'open')->get()
        );
        return $client;
    })->unique('id')->values();

    return Inertia::render('caregiver-schedule/index', [
        'clients' => $clients,
        'myCaregiverIds' => $myCaregiverIds,
    ]);
}
```

- [ ] **Step 3: Add `other-caregiver` variant to WeekView**

In `resources/js/components/schedule/week-view.tsx`:

Update the `EventBlock` interface variant type:

```typescript
variant: 'regular' | 'added' | 'modified' | 'cancelled' | 'available' | 'other';
```

Add `other` to both styles objects in `EventCard`:

```typescript
// In styles:
other: 'bg-gray-50 border-gray-200 text-gray-500 dark:bg-gray-900/40 dark:border-gray-700 dark:text-gray-400',

// In timeStyles:
other: 'text-gray-400 dark:text-gray-500',
```

Update `WeekViewProps` to accept which caregiver ids are "mine":

```typescript
interface WeekViewProps {
    schedules: Schedule[];
    exceptions: ScheduleException[];
    availabilitySlots?: AvailabilitySlot[];
    weekStart: Date;
    myCaregiverIds?: number[];
    onDeleteSchedule?: (id: number) => void;
    onDeleteException?: (id: number) => void;
    onDeleteAvailability?: (id: number) => void;
    onClaimAvailability?: (id: number) => void;
    onShiftClick?: (kind: 'schedule' | 'exception', id: number, date: string, isMine: boolean) => void;
}
```

In `getEventsForDay`, change the variant for non-own shifts:

```typescript
schedules
    .filter((s) => s.day_of_week === dayIndex && !cancelledOrModifiedIds.has(s.id))
    .forEach((s) => {
        const isMine = myCaregiverIds.includes(s.caregiver_id);
        events.push({
            id: `s-${s.id}`,
            label: s.caregiver?.name ?? 'Onbekend',
            startTime: s.start_time,
            endTime: s.end_time,
            variant: isMine ? 'regular' : 'other',
            onDelete: isMine && onDeleteSchedule ? () => onDeleteSchedule(s.id) : undefined,
            onAction: onShiftClick ? () => onShiftClick('schedule', s.id, dateStr, isMine) : undefined,
            actionLabel: isMine ? 'Acties' : 'Info',
        });
    });
```

Similarly for the exception loop (use `ex.caregiver_id`).

Pass `myCaregiverIds = []` default and read from props.

Add legend entry "Collega".

- [ ] **Step 4: Same updates to MonthView**

Mirror the changes in `resources/js/components/schedule/month-view.tsx`:
- Add `other` variant
- Add `myCaregiverIds` prop
- Compute `isMine` and switch variant accordingly
- Use same styles

- [ ] **Step 5: Wire up caregiver-schedule page**

In `resources/js/pages/caregiver-schedule/index.tsx`, accept `myCaregiverIds` prop:

```typescript
export default function CaregiverScheduleIndex({
    clients,
    myCaregiverIds,
}: {
    clients: ClientWithSchedule[];
    myCaregiverIds: number[];
}) {
```

Don't rewrite caregiver names anymore — we now want to see the real caregiver name (since you can see colleagues). Replace the merging block:

```typescript
const allSchedules: Schedule[] = clients.flatMap((c) => c.schedules ?? []);
const allExceptions: ScheduleException[] = clients.flatMap((c) => c.schedule_exceptions ?? []);
const allAvailabilitySlots: AvailabilitySlot[] = clients.flatMap((c) => c.availability_slots ?? []);
```

Pass `myCaregiverIds` to both views.

- [ ] **Step 6: Build, test, commit**

```bash
source ~/.nvm/nvm.sh && nvm use 22 && npm run build
php artisan test
git add resources/js/types/models.ts app/Http/Controllers/CaregiverScheduleController.php resources/js/components/schedule/ resources/js/pages/caregiver-schedule/index.tsx
git commit -m "feat: show colleagues' shifts on caregiver schedule with distinct variant"
```

---

## Task 10: Notification bell component

**Files:**
- Create: `resources/js/components/notifications/notification-bell.tsx`
- Modify: `resources/js/components/app-header.tsx` (or wherever the header lives — find with grep)
- Create: `resources/js/hooks/use-notifications.ts`

- [ ] **Step 1: Hook**

```typescript
// resources/js/hooks/use-notifications.ts
import { useEffect, useState } from 'react';

interface NotificationItem {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
}

export function useNotifications() {
    const [unreadCount, setUnreadCount] = useState(0);
    const [items, setItems] = useState<NotificationItem[]>([]);
    const [loading, setLoading] = useState(false);

    async function refresh() {
        setLoading(true);
        const res = await fetch('/notifications', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        setUnreadCount(data.unread_count);
        setItems(data.notifications);
        setLoading(false);
    }

    useEffect(() => {
        refresh();
        const id = setInterval(refresh, 60_000);
        return () => clearInterval(id);
    }, []);

    return { unreadCount, items, loading, refresh };
}
```

- [ ] **Step 2: Bell component**

```tsx
// resources/js/components/notifications/notification-bell.tsx
import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { useNotifications } from '@/hooks/use-notifications';

export function NotificationBell() {
    const { unreadCount, items, refresh } = useNotifications();
    const [open, setOpen] = useState(false);

    function markAllRead() {
        router.post('/notifications/read-all', {}, { preserveScroll: true, onSuccess: () => refresh() });
    }

    return (
        <div className="relative">
            <button onClick={() => setOpen(o => !o)} className="relative rounded-md p-2 hover:bg-gray-100">
                <Bell className="h-5 w-5" />
                {unreadCount > 0 && (
                    <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-medium text-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 top-full z-50 mt-2 w-80 rounded-xl border bg-white shadow-xl">
                    <div className="flex items-center justify-between border-b p-3">
                        <div className="text-sm font-medium">Meldingen</div>
                        {unreadCount > 0 && (
                            <button onClick={markAllRead} className="text-xs text-blue-600 hover:underline">
                                Alle als gelezen
                            </button>
                        )}
                    </div>
                    <div className="max-h-96 overflow-y-auto">
                        {items.length === 0 ? (
                            <div className="p-6 text-center text-sm text-gray-500">Geen meldingen</div>
                        ) : items.map(item => (
                            <div key={item.id} className={`border-b p-3 text-sm ${item.read_at ? 'text-gray-500' : 'font-medium'}`}>
                                {renderNotificationText(item)}
                                <div className="mt-1 text-xs text-gray-400">{new Date(item.created_at).toLocaleString('nl-NL')}</div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function renderNotificationText(item: { type: string; data: Record<string, unknown> }): string {
    switch (item.type) {
        case 'App\\Notifications\\ShiftTakeoverOffered':
            return `${item.data.offered_by} biedt een shift aan op ${item.data.date}`;
        case 'App\\Notifications\\ShiftTakenOver':
            return `Jouw shift is overgenomen`;
        case 'App\\Notifications\\ShiftSwapRequested':
            return `Iemand wil met jou ruilen`;
        case 'App\\Notifications\\ShiftSwapResponded':
            return `Reactie op jouw ruilverzoek`;
        case 'App\\Notifications\\OpenSwapRequested':
            return `Open ruilverzoek geplaatst`;
        case 'App\\Notifications\\OpenSwapOfferReceived':
            return `Nieuw ruilaanbod ontvangen`;
        case 'App\\Notifications\\OpenSwapAccepted':
            return `Jouw aanbod is geaccepteerd`;
        case 'App\\Notifications\\OpenSwapDeclined':
            return `Jouw aanbod is afgewezen`;
        default:
            return 'Nieuwe melding';
    }
}
```

- [ ] **Step 3: Add bell to header**

Find the existing app header component (likely `resources/js/components/app-header.tsx` or `app-sidebar-header.tsx`). Add the import:

```tsx
import { NotificationBell } from '@/components/notifications/notification-bell';
```

Place `<NotificationBell />` near the user avatar/menu.

- [ ] **Step 4: Build, manual test, commit**

```bash
source ~/.nvm/nvm.sh && nvm use 22 && npm run build
git add resources/js/components/notifications resources/js/hooks/use-notifications.ts resources/js/components/app-header.tsx
git commit -m "feat: notification bell with dropdown in app header"
```

---

## Task 11: Shift action dialogs + /my-requests page

**Files:**
- Create: `app/Http/Controllers/MyRequestsController.php`
- Create: `resources/js/pages/my-requests/index.tsx`
- Create: `resources/js/components/schedule/shift-action-menu.tsx`
- Create: `resources/js/components/schedule/takeover-offer-dialog.tsx`
- Create: `resources/js/components/schedule/direct-swap-dialog.tsx`
- Create: `resources/js/components/schedule/open-swap-dialog.tsx`
- Create: `resources/js/components/schedule/swap-response-dialog.tsx`
- Create: `resources/js/components/schedule/offer-bid-dialog.tsx`
- Modify: `resources/js/pages/caregiver-schedule/index.tsx` (wire up dialogs)
- Modify: `routes/web.php`
- Create: `tests/Feature/MyRequestsControllerTest.php`

- [ ] **Step 1: MyRequestsController**

```php
<?php
// app/Http/Controllers/MyRequestsController.php
namespace App\Http\Controllers;

use App\Models\Caregiver;
use App\Models\OpenSwapOffer;
use App\Models\OpenSwapRequest;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTakeoverOffer;
use Inertia\Inertia;
use Inertia\Response;

class MyRequestsController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $caregiverIds = Caregiver::where('user_id', $user->id)->pluck('id');

        return Inertia::render('my-requests/index', [
            'myTakeoverOffers' => ShiftTakeoverOffer::whereIn('offered_by_caregiver_id', $caregiverIds)
                ->with(['schedule.client', 'scheduleException.client', 'claimedBy'])
                ->latest()->get(),
            'myDirectSwapsOut' => ShiftSwapRequest::whereIn('requester_caregiver_id', $caregiverIds)
                ->with(['requester', 'target', 'requesterSchedule.client', 'targetSchedule.client'])
                ->latest()->get(),
            'directSwapsIn' => ShiftSwapRequest::whereIn('target_caregiver_id', $caregiverIds)
                ->where('status', 'pending')
                ->with(['requester', 'requesterSchedule.client', 'targetSchedule.client'])
                ->latest()->get(),
            'myOpenSwaps' => OpenSwapRequest::whereIn('requester_caregiver_id', $caregiverIds)
                ->with(['schedule.client', 'scheduleException.client', 'offers.offeredBy'])
                ->latest()->get(),
            'openSwapsToBidOn' => OpenSwapRequest::whereHas('schedule.client.caregivers', fn ($q) => $q->where('user_id', $user->id))
                ->where('status', 'open')
                ->whereNotIn('requester_caregiver_id', $caregiverIds)
                ->with(['requester', 'schedule.client', 'scheduleException.client'])
                ->latest()->get(),
        ]);
    }
}
```

Add route:

```php
Route::middleware(['auth', 'verified', 'role:caregiver'])->group(function () {
    Route::get('/my-requests', [\App\Http\Controllers\MyRequestsController::class, 'index'])->name('my-requests.index');
});
```

Add to sidebar navigation in `resources/js/components/app-sidebar.tsx`:

```typescript
import { Bell, LayoutGrid, Users, Repeat } from 'lucide-react';

// In mainNavItems (conditionally for caregivers — for now show always):
{ title: 'Mijn verzoeken', href: '/my-requests', icon: Repeat },
```

- [ ] **Step 2: Shift action menu**

```tsx
// resources/js/components/schedule/shift-action-menu.tsx
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { MoreVertical } from 'lucide-react';

export function ShiftActionMenu({
    onOfferTakeover,
    onDirectSwap,
    onOpenSwap,
}: {
    onOfferTakeover: () => void;
    onDirectSwap: () => void;
    onOpenSwap: () => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                    <MoreVertical className="h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                <DropdownMenuItem onClick={onOfferTakeover}>Aanbieden voor overname</DropdownMenuItem>
                <DropdownMenuItem onClick={onDirectSwap}>Direct ruilen met collega…</DropdownMenuItem>
                <DropdownMenuItem onClick={onOpenSwap}>Open ruilverzoek plaatsen</DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

- [ ] **Step 3: Takeover offer dialog**

```tsx
// resources/js/components/schedule/takeover-offer-dialog.tsx
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kind: 'schedule' | 'exception';
    id: number;
    date: string;
}

export function TakeoverOfferDialog({ open, onOpenChange, kind, id, date }: Props) {
    function submit() {
        router.post('/shift-takeover-offers', {
            [kind === 'schedule' ? 'schedule_id' : 'schedule_exception_id']: id,
            date,
        }, { onSuccess: () => onOpenChange(false) });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Shift aanbieden voor overname</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Andere zorgverleners van deze cliënt kunnen jouw shift overnemen.
                </p>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Annuleren</Button>
                    <Button onClick={submit}>Aanbieden</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 4: Direct swap dialog**

```tsx
// resources/js/components/schedule/direct-swap-dialog.tsx
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { Caregiver, Schedule } from '@/types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    myKind: 'schedule' | 'exception';
    myId: number;
    myDate: string;
    colleagues: (Caregiver & { schedules?: Schedule[] })[];
}

export function DirectSwapDialog({ open, onOpenChange, myKind, myId, myDate, colleagues }: Props) {
    const [targetCaregiverId, setTargetCaregiverId] = useState('');
    const [targetScheduleId, setTargetScheduleId] = useState('');
    const [targetDate, setTargetDate] = useState('');

    const selectedColleague = colleagues.find(c => c.id === Number(targetCaregiverId));

    function submit() {
        router.post('/shift-swap-requests', {
            [myKind === 'schedule' ? 'requester_schedule_id' : 'requester_schedule_exception_id']: myId,
            requester_date: myDate,
            target_caregiver_id: targetCaregiverId,
            target_schedule_id: targetScheduleId,
            target_date: targetDate,
        }, { onSuccess: () => onOpenChange(false) });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Direct ruilen met collega</DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <div>
                        <Label>Collega</Label>
                        <Select value={targetCaregiverId} onValueChange={setTargetCaregiverId}>
                            <SelectTrigger><SelectValue placeholder="Kies collega" /></SelectTrigger>
                            <SelectContent>
                                {colleagues.map(c => (
                                    <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {selectedColleague && (
                        <>
                            <div>
                                <Label>Hun shift</Label>
                                <Select value={targetScheduleId} onValueChange={setTargetScheduleId}>
                                    <SelectTrigger><SelectValue placeholder="Kies shift" /></SelectTrigger>
                                    <SelectContent>
                                        {(selectedColleague.schedules ?? []).map(s => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                Dag {s.day_of_week}: {s.start_time}–{s.end_time}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Datum van hun shift</Label>
                                <input type="date" value={targetDate} onChange={e => setTargetDate(e.target.value)} className="block w-full rounded-md border px-3 py-2 text-sm" />
                            </div>
                        </>
                    )}
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Annuleren</Button>
                    <Button onClick={submit} disabled={!targetCaregiverId || !targetScheduleId || !targetDate}>Versturen</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 5: Open swap dialog**

```tsx
// resources/js/components/schedule/open-swap-dialog.tsx
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kind: 'schedule' | 'exception';
    id: number;
    date: string;
}

export function OpenSwapDialog({ open, onOpenChange, kind, id, date }: Props) {
    function submit() {
        router.post('/open-swap-requests', {
            [kind === 'schedule' ? 'schedule_id' : 'schedule_exception_id']: id,
            date,
        }, { onSuccess: () => onOpenChange(false) });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Open ruilverzoek plaatsen</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-muted-foreground">
                    Collega's kunnen op deze shift een ruilaanbod doen. Jij kiest welk aanbod je accepteert.
                </p>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Annuleren</Button>
                    <Button onClick={submit}>Plaatsen</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 6: Swap response dialog**

```tsx
// resources/js/components/schedule/swap-response-dialog.tsx
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ShiftSwapRequest } from '@/types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    request: ShiftSwapRequest;
}

export function SwapResponseDialog({ open, onOpenChange, request }: Props) {
    const [reason, setReason] = useState('');

    function accept() {
        router.post(`/shift-swap-requests/${request.id}/accept`, {}, { onSuccess: () => onOpenChange(false) });
    }

    function decline() {
        router.post(`/shift-swap-requests/${request.id}/decline`, { decline_reason: reason }, { onSuccess: () => onOpenChange(false) });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Antwoord op ruilverzoek</DialogTitle>
                </DialogHeader>
                <div className="space-y-3 text-sm">
                    <div>{request.requester?.name} wil ruilen.</div>
                    <div>Hun shift: {request.requester_date}</div>
                    <div>Jouw shift: {request.target_date}</div>
                    <div>
                        <Label>Reden (optioneel, bij afwijzen)</Label>
                        <Input value={reason} onChange={e => setReason(e.target.value)} />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={decline}>Afwijzen</Button>
                    <Button onClick={accept}>Accepteren</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 7: Offer-on-open-swap dialog**

```tsx
// resources/js/components/schedule/offer-bid-dialog.tsx
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { Schedule } from '@/types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    requestId: number;
    mySchedules: Schedule[];
}

export function OfferBidDialog({ open, onOpenChange, requestId, mySchedules }: Props) {
    const [scheduleId, setScheduleId] = useState('');
    const [date, setDate] = useState('');

    function submit() {
        router.post(`/open-swap-requests/${requestId}/offers`, {
            offered_schedule_id: scheduleId,
            offered_date: date,
        }, { onSuccess: () => onOpenChange(false) });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bied jouw shift aan voor ruil</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                    <div>
                        <Label>Jouw shift</Label>
                        <Select value={scheduleId} onValueChange={setScheduleId}>
                            <SelectTrigger><SelectValue placeholder="Kies shift" /></SelectTrigger>
                            <SelectContent>
                                {mySchedules.map(s => (
                                    <SelectItem key={s.id} value={String(s.id)}>Dag {s.day_of_week}: {s.start_time}–{s.end_time}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Datum</Label>
                        <input type="date" value={date} onChange={e => setDate(e.target.value)} className="block w-full rounded-md border px-3 py-2 text-sm" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Annuleren</Button>
                    <Button onClick={submit} disabled={!scheduleId || !date}>Versturen</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 8: /my-requests page**

```tsx
// resources/js/pages/my-requests/index.tsx
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SwapResponseDialog } from '@/components/schedule/swap-response-dialog';
import { OfferBidDialog } from '@/components/schedule/offer-bid-dialog';
import type { OpenSwapRequest, ShiftSwapRequest, ShiftTakeoverOffer } from '@/types';

interface Props {
    myTakeoverOffers: ShiftTakeoverOffer[];
    myDirectSwapsOut: ShiftSwapRequest[];
    directSwapsIn: ShiftSwapRequest[];
    myOpenSwaps: OpenSwapRequest[];
    openSwapsToBidOn: OpenSwapRequest[];
}

const tabs = [
    { id: 'offers', label: 'Mijn aanbiedingen' },
    { id: 'swaps-out', label: 'Mijn ruilverzoeken' },
    { id: 'swaps-in', label: 'Verzoeken aan mij' },
    { id: 'bids', label: 'Aanbiedingen die ik kan doen' },
];

export default function MyRequestsIndex(props: Props) {
    const [tab, setTab] = useState('offers');
    const [respondTo, setRespondTo] = useState<ShiftSwapRequest | null>(null);
    const [bidOn, setBidOn] = useState<OpenSwapRequest | null>(null);

    function cancel(url: string) {
        router.delete(url);
    }

    return (
        <>
            <Head title="Mijn verzoeken" />
            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">Mijn verzoeken</h1>

                <div className="flex gap-1 rounded-lg bg-muted p-0.5 w-fit">
                    {tabs.map(t => (
                        <button
                            key={t.id}
                            onClick={() => setTab(t.id)}
                            className={`rounded-md px-3 py-1 text-sm ${tab === t.id ? 'bg-white shadow-sm font-medium' : 'hover:bg-white/50'}`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                {tab === 'offers' && (
                    <div className="space-y-2">
                        {props.myTakeoverOffers.map(o => (
                            <Card key={o.id}>
                                <CardHeader><CardTitle className="text-base">Aanbod {o.date} · status: {o.status}</CardTitle></CardHeader>
                                <CardContent>
                                    {o.status === 'open' && (
                                        <Button variant="outline" size="sm" onClick={() => cancel(`/shift-takeover-offers/${o.id}`)}>Intrekken</Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                        {props.myOpenSwaps.map(r => (
                            <Card key={`os-${r.id}`}>
                                <CardHeader><CardTitle className="text-base">Open ruilverzoek {r.date} · status: {r.status}</CardTitle></CardHeader>
                                <CardContent>
                                    {(r.offers ?? []).filter(o => o.status === 'pending').length > 0 && (
                                        <div className="text-sm">{(r.offers ?? []).filter(o => o.status === 'pending').length} openstaande aanbieding(en) — kies via knop in de planning</div>
                                    )}
                                    {r.status === 'open' && (
                                        <Button variant="outline" size="sm" onClick={() => cancel(`/open-swap-requests/${r.id}`)}>Intrekken</Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {tab === 'swaps-out' && (
                    <div className="space-y-2">
                        {props.myDirectSwapsOut.map(r => (
                            <Card key={r.id}>
                                <CardHeader><CardTitle className="text-base">Ruil met {r.target?.name} · status: {r.status}</CardTitle></CardHeader>
                                <CardContent>
                                    {r.status === 'pending' && (
                                        <Button variant="outline" size="sm" onClick={() => cancel(`/shift-swap-requests/${r.id}`)}>Intrekken</Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {tab === 'swaps-in' && (
                    <div className="space-y-2">
                        {props.directSwapsIn.map(r => (
                            <Card key={r.id}>
                                <CardHeader><CardTitle className="text-base">{r.requester?.name} wil ruilen</CardTitle></CardHeader>
                                <CardContent>
                                    <Button size="sm" onClick={() => setRespondTo(r)}>Reageren</Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {tab === 'bids' && (
                    <div className="space-y-2">
                        {props.openSwapsToBidOn.map(r => (
                            <Card key={r.id}>
                                <CardHeader><CardTitle className="text-base">{r.requester?.name} zoekt ruil op {r.date}</CardTitle></CardHeader>
                                <CardContent>
                                    <Button size="sm" onClick={() => setBidOn(r)}>Aanbieden</Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {respondTo && (
                <SwapResponseDialog open={!!respondTo} onOpenChange={(o) => !o && setRespondTo(null)} request={respondTo} />
            )}
            {bidOn && (
                <OfferBidDialog open={!!bidOn} onOpenChange={(o) => !o && setBidOn(null)} requestId={bidOn.id} mySchedules={[]} />
            )}
        </>
    );
}

MyRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Mijn verzoeken', href: '/my-requests' }],
};
```

- [ ] **Step 9: Wire shift action menu into caregiver-schedule page**

In `resources/js/pages/caregiver-schedule/index.tsx`:

Add state for which dialog is open and the shift context:

```typescript
const [dialog, setDialog] = useState<null | { action: 'takeover' | 'directswap' | 'openswap'; kind: 'schedule' | 'exception'; id: number; date: string }>(null);
```

Pass `onShiftClick` to WeekView/MonthView:

```typescript
onShiftClick={(kind, id, date, isMine) => {
    if (!isMine) return; // for colleague's shifts, future: show "bid" if open swap exists
    setDialog({ action: 'takeover', kind, id, date }); // default to takeover; user can pick from menu
}}
```

Render the dialogs:

```tsx
{dialog?.action === 'takeover' && (
    <TakeoverOfferDialog open onOpenChange={() => setDialog(null)} kind={dialog.kind} id={dialog.id} date={dialog.date} />
)}
{dialog?.action === 'directswap' && (
    <DirectSwapDialog open onOpenChange={() => setDialog(null)} myKind={dialog.kind} myId={dialog.id} myDate={dialog.date} colleagues={collectColleagues(clients, myCaregiverIds)} />
)}
{dialog?.action === 'openswap' && (
    <OpenSwapDialog open onOpenChange={() => setDialog(null)} kind={dialog.kind} id={dialog.id} date={dialog.date} />
)}
```

`collectColleagues` helper:

```typescript
function collectColleagues(clients: ClientWithSchedule[], myIds: number[]) {
    const colleagues: (Caregiver & { schedules?: Schedule[] })[] = [];
    for (const client of clients) {
        for (const cg of client.caregivers ?? []) {
            if (myIds.includes(cg.id)) continue;
            const cgSchedules = (client.schedules ?? []).filter(s => s.caregiver_id === cg.id);
            colleagues.push({ ...cg, schedules: cgSchedules });
        }
    }
    return colleagues;
}
```

For now, simplest: replace the menu with a single popover offering all 3 actions when clicking on an own shift. The user can pick.

- [ ] **Step 10: Feature test for /my-requests**

```php
<?php
// tests/Feature/MyRequestsControllerTest.php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ShiftTakeoverOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyRequestsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_caregiver_sees_own_offers(): void
    {
        $user = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create();
        $cg = Caregiver::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);
        ShiftTakeoverOffer::factory()->create(['offered_by_caregiver_id' => $cg->id]);

        $response = $this->actingAs($user)->get('/my-requests');

        $response->assertOk()->assertInertia(fn ($page) =>
            $page->component('my-requests/index')
                ->has('myTakeoverOffers', 1)
        );
    }
}
```

- [ ] **Step 11: Final build, full test suite, commit**

```bash
source ~/.nvm/nvm.sh && nvm use 22 && npm run build
php artisan test
git add app/Http/Controllers/MyRequestsController.php resources/js/components/schedule/ resources/js/components/notifications resources/js/pages/my-requests resources/js/pages/caregiver-schedule/index.tsx resources/js/components/app-sidebar.tsx routes/web.php tests/Feature/MyRequestsControllerTest.php
git commit -m "feat: shift action dialogs and /my-requests page"
```

---

## Self-review notes for the implementing agent

- **Spec coverage:** All flows from the spec are covered. Notification classes are described in Task 8 with a template; agent must create all 9 by following the pattern.
- **Stubs in early tasks:** Tasks 4–6 deliberately omit the `notify` calls and add them in Task 8 to avoid forward-references in tests. Don't skip the re-add step in Task 8.
- **Migrations order:** Task 3 needs the third migration for the back-reference FK (`selected_offer_id`) after both tables exist.
- **Frontend dialogs are minimal MVPs.** They get the job done but the implementer is free to polish (e.g., better date pickers, validation feedback) without changing behavior.
- **Manual testing checklist after Task 11:**
  1. Caregiver A offers shift → Caregiver B sees notification → B claims → schedule shows B on that date
  2. A direct swap A↔B → B accepts → both calendars updated
  3. A creates open swap → B bids → A accepts B's offer → both calendars updated, other bids declined
  4. Cancel/intrek flows for each type
  5. Delete the underlying schedule → all open requests auto-cancelled
  6. Run `php artisan shift-requests:expire` with past-date data → status changes to expired
