# Availability & Claim System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow budget holders to post available time slots that caregivers can claim, with automatic restoration when claims are cancelled.

**Architecture:** New `AvailabilitySlot` model with `open`/`claimed` status. Claiming creates a Schedule (recurring) or ScheduleException (one-time). Eloquent observers on Schedule and ScheduleException reset slots to `open` when the linked appointment is deleted.

**Tech Stack:** Laravel 13, Inertia.js, React, TypeScript, PostgreSQL, PHPUnit

**Spec:** `docs/superpowers/specs/2026-05-27-availability-claim-design.md`

---

## File Structure

### Backend (PHP)

```
app/
├── Enums/
│   └── AvailabilitySlotStatus.php          # open, claimed
├── Http/
│   ├── Controllers/
│   │   ├── AvailabilitySlotController.php  # store, destroy (budget holder)
│   │   └── AvailabilityClaimController.php # store (caregiver claims)
│   └── Requests/
│       └── AvailabilitySlotRequest.php
├── Models/
│   └── AvailabilitySlot.php
├── Observers/
│   ├── ScheduleObserver.php
│   └── ScheduleExceptionObserver.php
├── Policies/
│   └── AvailabilitySlotPolicy.php
database/
├── factories/
│   └── AvailabilitySlotFactory.php
└── migrations/
    └── xxxx_create_availability_slots_table.php
tests/
├── Unit/Models/
│   └── AvailabilitySlotTest.php
└── Feature/
    ├── AvailabilitySlotControllerTest.php
    ├── AvailabilityClaimControllerTest.php
    └── AvailabilitySlotObserverTest.php
```

### Frontend (React/TypeScript)

```
resources/js/
├── types/
│   └── models.ts                                   # add AvailabilitySlot interface
├── components/schedule/
│   └── week-view.tsx                               # add 'available' variant + onClaim callback
├── pages/
│   ├── schedule/index.tsx                          # add availability form + pass slots to WeekView
│   └── caregiver-schedule/index.tsx                # show open slots with claim button
```

### Modified existing files

```
app/Models/Client.php                               # add availabilitySlots() relationship
routes/web.php                                      # add availability + claim routes
app/Providers/AppServiceProvider.php                 # register observers
app/Http/Controllers/ScheduleController.php          # pass availability slots to index
app/Http/Controllers/CaregiverScheduleController.php # load open availability slots
```

---

## Task 1: AvailabilitySlotStatus Enum, Model, Migration & Factory

**Files:**
- Create: `app/Enums/AvailabilitySlotStatus.php`
- Create: `database/migrations/xxxx_create_availability_slots_table.php`
- Create: `app/Models/AvailabilitySlot.php`
- Create: `database/factories/AvailabilitySlotFactory.php`
- Modify: `app/Models/Client.php`
- Create: `tests/Unit/Models/AvailabilitySlotTest.php`

- [ ] **Step 1: Write the tests**

Create `tests/Unit/Models/AvailabilitySlotTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\AvailabilitySlotStatus;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilitySlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_slot_belongs_to_client(): void
    {
        $client = Client::factory()->create();
        $slot = AvailabilitySlot::factory()->recurring()->create(['client_id' => $client->id]);

        $this->assertEquals($client->id, $slot->client->id);
    }

    public function test_client_has_availability_slots(): void
    {
        $client = Client::factory()->create();
        AvailabilitySlot::factory()->recurring()->count(3)->create(['client_id' => $client->id]);

        $this->assertCount(3, $client->availabilitySlots);
    }

    public function test_recurring_slot_has_day_of_week_and_no_date(): void
    {
        $slot = AvailabilitySlot::factory()->recurring()->create();

        $this->assertNotNull($slot->day_of_week);
        $this->assertNull($slot->date);
    }

    public function test_one_time_slot_has_date_and_no_day_of_week(): void
    {
        $slot = AvailabilitySlot::factory()->oneTime()->create();

        $this->assertNull($slot->day_of_week);
        $this->assertNotNull($slot->date);
    }

    public function test_slot_defaults_to_open_status(): void
    {
        $slot = AvailabilitySlot::factory()->recurring()->create();

        $this->assertEquals(AvailabilitySlotStatus::Open, $slot->status);
    }

    public function test_claimed_slot_has_caregiver_and_timestamp(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $slot = AvailabilitySlot::factory()->recurring()->create([
            'client_id' => $client->id,
            'status' => AvailabilitySlotStatus::Claimed,
            'claimed_by' => $caregiver->id,
            'claimed_at' => now(),
            'schedule_id' => $schedule->id,
        ]);

        $this->assertEquals(AvailabilitySlotStatus::Claimed, $slot->status);
        $this->assertEquals($caregiver->id, $slot->claimedBy->id);
        $this->assertNotNull($slot->claimed_at);
        $this->assertEquals($schedule->id, $slot->schedule->id);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Unit/Models/AvailabilitySlotTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Create the enum**

Create `app/Enums/AvailabilitySlotStatus.php`:

```php
<?php

namespace App\Enums;

enum AvailabilitySlotStatus: string
{
    case Open = 'open';
    case Claimed = 'claimed';
}
```

- [ ] **Step 4: Create migration, model, and factory**

```bash
php artisan make:model AvailabilitySlot -mf
```

Edit the generated migration:

```php
public function up(): void
{
    Schema::create('availability_slots', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->tinyInteger('day_of_week')->nullable();
        $table->date('date')->nullable();
        $table->time('start_time');
        $table->time('end_time');
        $table->string('status')->default('open');
        $table->foreignId('claimed_by')->nullable()->constrained('caregivers')->nullOnDelete();
        $table->timestamp('claimed_at')->nullable();
        $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('schedule_exception_id')->nullable()->constrained()->nullOnDelete();
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}
```

Edit `app/Models/AvailabilitySlot.php`:

```php
<?php

namespace App\Models;

use App\Enums\AvailabilitySlotStatus;
use Database\Factories\AvailabilitySlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilitySlot extends Model
{
    /** @use HasFactory<AvailabilitySlotFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'day_of_week',
        'date',
        'start_time',
        'end_time',
        'status',
        'claimed_by',
        'claimed_at',
        'schedule_id',
        'schedule_exception_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'claimed_at' => 'datetime',
            'status' => AvailabilitySlotStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class, 'claimed_by');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function scheduleException(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class);
    }
}
```

Edit `database/factories/AvailabilitySlotFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\AvailabilitySlotStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class AvailabilitySlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'status' => AvailabilitySlotStatus::Open,
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn () => [
            'day_of_week' => fake()->numberBetween(0, 6),
            'date' => null,
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn () => [
            'day_of_week' => null,
            'date' => fake()->dateTimeBetween('now', '+1 month'),
        ]);
    }
}
```

Add the relationship to `app/Models/Client.php` — add this method:

```php
public function availabilitySlots(): HasMany
{
    return $this->hasMany(AvailabilitySlot::class);
}
```

- [ ] **Step 5: Run migration and tests**

```bash
php artisan migrate
php artisan test tests/Unit/Models/AvailabilitySlotTest.php
```

Expected: PASS (6 tests)

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Enums/AvailabilitySlotStatus.php app/Models/AvailabilitySlot.php app/Models/Client.php database/migrations/*availability* database/factories/AvailabilitySlotFactory.php tests/Unit/Models/AvailabilitySlotTest.php
git commit -m "feat: add AvailabilitySlot model with enum, migration, and factory"
```

---

## Task 2: AvailabilitySlot Policy, Request, Controller & Routes

**Files:**
- Create: `app/Policies/AvailabilitySlotPolicy.php`
- Create: `app/Http/Requests/AvailabilitySlotRequest.php`
- Create: `app/Http/Controllers/AvailabilitySlotController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/AvailabilitySlotControllerTest.php`

- [ ] **Step 1: Write the tests**

Create `tests/Feature/AvailabilitySlotControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilitySlotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_budget_holder_can_create_recurring_availability_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->post("/clients/{$client->id}/availability-slots", [
            'type' => 'recurring',
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('availability_slots', [
            'client_id' => $client->id,
            'day_of_week' => 2,
            'date' => null,
            'status' => 'open',
        ]);
    }

    public function test_budget_holder_can_create_one_time_availability_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);

        $response = $this->actingAs($user)->post("/clients/{$client->id}/availability-slots", [
            'type' => 'one_time',
            'date' => '2026-06-15',
            'start_time' => '14:00',
            'end_time' => '17:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('availability_slots', [
            'client_id' => $client->id,
            'day_of_week' => null,
            'date' => '2026-06-15',
        ]);
    }

    public function test_budget_holder_can_delete_open_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $slot = AvailabilitySlot::factory()->recurring()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->delete("/clients/{$client->id}/availability-slots/{$slot->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('availability_slots', ['id' => $slot->id]);
    }

    public function test_deleting_claimed_slot_also_deletes_linked_schedule(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);
        $slot = AvailabilitySlot::factory()->recurring()->create([
            'client_id' => $client->id,
            'status' => AvailabilitySlotStatus::Claimed,
            'claimed_by' => $caregiver->id,
            'claimed_at' => now(),
            'schedule_id' => $schedule->id,
        ]);

        $this->actingAs($user)->delete("/clients/{$client->id}/availability-slots/{$slot->id}");

        $this->assertDatabaseMissing('availability_slots', ['id' => $slot->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_other_budget_holder_cannot_create_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $otherClient = Client::factory()->create();

        $response = $this->actingAs($user)->post("/clients/{$otherClient->id}/availability-slots", [
            'type' => 'recurring',
            'day_of_week' => 0,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $response->assertForbidden();
    }

    public function test_caregiver_cannot_create_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create();

        $response = $this->actingAs($user)->post("/clients/{$client->id}/availability-slots", [
            'type' => 'recurring',
            'day_of_week' => 0,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/AvailabilitySlotControllerTest.php
```

Expected: FAIL

- [ ] **Step 3: Create the policy**

```bash
php artisan make:policy AvailabilitySlotPolicy --model=AvailabilitySlot
```

Edit `app/Policies/AvailabilitySlotPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\User;

class AvailabilitySlotPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    public function delete(User $user, AvailabilitySlot $slot): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $slot->client->budget_holder_id === $user->id;
    }
}
```

- [ ] **Step 4: Create the form request**

```bash
php artisan make:request AvailabilitySlotRequest
```

Edit `app/Http/Requests/AvailabilitySlotRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvailabilitySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:recurring,one_time'],
            'day_of_week' => ['required_if:type,recurring', 'nullable', 'integer', 'min:0', 'max:6'],
            'date' => ['required_if:type,one_time', 'nullable', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

```bash
php artisan make:controller AvailabilitySlotController
```

Edit `app/Http/Controllers/AvailabilitySlotController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvailabilitySlotRequest;
use App\Models\AvailabilitySlot;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;

class AvailabilitySlotController extends Controller
{
    public function store(AvailabilitySlotRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);
        $this->authorize('create', AvailabilitySlot::class);

        $validated = $request->validated();

        $client->availabilitySlots()->create([
            'day_of_week' => $validated['type'] === 'recurring' ? $validated['day_of_week'] : null,
            'date' => $validated['type'] === 'one_time' ? $validated['date'] : null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('clients.schedule.index', $client);
    }

    public function destroy(Client $client, AvailabilitySlot $availabilitySlot): RedirectResponse
    {
        $this->authorize('delete', $availabilitySlot);

        if ($availabilitySlot->schedule_id) {
            $availabilitySlot->schedule?->delete();
        }
        if ($availabilitySlot->schedule_exception_id) {
            $availabilitySlot->scheduleException?->delete();
        }

        $availabilitySlot->delete();

        return redirect()->route('clients.schedule.index', $client);
    }
}
```

- [ ] **Step 6: Add routes**

Add to `routes/web.php` inside the `role:budget_holder` middleware group:

```php
use App\Http\Controllers\AvailabilitySlotController;

Route::post('clients/{client}/availability-slots', [AvailabilitySlotController::class, 'store'])->name('clients.availability-slots.store');
Route::delete('clients/{client}/availability-slots/{availabilitySlot}', [AvailabilitySlotController::class, 'destroy'])->name('clients.availability-slots.destroy');
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/AvailabilitySlotControllerTest.php
```

Expected: PASS (6 tests)

- [ ] **Step 8: Run full test suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 9: Commit**

```bash
git add app/Policies/AvailabilitySlotPolicy.php app/Http/Requests/AvailabilitySlotRequest.php app/Http/Controllers/AvailabilitySlotController.php routes/web.php tests/Feature/AvailabilitySlotControllerTest.php
git commit -m "feat: add AvailabilitySlot controller, policy, and routes for budget holders"
```

---

## Task 3: Claim Controller & Observer Reset Logic

**Files:**
- Create: `app/Http/Controllers/AvailabilityClaimController.php`
- Create: `app/Observers/ScheduleObserver.php`
- Create: `app/Observers/ScheduleExceptionObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/AvailabilityClaimControllerTest.php`
- Create: `tests/Feature/AvailabilitySlotObserverTest.php`

- [ ] **Step 1: Write claim controller tests**

Create `tests/Feature/AvailabilityClaimControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityClaimControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_claim_open_recurring_slot(): void
    {
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);
        $slot = AvailabilitySlot::factory()->recurring()->create([
            'client_id' => $client->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $response = $this->actingAs($caregiverUser)->post("/availability-slots/{$slot->id}/claim");

        $response->assertRedirect();
        $slot->refresh();
        $this->assertEquals(AvailabilitySlotStatus::Claimed, $slot->status);
        $this->assertEquals($caregiver->id, $slot->claimed_by);
        $this->assertNotNull($slot->claimed_at);
        $this->assertNotNull($slot->schedule_id);
        $this->assertDatabaseHas('schedules', [
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);
    }

    public function test_caregiver_can_claim_open_one_time_slot(): void
    {
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);
        $slot = AvailabilitySlot::factory()->oneTime()->create([
            'client_id' => $client->id,
            'date' => '2026-06-15',
            'start_time' => '14:00',
            'end_time' => '17:00',
        ]);

        $response = $this->actingAs($caregiverUser)->post("/availability-slots/{$slot->id}/claim");

        $response->assertRedirect();
        $slot->refresh();
        $this->assertEquals(AvailabilitySlotStatus::Claimed, $slot->status);
        $this->assertNotNull($slot->schedule_exception_id);
        $this->assertDatabaseHas('schedule_exceptions', [
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'date' => '2026-06-15',
            'type' => 'added',
        ]);
    }

    public function test_caregiver_cannot_claim_already_claimed_slot(): void
    {
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create();
        Caregiver::factory()->create([
            'client_id' => $client->id,
            'user_id' => $caregiverUser->id,
        ]);
        $slot = AvailabilitySlot::factory()->recurring()->create([
            'client_id' => $client->id,
            'status' => AvailabilitySlotStatus::Claimed,
        ]);

        $response = $this->actingAs($caregiverUser)->post("/availability-slots/{$slot->id}/claim");

        $response->assertStatus(409);
    }

    public function test_caregiver_not_linked_to_client_cannot_claim(): void
    {
        $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
        $client = Client::factory()->create();
        $slot = AvailabilitySlot::factory()->recurring()->create(['client_id' => $client->id]);

        $response = $this->actingAs($caregiverUser)->post("/availability-slots/{$slot->id}/claim");

        $response->assertForbidden();
    }

    public function test_budget_holder_cannot_claim_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $client = Client::factory()->create(['budget_holder_id' => $user->id]);
        $slot = AvailabilitySlot::factory()->recurring()->create(['client_id' => $client->id]);

        $response = $this->actingAs($user)->post("/availability-slots/{$slot->id}/claim");

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Write observer tests**

Create `tests/Feature/AvailabilitySlotObserverTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\ScheduleExceptionType;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilitySlotObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_schedule_resets_linked_availability_slot(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);
        $slot = AvailabilitySlot::factory()->recurring()->create([
            'client_id' => $client->id,
            'status' => AvailabilitySlotStatus::Claimed,
            'claimed_by' => $caregiver->id,
            'claimed_at' => now(),
            'schedule_id' => $schedule->id,
        ]);

        $schedule->delete();

        $slot->refresh();
        $this->assertEquals(AvailabilitySlotStatus::Open, $slot->status);
        $this->assertNull($slot->claimed_by);
        $this->assertNull($slot->claimed_at);
        $this->assertNull($slot->schedule_id);
    }

    public function test_deleting_schedule_exception_resets_linked_availability_slot(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $exception = ScheduleException::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
            'schedule_id' => null,
            'type' => ScheduleExceptionType::Added,
        ]);
        $slot = AvailabilitySlot::factory()->oneTime()->create([
            'client_id' => $client->id,
            'status' => AvailabilitySlotStatus::Claimed,
            'claimed_by' => $caregiver->id,
            'claimed_at' => now(),
            'schedule_exception_id' => $exception->id,
        ]);

        $exception->delete();

        $slot->refresh();
        $this->assertEquals(AvailabilitySlotStatus::Open, $slot->status);
        $this->assertNull($slot->claimed_by);
        $this->assertNull($slot->claimed_at);
        $this->assertNull($slot->schedule_exception_id);
    }

    public function test_deleting_unlinked_schedule_does_not_affect_slots(): void
    {
        $client = Client::factory()->create();
        $caregiver = Caregiver::factory()->create(['client_id' => $client->id]);
        $schedule = Schedule::factory()->create([
            'client_id' => $client->id,
            'caregiver_id' => $caregiver->id,
        ]);

        $schedule->delete();

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
php artisan test tests/Feature/AvailabilityClaimControllerTest.php tests/Feature/AvailabilitySlotObserverTest.php
```

Expected: FAIL

- [ ] **Step 4: Create the observers**

Create `app/Observers/ScheduleObserver.php`:

```php
<?php

namespace App\Observers;

use App\Enums\AvailabilitySlotStatus;
use App\Models\AvailabilitySlot;
use App\Models\Schedule;

class ScheduleObserver
{
    public function deleted(Schedule $schedule): void
    {
        AvailabilitySlot::where('schedule_id', $schedule->id)->update([
            'status' => AvailabilitySlotStatus::Open,
            'claimed_by' => null,
            'claimed_at' => null,
            'schedule_id' => null,
        ]);
    }
}
```

Create `app/Observers/ScheduleExceptionObserver.php`:

```php
<?php

namespace App\Observers;

use App\Enums\AvailabilitySlotStatus;
use App\Models\AvailabilitySlot;
use App\Models\ScheduleException;

class ScheduleExceptionObserver
{
    public function deleted(ScheduleException $exception): void
    {
        AvailabilitySlot::where('schedule_exception_id', $exception->id)->update([
            'status' => AvailabilitySlotStatus::Open,
            'claimed_by' => null,
            'claimed_at' => null,
            'schedule_exception_id' => null,
        ]);
    }
}
```

- [ ] **Step 5: Register observers**

Edit `app/Providers/AppServiceProvider.php` — add to the `boot()` method:

```php
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Observers\ScheduleObserver;
use App\Observers\ScheduleExceptionObserver;

public function boot(): void
{
    Schedule::observe(ScheduleObserver::class);
    ScheduleException::observe(ScheduleExceptionObserver::class);
}
```

- [ ] **Step 6: Create the claim controller**

```bash
php artisan make:controller AvailabilityClaimController
```

Edit `app/Http/Controllers/AvailabilityClaimController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AvailabilitySlotStatus;
use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\Caregiver;
use Illuminate\Http\RedirectResponse;

class AvailabilityClaimController extends Controller
{
    public function store(AvailabilitySlot $availabilitySlot): RedirectResponse
    {
        $user = auth()->user();

        if ($user->role !== UserRole::Caregiver) {
            abort(403);
        }

        $caregiver = Caregiver::where('user_id', $user->id)
            ->where('client_id', $availabilitySlot->client_id)
            ->first();

        if (!$caregiver) {
            abort(403);
        }

        if ($availabilitySlot->status !== AvailabilitySlotStatus::Open) {
            abort(409, 'Dit slot is al geclaimd.');
        }

        if ($availabilitySlot->day_of_week !== null) {
            $schedule = $availabilitySlot->client->schedules()->create([
                'caregiver_id' => $caregiver->id,
                'day_of_week' => $availabilitySlot->day_of_week,
                'start_time' => $availabilitySlot->start_time,
                'end_time' => $availabilitySlot->end_time,
            ]);
            $availabilitySlot->update([
                'status' => AvailabilitySlotStatus::Claimed,
                'claimed_by' => $caregiver->id,
                'claimed_at' => now(),
                'schedule_id' => $schedule->id,
            ]);
        } else {
            $exception = $availabilitySlot->client->scheduleExceptions()->create([
                'caregiver_id' => $caregiver->id,
                'date' => $availabilitySlot->date,
                'start_time' => $availabilitySlot->start_time,
                'end_time' => $availabilitySlot->end_time,
                'type' => ScheduleExceptionType::Added,
            ]);
            $availabilitySlot->update([
                'status' => AvailabilitySlotStatus::Claimed,
                'claimed_by' => $caregiver->id,
                'claimed_at' => now(),
                'schedule_exception_id' => $exception->id,
            ]);
        }

        return redirect()->route('my-schedule');
    }
}
```

- [ ] **Step 7: Add claim route**

Add to `routes/web.php` — after the `my-schedule` route, still inside `auth+verified` middleware but with `role:caregiver`:

```php
use App\Http\Controllers\AvailabilityClaimController;

Route::post('/availability-slots/{availabilitySlot}/claim', [AvailabilityClaimController::class, 'store'])
    ->middleware(['auth', 'verified', 'role:caregiver'])
    ->name('availability-slots.claim');
```

- [ ] **Step 8: Run tests**

```bash
php artisan test tests/Feature/AvailabilityClaimControllerTest.php tests/Feature/AvailabilitySlotObserverTest.php
```

Expected: PASS (8 tests)

- [ ] **Step 9: Run full test suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/AvailabilityClaimController.php app/Observers/ app/Providers/AppServiceProvider.php routes/web.php tests/Feature/AvailabilityClaimControllerTest.php tests/Feature/AvailabilitySlotObserverTest.php
git commit -m "feat: add claim controller and observers for availability slot reset"
```

---

## Task 4: TypeScript Types & WeekView Availability Variant

**Files:**
- Modify: `resources/js/types/models.ts`
- Modify: `resources/js/components/schedule/week-view.tsx`

- [ ] **Step 1: Add AvailabilitySlot TypeScript interface**

Add to `resources/js/types/models.ts`:

```typescript
export interface AvailabilitySlot {
    id: number;
    client_id: number;
    day_of_week: number | null;
    date: string | null;
    start_time: string;
    end_time: string;
    status: 'open' | 'claimed';
    claimed_by: number | null;
    claimed_at: string | null;
    notes: string | null;
}
```

- [ ] **Step 2: Add 'available' variant to WeekView**

In `resources/js/components/schedule/week-view.tsx`:

Update the `EventBlock` interface's `variant` type:

```typescript
variant: 'regular' | 'added' | 'modified' | 'cancelled' | 'available';
```

Add an optional `onAction` callback and `actionLabel` to `EventBlock`:

```typescript
interface EventBlock {
    id: string;
    label: string;
    sublabel?: string;
    startTime: string;
    endTime: string;
    variant: 'regular' | 'added' | 'modified' | 'cancelled' | 'available';
    onDelete?: () => void;
    onAction?: () => void;
    actionLabel?: string;
}
```

Add the `available` variant styles in `EventCard`:

In the `styles` object:
```typescript
available: 'bg-purple-50 border-purple-300 border-dashed text-purple-900 dark:bg-purple-950/40 dark:border-purple-700 dark:text-purple-200',
```

In the `timeStyles` object:
```typescript
available: 'text-purple-600 dark:text-purple-400',
```

Add the action button in `EventCard`, after the delete button section:

```tsx
{event.onAction && (
    <Button
        variant="ghost"
        size="sm"
        className="h-5 shrink-0 px-1.5 text-[10px] font-medium text-purple-700 opacity-0 group-hover:opacity-100"
        onClick={(e) => {
            e.stopPropagation();
            event.onAction!();
        }}
    >
        {event.actionLabel ?? 'Claimen'}
    </Button>
)}
```

Update the `WeekViewProps` interface to accept availability slots:

```typescript
interface WeekViewProps {
    schedules: Schedule[];
    exceptions: ScheduleException[];
    availabilitySlots?: AvailabilitySlot[];
    weekStart: Date;
    onDeleteSchedule?: (id: number) => void;
    onDeleteException?: (id: number) => void;
    onDeleteAvailability?: (id: number) => void;
    onClaimAvailability?: (id: number) => void;
}
```

Update the function signature to accept new props:

```typescript
export function WeekView({
    schedules,
    exceptions,
    availabilitySlots = [],
    weekStart,
    onDeleteSchedule,
    onDeleteException,
    onDeleteAvailability,
    onClaimAvailability,
}: WeekViewProps) {
```

In `getEventsForDay`, add availability slots to the events list — after the existing exception handling:

```typescript
// Availability slots (purple, dashed)
availabilitySlots
    .filter((slot) => {
        if (slot.status !== 'open') return false;
        if (slot.day_of_week !== null) return slot.day_of_week === dayIndex;
        return slot.date === dateStr;
    })
    .forEach((slot) => {
        events.push({
            id: `av-${slot.id}`,
            label: 'Beschikbaar',
            sublabel: slot.notes ?? undefined,
            startTime: slot.start_time,
            endTime: slot.end_time,
            variant: 'available',
            onDelete: onDeleteAvailability ? () => onDeleteAvailability(slot.id) : undefined,
            onAction: onClaimAvailability ? () => onClaimAvailability(slot.id) : undefined,
            actionLabel: 'Claimen',
        });
    });
```

Add the availability legend item — in the legend `<div>` before the closing `</div>`:

```tsx
<div className="flex items-center gap-1.5">
    <span className="h-3 w-3 rounded border border-dashed border-purple-300 bg-purple-50" />
    Beschikbaar
</div>
```

- [ ] **Step 3: Verify TypeScript compiles**

```bash
npx tsc --noEmit
```

Expected: no new errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/schedule/week-view.tsx
git commit -m "feat: add availability slot type and purple dashed variant to WeekView"
```

---

## Task 5: Wire Up Schedule Page (Budget Holder) & Caregiver Schedule Page

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php`
- Modify: `app/Http/Controllers/CaregiverScheduleController.php`
- Modify: `resources/js/pages/schedule/index.tsx`
- Modify: `resources/js/pages/caregiver-schedule/index.tsx`

- [ ] **Step 1: Update ScheduleController to pass availability slots**

In `app/Http/Controllers/ScheduleController.php`, update the `index` method to also load and pass availability slots:

```php
public function index(Client $client): Response
{
    $this->authorize('view', $client);

    $client->load(['schedules.caregiver', 'scheduleExceptions.caregiver', 'caregivers']);

    return Inertia::render('schedule/index', [
        'schedules' => $client->schedules,
        'exceptions' => $client->scheduleExceptions,
        'availabilitySlots' => $client->availabilitySlots()->where('status', 'open')->get(),
        'caregivers' => $client->caregivers,
        'client' => $client,
    ]);
}
```

- [ ] **Step 2: Update CaregiverScheduleController to pass open availability slots**

In `app/Http/Controllers/CaregiverScheduleController.php`, update `__invoke` to load open slots for each client:

```php
public function __invoke(): Response
{
    $user = auth()->user();

    $caregiverRecords = Caregiver::where('user_id', $user->id)->get();

    $clients = $caregiverRecords->map(function (Caregiver $caregiver) {
        $client = $caregiver->client;
        $client->load([
            'schedules' => fn ($q) => $q->where('caregiver_id', $caregiver->id),
            'schedules.caregiver',
            'scheduleExceptions' => fn ($q) => $q->where('caregiver_id', $caregiver->id),
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
    ]);
}
```

- [ ] **Step 3: Update schedule/index.tsx — add availability form and wire up WeekView**

In `resources/js/pages/schedule/index.tsx`:

Add import for `AvailabilitySlot` type. Add `availabilitySlots` prop. Add state and form for the availability form. Add `deleteAvailability` handler. Pass `availabilitySlots` and `onDeleteAvailability` to `<WeekView>`.

Add to the component props:

```typescript
export default function ScheduleIndex({
    schedules,
    exceptions,
    availabilitySlots,
    caregivers,
    client,
}: {
    schedules: Schedule[];
    exceptions: ScheduleException[];
    availabilitySlots: AvailabilitySlot[];
    caregivers: Caregiver[];
    client: Client;
}) {
```

Add availability form state and handler:

```typescript
const [showAvailabilityForm, setShowAvailabilityForm] = useState(false);

const availabilityForm = useForm({
    type: 'recurring',
    day_of_week: '',
    date: '',
    start_time: '',
    end_time: '',
    notes: '',
});

function submitAvailability(e: React.FormEvent) {
    e.preventDefault();
    availabilityForm.post(`/clients/${client.id}/availability-slots`, {
        onSuccess: () => {
            availabilityForm.reset();
            setShowAvailabilityForm(false);
        },
    });
}

function deleteAvailability(id: number) {
    router.delete(`/clients/${client.id}/availability-slots/${id}`);
}
```

Add a "+ Beschikbaarheid" button in the header alongside the existing buttons:

```tsx
<Button
    variant="outline"
    onClick={() => setShowAvailabilityForm(!showAvailabilityForm)}
>
    <Plus />
    Beschikbaarheid
</Button>
```

Add the availability form (similar structure to the existing schedule/exception forms). Show a select to choose between "Terugkerend" and "Eenmalig", then conditionally show either a day-of-week select or a date input, plus start/end time:

```tsx
{showAvailabilityForm && (
    <Card>
        <CardHeader>
            <CardTitle className="text-base">Beschikbaarheid toevoegen</CardTitle>
        </CardHeader>
        <CardContent>
            <form onSubmit={submitAvailability} className="flex flex-col gap-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label>Type</Label>
                        <Select
                            value={availabilityForm.data.type}
                            onValueChange={(val) => availabilityForm.setData('type', val)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="recurring">Terugkerend</SelectItem>
                                <SelectItem value="one_time">Eenmalig</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    {availabilityForm.data.type === 'recurring' ? (
                        <div className="space-y-2">
                            <Label>Dag</Label>
                            <Select
                                value={availabilityForm.data.day_of_week}
                                onValueChange={(val) => availabilityForm.setData('day_of_week', val)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecteer dag" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dayOptions.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {availabilityForm.errors.day_of_week && (
                                <p className="text-sm text-red-500">{availabilityForm.errors.day_of_week}</p>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-2">
                            <Label htmlFor="av-date">Datum</Label>
                            <Input
                                id="av-date"
                                type="date"
                                value={availabilityForm.data.date}
                                onChange={(e) => availabilityForm.setData('date', e.target.value)}
                            />
                            {availabilityForm.errors.date && (
                                <p className="text-sm text-red-500">{availabilityForm.errors.date}</p>
                            )}
                        </div>
                    )}
                    <div className="space-y-2">
                        <Label htmlFor="av-start">Starttijd</Label>
                        <Input
                            id="av-start"
                            type="time"
                            value={availabilityForm.data.start_time}
                            onChange={(e) => availabilityForm.setData('start_time', e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="av-end">Eindtijd</Label>
                        <Input
                            id="av-end"
                            type="time"
                            value={availabilityForm.data.end_time}
                            onChange={(e) => availabilityForm.setData('end_time', e.target.value)}
                        />
                    </div>
                </div>
                <div className="flex gap-2">
                    <Button type="submit" disabled={availabilityForm.processing}>Opslaan</Button>
                    <Button type="button" variant="outline" onClick={() => setShowAvailabilityForm(false)}>Annuleren</Button>
                </div>
            </form>
        </CardContent>
    </Card>
)}
```

Update the `<WeekView>` component call:

```tsx
<WeekView
    schedules={schedules}
    exceptions={exceptions}
    availabilitySlots={availabilitySlots}
    weekStart={weekStart}
    onDeleteSchedule={deleteSchedule}
    onDeleteException={deleteException}
    onDeleteAvailability={deleteAvailability}
/>
```

- [ ] **Step 4: Update caregiver-schedule/index.tsx — show open slots with claim**

In `resources/js/pages/caregiver-schedule/index.tsx`:

Update the `ClientWithSchedule` interface:

```typescript
interface ClientWithSchedule extends Client {
    schedules: Schedule[];
    schedule_exceptions: ScheduleException[];
    availability_slots: AvailabilitySlot[];
}
```

Add import for `AvailabilitySlot`, `router`.

Add claim handler:

```typescript
function claimSlot(slotId: number) {
    router.post(`/availability-slots/${slotId}/claim`);
}
```

Update the `<WeekView>` call inside the client map to pass availability slots and claim handler:

```tsx
<WeekView
    schedules={client.schedules ?? []}
    exceptions={client.schedule_exceptions ?? []}
    availabilitySlots={client.availability_slots ?? []}
    weekStart={weekStart}
    onClaimAvailability={claimSlot}
/>
```

- [ ] **Step 5: Build frontend**

```bash
source ~/.nvm/nvm.sh && nvm use 22 && npm run build
```

Expected: builds successfully.

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ScheduleController.php app/Http/Controllers/CaregiverScheduleController.php resources/js/pages/schedule/index.tsx resources/js/pages/caregiver-schedule/index.tsx
git commit -m "feat: wire up availability slots in schedule and caregiver pages"
```
