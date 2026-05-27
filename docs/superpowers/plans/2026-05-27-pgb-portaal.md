# PGB Portaal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a PGB portal for budget holders to manage care budgets and schedules for their clients.

**Architecture:** Laravel monolith with Inertia.js + React frontend. PostgreSQL database. Laravel Breeze provides auth scaffolding. Simple role-based authorization via a `role` column on users and Laravel Policy classes.

**Tech Stack:** Laravel 12, Inertia.js, React 19, TypeScript, Tailwind CSS 4, PostgreSQL, Pest (testing)

**Spec:** `docs/superpowers/specs/2026-05-27-pgb-portaal-design.md`

---

## File Structure

### Backend (PHP)

```
app/
├── Enums/
│   ├── UserRole.php                    # budget_holder, caregiver
│   ├── CaregiverType.php              # parent, care_worker, day_care, zzp, other
│   └── ScheduleExceptionType.php      # cancelled, modified, added
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php
│   │   ├── ClientController.php
│   │   ├── CaregiverController.php
│   │   ├── BudgetCategoryController.php
│   │   ├── BudgetExpenseController.php
│   │   ├── ScheduleController.php
│   │   └── ScheduleExceptionController.php
│   ├── Middleware/
│   │   └── EnsureRole.php
│   └── Requests/
│       ├── ClientRequest.php
│       ├── CaregiverRequest.php
│       ├── BudgetCategoryRequest.php
│       ├── BudgetExpenseRequest.php
│       ├── ScheduleRequest.php
│       └── ScheduleExceptionRequest.php
├── Models/
│   ├── User.php                        # modify existing
│   ├── Client.php
│   ├── Caregiver.php
│   ├── BudgetCategory.php
│   ├── BudgetExpense.php
│   ├── Schedule.php
│   └── ScheduleException.php
└── Policies/
    ├── ClientPolicy.php
    ├── CaregiverPolicy.php
    ├── BudgetCategoryPolicy.php
    ├── BudgetExpensePolicy.php
    └── SchedulePolicy.php

database/
├── factories/
│   ├── ClientFactory.php
│   ├── CaregiverFactory.php
│   ├── BudgetCategoryFactory.php
│   ├── BudgetExpenseFactory.php
│   ├── ScheduleFactory.php
│   └── ScheduleExceptionFactory.php
└── migrations/
    ├── xxxx_add_role_to_users_table.php
    ├── xxxx_create_clients_table.php
    ├── xxxx_create_caregivers_table.php
    ├── xxxx_create_budget_categories_table.php
    ├── xxxx_create_budget_expenses_table.php
    ├── xxxx_create_schedules_table.php
    └── xxxx_create_schedule_exceptions_table.php
```

### Frontend (React/TypeScript)

```
resources/js/
├── types/
│   └── models.d.ts                     # TypeScript interfaces for all models
├── Layouts/
│   └── AuthenticatedLayout.tsx         # modify existing — add PGB navigation
├── Components/
│   ├── Budget/
│   │   └── ProgressBar.tsx
│   └── Schedule/
│       └── WeekView.tsx
└── Pages/
    ├── Dashboard.tsx                   # replace existing
    ├── Clients/
    │   ├── Index.tsx
    │   ├── Show.tsx
    │   ├── Create.tsx
    │   └── Edit.tsx
    ├── Caregivers/
    │   ├── Index.tsx
    │   ├── Create.tsx
    │   └── Edit.tsx
    ├── Budget/
    │   └── Index.tsx
    ├── Schedule/
    │   └── Index.tsx
    └── CaregiverSchedule/
        └── Index.tsx                   # read-only view for caregiver role
```

---

## Task 1: Project Scaffolding

**Files:**
- Create: entire Laravel project via `laravel new`
- Modify: `.env`

- [ ] **Step 1: Create Laravel project with Breeze React stack**

```bash
cd /Users/jesse/dev/flowan
laravel new pgb --breeze --stack=react --typescript --pest --database=pgsql --no-interaction
```

This installs Laravel with Breeze (React + Inertia + TypeScript), Pest for testing, and configures PostgreSQL.

- [ ] **Step 2: Configure PostgreSQL connection**

Edit `.env` — set the database connection. Ensure a PostgreSQL database named `pgb` exists:

```bash
cd /Users/jesse/dev/flowan/pgb
createdb pgb 2>/dev/null; createdb pgb_test 2>/dev/null
```

Verify `.env` has:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pgb
```

- [ ] **Step 3: Run migrations and verify**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan migrate
php artisan test
```

Expected: all default Breeze tests pass.

- [ ] **Step 4: Initialize git and commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git init
git add -A
git commit -m "chore: scaffold Laravel + Breeze (React/Inertia/TypeScript)"
```

---

## Task 2: Enums and User Model Update

**Files:**
- Create: `app/Enums/UserRole.php`
- Create: `app/Enums/CaregiverType.php`
- Create: `app/Enums/ScheduleExceptionType.php`
- Create: `database/migrations/xxxx_add_role_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Create: `tests/Unit/Enums/UserRoleTest.php`

- [ ] **Step 1: Write enum tests**

Create `tests/Unit/Enums/UserRoleTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Enums\CaregiverType;
use App\Enums\ScheduleExceptionType;

it('has budget_holder and caregiver roles', function () {
    expect(UserRole::cases())->toHaveCount(2);
    expect(UserRole::BudgetHolder->value)->toBe('budget_holder');
    expect(UserRole::Caregiver->value)->toBe('caregiver');
});

it('has all caregiver types', function () {
    expect(CaregiverType::cases())->toHaveCount(5);
    expect(CaregiverType::Parent->value)->toBe('parent');
    expect(CaregiverType::CareWorker->value)->toBe('care_worker');
    expect(CaregiverType::DayCare->value)->toBe('day_care');
    expect(CaregiverType::Zzp->value)->toBe('zzp');
    expect(CaregiverType::Other->value)->toBe('other');
});

it('has all schedule exception types', function () {
    expect(ScheduleExceptionType::cases())->toHaveCount(3);
    expect(ScheduleExceptionType::Cancelled->value)->toBe('cancelled');
    expect(ScheduleExceptionType::Modified->value)->toBe('modified');
    expect(ScheduleExceptionType::Added->value)->toBe('added');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Unit/Enums/UserRoleTest.php
```

Expected: FAIL — classes not found.

- [ ] **Step 3: Create enums**

Create `app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case BudgetHolder = 'budget_holder';
    case Caregiver = 'caregiver';
}
```

Create `app/Enums/CaregiverType.php`:

```php
<?php

namespace App\Enums;

enum CaregiverType: string
{
    case Parent = 'parent';
    case CareWorker = 'care_worker';
    case DayCare = 'day_care';
    case Zzp = 'zzp';
    case Other = 'other';
}
```

Create `app/Enums/ScheduleExceptionType.php`:

```php
<?php

namespace App\Enums;

enum ScheduleExceptionType: string
{
    case Cancelled = 'cancelled';
    case Modified = 'modified';
    case Added = 'added';
}
```

- [ ] **Step 4: Run enum tests to verify they pass**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Unit/Enums/UserRoleTest.php
```

Expected: PASS

- [ ] **Step 5: Write User model test**

Create `tests/Unit/Models/UserTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

it('has a role attribute cast to UserRole enum', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);

    expect($user->role)->toBe(UserRole::BudgetHolder);
});

it('defaults role to budget_holder', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::BudgetHolder);
});
```

- [ ] **Step 6: Run test to verify it fails**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Unit/Models/UserTest.php
```

Expected: FAIL — role column does not exist.

- [ ] **Step 7: Create migration and update User model**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:migration add_role_to_users_table --table=users
```

Edit the generated migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('budget_holder')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
```

Update `app/Models/User.php` — add to the `$fillable` array and add a cast:

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => \App\Enums\UserRole::class,
    ];
}
```

Update `database/factories/UserFactory.php` — add role to definition:

```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => static::$password ??= Hash::make('password'),
        'remember_token' => Str::random(10),
        'role' => UserRole::BudgetHolder,
    ];
}
```

Add the import at the top of the factory:

```php
use App\Enums\UserRole;
```

- [ ] **Step 8: Run migration and tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan migrate
php artisan test tests/Unit/Models/UserTest.php
```

Expected: PASS

- [ ] **Step 9: Run full test suite**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test
```

Expected: all tests pass.

- [ ] **Step 10: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Enums/ database/migrations/*add_role* app/Models/User.php database/factories/UserFactory.php tests/Unit/
git commit -m "feat: add user roles and domain enums"
```

---

## Task 3: Client Model, Migration & Factory

**Files:**
- Create: `database/migrations/xxxx_create_clients_table.php`
- Create: `app/Models/Client.php`
- Create: `database/factories/ClientFactory.php`
- Modify: `app/Models/User.php` (add relationship)
- Create: `tests/Unit/Models/ClientTest.php`

- [ ] **Step 1: Write Client model tests**

Create `tests/Unit/Models/ClientTest.php`:

```php
<?php

use App\Models\Client;
use App\Models\User;
use App\Enums\UserRole;

it('belongs to a budget holder', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();

    expect($client->budgetHolder->id)->toBe($user->id);
});

it('is accessible from the budget holder', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();

    expect($user->clients)->toHaveCount(1);
    expect($user->clients->first()->id)->toBe($client->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Unit/Models/ClientTest.php
```

Expected: FAIL — Client model not found.

- [ ] **Step 3: Create migration, model, and factory**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:model Client -mf
```

Edit the generated migration:

```php
public function up(): void
{
    Schema::create('clients', function (Blueprint $table) {
        $table->id();
        $table->foreignId('budget_holder_id')->constrained('users')->cascadeOnDelete();
        $table->string('name');
        $table->date('date_of_birth');
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}
```

Edit `app/Models/Client.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_holder_id',
        'name',
        'date_of_birth',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function budgetHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_holder_id');
    }

    public function caregivers(): HasMany
    {
        return $this->hasMany(Caregiver::class);
    }

    public function budgetCategories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }
}
```

Edit `database/factories/ClientFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'budget_holder_id' => User::factory(['role' => UserRole::BudgetHolder]),
            'name' => fake()->name(),
            'date_of_birth' => fake()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
```

Add the `clients()` relationship to `app/Models/User.php`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function clients(): HasMany
{
    return $this->hasMany(Client::class, 'budget_holder_id');
}
```

- [ ] **Step 4: Run migration and tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan migrate
php artisan test tests/Unit/Models/ClientTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Models/ database/migrations/*create_clients* database/factories/ClientFactory.php tests/Unit/Models/ClientTest.php
git commit -m "feat: add Client model with budget holder relationship"
```

---

## Task 4: Caregiver Model, Migration & Factory

**Files:**
- Create: `database/migrations/xxxx_create_caregivers_table.php`
- Create: `app/Models/Caregiver.php`
- Create: `database/factories/CaregiverFactory.php`
- Create: `tests/Unit/Models/CaregiverTest.php`

- [ ] **Step 1: Write Caregiver model tests**

Create `tests/Unit/Models/CaregiverTest.php`:

```php
<?php

use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use App\Enums\CaregiverType;
use App\Enums\UserRole;

it('belongs to a client', function () {
    $caregiver = Caregiver::factory()->create();

    expect($caregiver->client)->toBeInstanceOf(Client::class);
});

it('optionally belongs to a user', function () {
    $user = User::factory()->create(['role' => UserRole::Caregiver]);
    $caregiver = Caregiver::factory()->create(['user_id' => $user->id]);

    expect($caregiver->user->id)->toBe($user->id);
});

it('has a caregiver type enum', function () {
    $caregiver = Caregiver::factory()->create(['type' => CaregiverType::Zzp]);

    expect($caregiver->type)->toBe(CaregiverType::Zzp);
});

it('is listed under the client caregivers', function () {
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->for($client)->create();

    expect($client->caregivers)->toHaveCount(1);
    expect($client->caregivers->first()->id)->toBe($caregiver->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Unit/Models/CaregiverTest.php
```

Expected: FAIL

- [ ] **Step 3: Create migration, model, and factory**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:model Caregiver -mf
```

Edit the generated migration:

```php
public function up(): void
{
    Schema::create('caregivers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        $table->string('name');
        $table->string('type');
        $table->decimal('hourly_rate', 8, 2)->nullable();
        $table->timestamps();
    });
}
```

Edit `app/Models/Caregiver.php`:

```php
<?php

namespace App\Models;

use App\Enums\CaregiverType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caregiver extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'name',
        'type',
        'hourly_rate',
    ];

    protected function casts(): array
    {
        return [
            'type' => CaregiverType::class,
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function budgetExpenses(): HasMany
    {
        return $this->hasMany(BudgetExpense::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }
}
```

Edit `database/factories/CaregiverFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\CaregiverType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaregiverFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'type' => fake()->randomElement(CaregiverType::cases()),
            'hourly_rate' => fake()->optional()->randomFloat(2, 15, 75),
        ];
    }
}
```

- [ ] **Step 4: Run migration and tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan migrate
php artisan test tests/Unit/Models/CaregiverTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Models/Caregiver.php database/migrations/*create_caregivers* database/factories/CaregiverFactory.php tests/Unit/Models/CaregiverTest.php
git commit -m "feat: add Caregiver model with client/user relationships"
```

---

## Task 5: Budget Models (Categories + Expenses)

**Files:**
- Create: `database/migrations/xxxx_create_budget_categories_table.php`
- Create: `database/migrations/xxxx_create_budget_expenses_table.php`
- Create: `app/Models/BudgetCategory.php`
- Create: `app/Models/BudgetExpense.php`
- Create: `database/factories/BudgetCategoryFactory.php`
- Create: `database/factories/BudgetExpenseFactory.php`
- Create: `tests/Unit/Models/BudgetTest.php`

- [ ] **Step 1: Write Budget model tests**

Create `tests/Unit/Models/BudgetTest.php`:

```php
<?php

use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\Client;
use App\Models\Caregiver;

it('budget category belongs to a client', function () {
    $client = Client::factory()->create();
    $category = BudgetCategory::factory()->for($client)->create();

    expect($category->client->id)->toBe($client->id);
});

it('budget category has expenses', function () {
    $category = BudgetCategory::factory()->create();
    BudgetExpense::factory()->for($category)->count(3)->create();

    expect($category->expenses)->toHaveCount(3);
});

it('budget category recalculates spent amount', function () {
    $category = BudgetCategory::factory()->create(['allocated_amount' => 1000, 'spent_amount' => 0]);
    BudgetExpense::factory()->for($category)->create(['amount' => 250]);
    BudgetExpense::factory()->for($category)->create(['amount' => 150]);

    $category->updateSpentAmount();

    expect($category->fresh()->spent_amount)->toBe('400.00');
});

it('budget expense belongs to a category', function () {
    $expense = BudgetExpense::factory()->create();

    expect($expense->budgetCategory)->toBeInstanceOf(BudgetCategory::class);
});

it('budget expense optionally belongs to a caregiver', function () {
    $caregiver = Caregiver::factory()->create();
    $expense = BudgetExpense::factory()->create(['caregiver_id' => $caregiver->id]);

    expect($expense->caregiver->id)->toBe($caregiver->id);
});

it('client has budget categories', function () {
    $client = Client::factory()->create();
    BudgetCategory::factory()->for($client)->count(3)->create();

    expect($client->budgetCategories)->toHaveCount(3);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Unit/Models/BudgetTest.php
```

Expected: FAIL

- [ ] **Step 3: Create BudgetCategory migration, model, factory**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:model BudgetCategory -mf
```

Edit the generated migration:

```php
public function up(): void
{
    Schema::create('budget_categories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->decimal('allocated_amount', 10, 2);
        $table->decimal('spent_amount', 10, 2)->default(0);
        $table->timestamps();
    });
}
```

Edit `app/Models/BudgetCategory.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'allocated_amount',
        'spent_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(BudgetExpense::class);
    }

    public function updateSpentAmount(): void
    {
        $this->update([
            'spent_amount' => $this->expenses()->sum('amount'),
        ]);
    }
}
```

Edit `database/factories/BudgetCategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->randomElement([
                'Persoonlijke verzorging',
                'Begeleiding individueel',
                'Dagbesteding',
                'Verpleging',
                'Kortdurend verblijf',
            ]),
            'allocated_amount' => fake()->randomFloat(2, 1000, 20000),
            'spent_amount' => 0,
        ];
    }
}
```

- [ ] **Step 4: Create BudgetExpense migration, model, factory**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:model BudgetExpense -mf
```

Edit the generated migration:

```php
public function up(): void
{
    Schema::create('budget_expenses', function (Blueprint $table) {
        $table->id();
        $table->foreignId('budget_category_id')->constrained()->cascadeOnDelete();
        $table->foreignId('caregiver_id')->nullable()->constrained()->nullOnDelete();
        $table->string('description');
        $table->decimal('amount', 10, 2);
        $table->date('date');
        $table->timestamps();
    });
}
```

Edit `app/Models/BudgetExpense.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_category_id',
        'caregiver_id',
        'description',
        'amount',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function budgetCategory(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
```

Edit `database/factories/BudgetExpenseFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\BudgetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'budget_category_id' => BudgetCategory::factory(),
            'caregiver_id' => null,
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 10, 500),
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }
}
```

- [ ] **Step 5: Run migration and tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan migrate
php artisan test tests/Unit/Models/BudgetTest.php
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Models/BudgetCategory.php app/Models/BudgetExpense.php database/migrations/*budget* database/factories/BudgetCategoryFactory.php database/factories/BudgetExpenseFactory.php tests/Unit/Models/BudgetTest.php
git commit -m "feat: add BudgetCategory and BudgetExpense models"
```

---

## Task 6: Schedule Models (Schedules + Exceptions)

**Files:**
- Create: `database/migrations/xxxx_create_schedules_table.php`
- Create: `database/migrations/xxxx_create_schedule_exceptions_table.php`
- Create: `app/Models/Schedule.php`
- Create: `app/Models/ScheduleException.php`
- Create: `database/factories/ScheduleFactory.php`
- Create: `database/factories/ScheduleExceptionFactory.php`
- Create: `tests/Unit/Models/ScheduleTest.php`

- [ ] **Step 1: Write Schedule model tests**

Create `tests/Unit/Models/ScheduleTest.php`:

```php
<?php

use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\Client;
use App\Models\Caregiver;
use App\Enums\ScheduleExceptionType;

it('schedule belongs to a client and caregiver', function () {
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->for($client)->create();
    $schedule = Schedule::factory()->create([
        'client_id' => $client->id,
        'caregiver_id' => $caregiver->id,
    ]);

    expect($schedule->client->id)->toBe($client->id);
    expect($schedule->caregiver->id)->toBe($caregiver->id);
});

it('schedule has exceptions', function () {
    $schedule = Schedule::factory()->create();
    ScheduleException::factory()->for($schedule)->create();

    expect($schedule->exceptions)->toHaveCount(1);
});

it('schedule exception can be a standalone appointment', function () {
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->for($client)->create();
    $exception = ScheduleException::factory()->create([
        'schedule_id' => null,
        'client_id' => $client->id,
        'caregiver_id' => $caregiver->id,
        'type' => ScheduleExceptionType::Added,
    ]);

    expect($exception->schedule)->toBeNull();
    expect($exception->type)->toBe(ScheduleExceptionType::Added);
});

it('client has schedules', function () {
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->for($client)->create();
    Schedule::factory()->count(3)->create([
        'client_id' => $client->id,
        'caregiver_id' => $caregiver->id,
    ]);

    expect($client->schedules)->toHaveCount(3);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Unit/Models/ScheduleTest.php
```

Expected: FAIL

- [ ] **Step 3: Create Schedule migration, model, factory**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:model Schedule -mf
```

Edit the generated migration:

```php
public function up(): void
{
    Schema::create('schedules', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
        $table->tinyInteger('day_of_week');
        $table->time('start_time');
        $table->time('end_time');
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}
```

Edit `app/Models/Schedule.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'caregiver_id',
        'day_of_week',
        'start_time',
        'end_time',
        'notes',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }
}
```

Edit `database/factories/ScheduleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        $client = Client::factory();

        return [
            'client_id' => $client,
            'caregiver_id' => Caregiver::factory()->for($client),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => fake()->time('H:00', '16:00'),
            'end_time' => fake()->time('H:00', '20:00'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
```

- [ ] **Step 4: Create ScheduleException migration, model, factory**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:model ScheduleException -mf
```

Edit the generated migration:

```php
public function up(): void
{
    Schema::create('schedule_exceptions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('schedule_id')->nullable()->constrained()->cascadeOnDelete();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();
        $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
        $table->date('date');
        $table->time('start_time');
        $table->time('end_time');
        $table->string('type');
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}
```

Edit `app/Models/ScheduleException.php`:

```php
<?php

namespace App\Models;

use App\Enums\ScheduleExceptionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleException extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'client_id',
        'caregiver_id',
        'date',
        'start_time',
        'end_time',
        'type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => ScheduleExceptionType::class,
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
```

Edit `database/factories/ScheduleExceptionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ScheduleExceptionType;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleExceptionFactory extends Factory
{
    public function definition(): array
    {
        $schedule = Schedule::factory();

        return [
            'schedule_id' => $schedule,
            'client_id' => $schedule->client_id ?? Client::factory(),
            'caregiver_id' => $schedule->caregiver_id ?? Caregiver::factory(),
            'date' => fake()->dateTimeBetween('now', '+1 month'),
            'start_time' => fake()->time('H:00', '16:00'),
            'end_time' => fake()->time('H:00', '20:00'),
            'type' => fake()->randomElement(ScheduleExceptionType::cases()),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
```

- [ ] **Step 5: Run migration and tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan migrate
php artisan test tests/Unit/Models/ScheduleTest.php
```

Expected: PASS

- [ ] **Step 6: Run full test suite**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Models/Schedule.php app/Models/ScheduleException.php database/migrations/*schedule* database/factories/ScheduleFactory.php database/factories/ScheduleExceptionFactory.php tests/Unit/Models/ScheduleTest.php
git commit -m "feat: add Schedule and ScheduleException models"
```

---

## Task 7: Authorization (Policies & Middleware)

**Files:**
- Create: `app/Http/Middleware/EnsureRole.php`
- Create: `app/Policies/ClientPolicy.php`
- Create: `app/Policies/CaregiverPolicy.php`
- Create: `app/Policies/BudgetCategoryPolicy.php`
- Create: `app/Policies/BudgetExpensePolicy.php`
- Create: `app/Policies/SchedulePolicy.php`
- Create: `tests/Feature/Auth/AuthorizationTest.php`

- [ ] **Step 1: Write authorization tests**

Create `tests/Feature/Auth/AuthorizationTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Client;
use App\Models\Caregiver;
use App\Models\BudgetCategory;

it('budget holder can view their own client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();

    expect($user->can('view', $client))->toBeTrue();
});

it('budget holder cannot view another holders client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $otherUser = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($otherUser, 'budgetHolder')->create();

    expect($user->can('view', $client))->toBeFalse();
});

it('caregiver cannot view clients', function () {
    $user = User::factory()->create(['role' => UserRole::Caregiver]);
    $client = Client::factory()->create();

    expect($user->can('view', $client))->toBeFalse();
});

it('budget holder can manage caregivers of their client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $caregiver = Caregiver::factory()->for($client)->create();

    expect($user->can('view', $caregiver))->toBeTrue();
    expect($user->can('update', $caregiver))->toBeTrue();
    expect($user->can('delete', $caregiver))->toBeTrue();
});

it('budget holder can manage budget categories of their client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $category = BudgetCategory::factory()->for($client)->create();

    expect($user->can('view', $category))->toBeTrue();
    expect($user->can('update', $category))->toBeTrue();
});

it('role middleware blocks caregiver from budget holder routes', function () {
    $user = User::factory()->create(['role' => UserRole::Caregiver]);

    $response = $this->actingAs($user)->get('/clients');

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/Auth/AuthorizationTest.php
```

Expected: FAIL

- [ ] **Step 3: Create EnsureRole middleware**

Create `app/Http/Middleware/EnsureRole.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->role;

        if (!$userRole) {
            abort(403);
        }

        $allowedRoles = array_map(fn (string $role) => UserRole::from($role), $roles);

        if (!in_array($userRole, $allowedRoles)) {
            abort(403);
        }

        return $next($request);
    }
}
```

Register the middleware alias in `bootstrap/app.php` — add inside the `withMiddleware` closure:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
    ]);

    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureRole::class,
    ]);
})
```

- [ ] **Step 4: Create ClientPolicy**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:policy ClientPolicy --model=Client
```

Edit `app/Policies/ClientPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $client->budget_holder_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    public function update(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }
}
```

- [ ] **Step 5: Create CaregiverPolicy**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:policy CaregiverPolicy --model=Caregiver
```

Edit `app/Policies/CaregiverPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\User;

class CaregiverPolicy
{
    public function view(User $user, Caregiver $caregiver): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $caregiver->client->budget_holder_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    public function update(User $user, Caregiver $caregiver): bool
    {
        return $this->view($user, $caregiver);
    }

    public function delete(User $user, Caregiver $caregiver): bool
    {
        return $this->view($user, $caregiver);
    }
}
```

- [ ] **Step 6: Create BudgetCategoryPolicy**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:policy BudgetCategoryPolicy --model=BudgetCategory
```

Edit `app/Policies/BudgetCategoryPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BudgetCategory;
use App\Models\User;

class BudgetCategoryPolicy
{
    public function view(User $user, BudgetCategory $budgetCategory): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $budgetCategory->client->budget_holder_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    public function update(User $user, BudgetCategory $budgetCategory): bool
    {
        return $this->view($user, $budgetCategory);
    }

    public function delete(User $user, BudgetCategory $budgetCategory): bool
    {
        return $this->view($user, $budgetCategory);
    }
}
```

- [ ] **Step 7: Create BudgetExpensePolicy and SchedulePolicy**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:policy BudgetExpensePolicy --model=BudgetExpense
php artisan make:policy SchedulePolicy --model=Schedule
```

Edit `app/Policies/BudgetExpensePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BudgetExpense;
use App\Models\User;

class BudgetExpensePolicy
{
    public function view(User $user, BudgetExpense $expense): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $expense->budgetCategory->client->budget_holder_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    public function update(User $user, BudgetExpense $expense): bool
    {
        return $this->view($user, $expense);
    }

    public function delete(User $user, BudgetExpense $expense): bool
    {
        return $this->view($user, $expense);
    }
}
```

Edit `app/Policies/SchedulePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function view(User $user, Schedule $schedule): bool
    {
        return $user->role === UserRole::BudgetHolder
            && $schedule->client->budget_holder_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::BudgetHolder;
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }
}
```

- [ ] **Step 8: Add a test route for the middleware test**

Add to `routes/web.php` (we will add real routes later, but the test needs `/clients` to exist):

```php
use App\Http\Middleware\EnsureRole;

Route::middleware(['auth', 'verified', 'role:budget_holder'])->group(function () {
    Route::get('/clients', fn () => inertia('Clients/Index'))->name('clients.index');
});
```

- [ ] **Step 9: Run tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/Auth/AuthorizationTest.php
```

Expected: PASS

- [ ] **Step 10: Run full test suite**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test
```

Expected: all tests pass.

- [ ] **Step 11: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Http/Middleware/EnsureRole.php app/Policies/ bootstrap/app.php routes/web.php tests/Feature/Auth/AuthorizationTest.php
git commit -m "feat: add role middleware and authorization policies"
```

---

## Task 8: TypeScript Types & Layout Navigation

**Files:**
- Create: `resources/js/types/models.d.ts`
- Modify: `resources/js/Layouts/AuthenticatedLayout.tsx`

- [ ] **Step 1: Create TypeScript type definitions**

Create `resources/js/types/models.d.ts`:

```typescript
export interface User {
    id: number;
    name: string;
    email: string;
    role: 'budget_holder' | 'caregiver';
}

export interface Client {
    id: number;
    budget_holder_id: number;
    name: string;
    date_of_birth: string;
    notes: string | null;
    caregivers?: Caregiver[];
    budget_categories?: BudgetCategory[];
}

export interface Caregiver {
    id: number;
    client_id: number;
    user_id: number | null;
    name: string;
    type: 'parent' | 'care_worker' | 'day_care' | 'zzp' | 'other';
    hourly_rate: string | null;
}

export interface BudgetCategory {
    id: number;
    client_id: number;
    name: string;
    allocated_amount: string;
    spent_amount: string;
    expenses?: BudgetExpense[];
}

export interface BudgetExpense {
    id: number;
    budget_category_id: number;
    caregiver_id: number | null;
    description: string;
    amount: string;
    date: string;
    caregiver?: Caregiver;
}

export interface Schedule {
    id: number;
    client_id: number;
    caregiver_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    notes: string | null;
    caregiver?: Caregiver;
    exceptions?: ScheduleException[];
}

export interface ScheduleException {
    id: number;
    schedule_id: number | null;
    client_id: number;
    caregiver_id: number;
    date: string;
    start_time: string;
    end_time: string;
    type: 'cancelled' | 'modified' | 'added';
    notes: string | null;
    caregiver?: Caregiver;
}
```

- [ ] **Step 2: Update AuthenticatedLayout with PGB navigation**

Read the existing `resources/js/Layouts/AuthenticatedLayout.tsx` to understand the current structure. Then update the navigation links.

Replace the existing navigation links section (the `<NavLink>` items in the desktop nav) with:

```tsx
<NavLink href={route('dashboard')} active={route().current('dashboard')}>
    Dashboard
</NavLink>
<NavLink href={route('clients.index')} active={route().current('clients.*')}>
    Cliënten
</NavLink>
```

These are the top-level nav items. Client-specific navigation (budget, planning, caregivers) appears on the client detail page as sub-navigation — not in the top bar.

- [ ] **Step 3: Verify TypeScript compiles**

```bash
cd /Users/jesse/dev/flowan/pgb
npx tsc --noEmit
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add resources/js/types/models.d.ts resources/js/Layouts/AuthenticatedLayout.tsx
git commit -m "feat: add TypeScript model types and update navigation"
```

---

## Task 9: Client CRUD Pages

**Files:**
- Create: `app/Http/Controllers/ClientController.php`
- Create: `app/Http/Requests/ClientRequest.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Clients/Index.tsx`
- Create: `resources/js/Pages/Clients/Show.tsx`
- Create: `resources/js/Pages/Clients/Create.tsx`
- Create: `resources/js/Pages/Clients/Edit.tsx`
- Create: `tests/Feature/ClientControllerTest.php`

- [ ] **Step 1: Write feature tests for ClientController**

Create `tests/Feature/ClientControllerTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;

it('shows clients index for budget holder', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    Client::factory()->for($user, 'budgetHolder')->count(2)->create();

    $response = $this->actingAs($user)->get('/clients');

    $response->assertOk();
    $response->assertInertia(fn ($page) =>
        $page->component('Clients/Index')
            ->has('clients', 2)
    );
});

it('only shows own clients', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $otherUser = User::factory()->create(['role' => UserRole::BudgetHolder]);
    Client::factory()->for($user, 'budgetHolder')->create();
    Client::factory()->for($otherUser, 'budgetHolder')->create();

    $response = $this->actingAs($user)->get('/clients');

    $response->assertInertia(fn ($page) =>
        $page->has('clients', 1)
    );
});

it('can create a client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);

    $response = $this->actingAs($user)->post('/clients', [
        'name' => 'Test Client',
        'date_of_birth' => '2015-06-15',
        'notes' => 'Test notes',
    ]);

    $response->assertRedirect('/clients');
    $this->assertDatabaseHas('clients', [
        'name' => 'Test Client',
        'budget_holder_id' => $user->id,
    ]);
});

it('can update a client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();

    $response = $this->actingAs($user)->put("/clients/{$client->id}", [
        'name' => 'Updated Name',
        'date_of_birth' => $client->date_of_birth->format('Y-m-d'),
    ]);

    $response->assertRedirect("/clients/{$client->id}");
    expect($client->fresh()->name)->toBe('Updated Name');
});

it('can delete a client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();

    $response = $this->actingAs($user)->delete("/clients/{$client->id}");

    $response->assertRedirect('/clients');
    $this->assertDatabaseMissing('clients', ['id' => $client->id]);
});

it('shows client detail with caregivers and budget', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();

    $response = $this->actingAs($user)->get("/clients/{$client->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) =>
        $page->component('Clients/Show')
            ->has('client')
    );
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/ClientControllerTest.php
```

Expected: FAIL

- [ ] **Step 3: Create ClientRequest**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:request ClientRequest
```

Edit `app/Http/Requests/ClientRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create ClientController**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:controller ClientController
```

Edit `app/Http/Controllers/ClientController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $clients = Client::where('budget_holder_id', auth()->id())
            ->withCount('caregivers')
            ->with('budgetCategories')
            ->get();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
        ]);
    }

    public function show(Client $client): Response
    {
        $this->authorize('view', $client);

        $client->load(['caregivers', 'budgetCategories']);

        return Inertia::render('Clients/Show', [
            'client' => $client,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Clients/Create');
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::create([
            ...$request->validated(),
            'budget_holder_id' => auth()->id(),
        ]);

        return redirect()->route('clients.index');
    }

    public function edit(Client $client): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('Clients/Edit', [
            'client' => $client,
        ]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update($request->validated());

        return redirect()->route('clients.show', $client);
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()->route('clients.index');
    }
}
```

- [ ] **Step 5: Update routes**

Replace the temporary `/clients` route in `routes/web.php` with full resource routes:

```php
use App\Http\Controllers\ClientController;

Route::middleware(['auth', 'verified', 'role:budget_holder'])->group(function () {
    Route::resource('clients', ClientController::class);
});
```

- [ ] **Step 6: Run tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/ClientControllerTest.php
```

Expected: PASS

- [ ] **Step 7: Create Clients/Index.tsx**

Create `resources/js/Pages/Clients/Index.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Client } from '@/types/models';

interface Props {
    clients: (Client & { caregivers_count: number })[];
}

export default function Index({ clients }: Props) {
    return (
        <AuthenticatedLayout>
            <Head title="Cliënten" />

            <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 py-12">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-semibold text-gray-900">Cliënten</h1>
                    <Link
                        href={route('clients.create')}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Nieuwe cliënt
                    </Link>
                </div>

                <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    {clients.length === 0 ? (
                        <div className="p-6 text-gray-500">
                            Nog geen cliënten. Voeg je eerste cliënt toe.
                        </div>
                    ) : (
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Naam</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Geboortedatum</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Zorgverleners</th>
                                    <th className="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {clients.map((client) => (
                                    <tr key={client.id}>
                                        <td className="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                            <Link href={route('clients.show', client.id)} className="text-indigo-600 hover:text-indigo-900">
                                                {client.name}
                                            </Link>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-gray-500">
                                            {new Date(client.date_of_birth).toLocaleDateString('nl-NL')}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-gray-500">
                                            {client.caregivers_count}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right">
                                            <Link href={route('clients.edit', client.id)} className="text-indigo-600 hover:text-indigo-900">
                                                Bewerken
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 8: Create Clients/Show.tsx**

Create `resources/js/Pages/Clients/Show.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Client } from '@/types/models';

interface Props {
    client: Client;
}

export default function Show({ client }: Props) {
    const deleteClient = () => {
        if (confirm('Weet je zeker dat je deze cliënt wilt verwijderen?')) {
            router.delete(route('clients.destroy', client.id));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={client.name} />

            <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 py-12">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-semibold text-gray-900">{client.name}</h1>
                    <div className="flex gap-3">
                        <Link
                            href={route('clients.edit', client.id)}
                            className="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                        >
                            Bewerken
                        </Link>
                        <button
                            onClick={deleteClient}
                            className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500"
                        >
                            Verwijderen
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-sm font-medium text-gray-500">Geboortedatum</h3>
                        <p className="mt-1 text-lg text-gray-900">
                            {new Date(client.date_of_birth).toLocaleDateString('nl-NL')}
                        </p>
                    </div>
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-sm font-medium text-gray-500">Zorgverleners</h3>
                        <p className="mt-1 text-lg text-gray-900">
                            {client.caregivers?.length ?? 0}
                        </p>
                    </div>
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-sm font-medium text-gray-500">Notities</h3>
                        <p className="mt-1 text-gray-900">
                            {client.notes || 'Geen notities'}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Link
                        href={route('clients.caregivers.index', client.id)}
                        className="flex items-center justify-center rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50"
                    >
                        <span className="text-lg font-medium text-gray-900">Zorgverleners</span>
                    </Link>
                    <Link
                        href={route('clients.budget.index', client.id)}
                        className="flex items-center justify-center rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50"
                    >
                        <span className="text-lg font-medium text-gray-900">Budget</span>
                    </Link>
                    <Link
                        href={route('clients.schedule.index', client.id)}
                        className="flex items-center justify-center rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50"
                    >
                        <span className="text-lg font-medium text-gray-900">Planning</span>
                    </Link>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 9: Create Clients/Create.tsx and Clients/Edit.tsx**

Create `resources/js/Pages/Clients/Create.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        date_of_birth: '',
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('clients.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Nieuwe cliënt" />

            <div className="mx-auto max-w-2xl sm:px-6 lg:px-8 py-12">
                <h1 className="text-2xl font-semibold text-gray-900 mb-6">Nieuwe cliënt</h1>

                <form onSubmit={submit} className="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700">Naam</label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="date_of_birth" className="block text-sm font-medium text-gray-700">Geboortedatum</label>
                        <input
                            id="date_of_birth"
                            type="date"
                            value={data.date_of_birth}
                            onChange={(e) => setData('date_of_birth', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.date_of_birth && <p className="mt-1 text-sm text-red-600">{errors.date_of_birth}</p>}
                    </div>

                    <div>
                        <label htmlFor="notes" className="block text-sm font-medium text-gray-700">Notities</label>
                        <textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.notes && <p className="mt-1 text-sm text-red-600">{errors.notes}</p>}
                    </div>

                    <div className="flex items-center gap-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            Opslaan
                        </button>
                        <Link href={route('clients.index')} className="text-sm text-gray-600 hover:text-gray-900">
                            Annuleren
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
```

Create `resources/js/Pages/Clients/Edit.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { Client } from '@/types/models';
import { FormEvent } from 'react';

interface Props {
    client: Client;
}

export default function Edit({ client }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: client.name,
        date_of_birth: client.date_of_birth,
        notes: client.notes || '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('clients.update', client.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`${client.name} bewerken`} />

            <div className="mx-auto max-w-2xl sm:px-6 lg:px-8 py-12">
                <h1 className="text-2xl font-semibold text-gray-900 mb-6">{client.name} bewerken</h1>

                <form onSubmit={submit} className="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700">Naam</label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="date_of_birth" className="block text-sm font-medium text-gray-700">Geboortedatum</label>
                        <input
                            id="date_of_birth"
                            type="date"
                            value={data.date_of_birth}
                            onChange={(e) => setData('date_of_birth', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.date_of_birth && <p className="mt-1 text-sm text-red-600">{errors.date_of_birth}</p>}
                    </div>

                    <div>
                        <label htmlFor="notes" className="block text-sm font-medium text-gray-700">Notities</label>
                        <textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.notes && <p className="mt-1 text-sm text-red-600">{errors.notes}</p>}
                    </div>

                    <div className="flex items-center gap-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            Opslaan
                        </button>
                        <Link href={route('clients.show', client.id)} className="text-sm text-gray-600 hover:text-gray-900">
                            Annuleren
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 10: Verify TypeScript compiles**

```bash
cd /Users/jesse/dev/flowan/pgb
npx tsc --noEmit
```

Expected: no errors (or only pre-existing Breeze warnings).

- [ ] **Step 11: Run full test suite**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test
```

Expected: all tests pass.

- [ ] **Step 12: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Http/Controllers/ClientController.php app/Http/Requests/ClientRequest.php routes/web.php resources/js/Pages/Clients/ tests/Feature/ClientControllerTest.php
git commit -m "feat: add Client CRUD pages"
```

---

## Task 10: Caregiver Management Pages

**Files:**
- Create: `app/Http/Controllers/CaregiverController.php`
- Create: `app/Http/Requests/CaregiverRequest.php`
- Modify: `routes/web.php`
- Create: `resources/js/Pages/Caregivers/Index.tsx`
- Create: `resources/js/Pages/Caregivers/Create.tsx`
- Create: `resources/js/Pages/Caregivers/Edit.tsx`
- Create: `tests/Feature/CaregiverControllerTest.php`

- [ ] **Step 1: Write feature tests**

Create `tests/Feature/CaregiverControllerTest.php`:

```php
<?php

use App\Enums\CaregiverType;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;

it('shows caregivers for a client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    Caregiver::factory()->for($client)->count(3)->create();

    $response = $this->actingAs($user)->get("/clients/{$client->id}/caregivers");

    $response->assertOk();
    $response->assertInertia(fn ($page) =>
        $page->component('Caregivers/Index')
            ->has('caregivers', 3)
            ->has('client')
    );
});

it('can create a caregiver', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();

    $response = $this->actingAs($user)->post("/clients/{$client->id}/caregivers", [
        'name' => 'Jan de Vries',
        'type' => 'zzp',
        'hourly_rate' => '35.00',
    ]);

    $response->assertRedirect("/clients/{$client->id}/caregivers");
    $this->assertDatabaseHas('caregivers', [
        'name' => 'Jan de Vries',
        'client_id' => $client->id,
        'type' => 'zzp',
    ]);
});

it('can update a caregiver', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $caregiver = Caregiver::factory()->for($client)->create();

    $response = $this->actingAs($user)->put("/clients/{$client->id}/caregivers/{$caregiver->id}", [
        'name' => 'Updated Name',
        'type' => 'care_worker',
        'hourly_rate' => '40.00',
    ]);

    $response->assertRedirect("/clients/{$client->id}/caregivers");
    expect($caregiver->fresh()->name)->toBe('Updated Name');
});

it('can delete a caregiver', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $caregiver = Caregiver::factory()->for($client)->create();

    $response = $this->actingAs($user)->delete("/clients/{$client->id}/caregivers/{$caregiver->id}");

    $response->assertRedirect("/clients/{$client->id}/caregivers");
    $this->assertDatabaseMissing('caregivers', ['id' => $caregiver->id]);
});

it('prevents access to other holders client caregivers', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $otherClient = Client::factory()->create();

    $response = $this->actingAs($user)->get("/clients/{$otherClient->id}/caregivers");

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/CaregiverControllerTest.php
```

Expected: FAIL

- [ ] **Step 3: Create CaregiverRequest**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:request CaregiverRequest
```

Edit `app/Http/Requests/CaregiverRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\CaregiverType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaregiverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CaregiverType::class)],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 4: Create CaregiverController**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:controller CaregiverController
```

Edit `app/Http/Controllers/CaregiverController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaregiverRequest;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CaregiverController extends Controller
{
    public function index(Client $client): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('Caregivers/Index', [
            'client' => $client,
            'caregivers' => $client->caregivers,
        ]);
    }

    public function create(Client $client): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('Caregivers/Create', [
            'client' => $client,
        ]);
    }

    public function store(CaregiverRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $client->caregivers()->create($request->validated());

        return redirect()->route('clients.caregivers.index', $client);
    }

    public function edit(Client $client, Caregiver $caregiver): Response
    {
        $this->authorize('view', $caregiver);

        return Inertia::render('Caregivers/Edit', [
            'client' => $client,
            'caregiver' => $caregiver,
        ]);
    }

    public function update(CaregiverRequest $request, Client $client, Caregiver $caregiver): RedirectResponse
    {
        $this->authorize('update', $caregiver);

        $caregiver->update($request->validated());

        return redirect()->route('clients.caregivers.index', $client);
    }

    public function destroy(Client $client, Caregiver $caregiver): RedirectResponse
    {
        $this->authorize('delete', $caregiver);

        $caregiver->delete();

        return redirect()->route('clients.caregivers.index', $client);
    }
}
```

- [ ] **Step 5: Add routes**

Add to `routes/web.php` inside the existing `role:budget_holder` middleware group:

```php
use App\Http\Controllers\CaregiverController;

Route::resource('clients.caregivers', CaregiverController::class)->except(['show']);
```

- [ ] **Step 6: Run tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/CaregiverControllerTest.php
```

Expected: PASS

- [ ] **Step 7: Create Caregivers/Index.tsx**

Create `resources/js/Pages/Caregivers/Index.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Client, Caregiver } from '@/types/models';

interface Props {
    client: Client;
    caregivers: Caregiver[];
}

const typeLabels: Record<string, string> = {
    parent: 'Ouder',
    care_worker: 'Zorgmedewerker',
    day_care: 'Dagbesteding',
    zzp: 'ZZP\'er',
    other: 'Anders',
};

export default function Index({ client, caregivers }: Props) {
    const deleteCaregiver = (caregiver: Caregiver) => {
        if (confirm(`Weet je zeker dat je ${caregiver.name} wilt verwijderen?`)) {
            router.delete(route('clients.caregivers.destroy', [client.id, caregiver.id]));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Zorgverleners - ${client.name}`} />

            <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 py-12">
                <div className="mb-4">
                    <Link href={route('clients.show', client.id)} className="text-sm text-indigo-600 hover:text-indigo-900">
                        &larr; {client.name}
                    </Link>
                </div>

                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-semibold text-gray-900">Zorgverleners</h1>
                    <Link
                        href={route('clients.caregivers.create', client.id)}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Toevoegen
                    </Link>
                </div>

                <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    {caregivers.length === 0 ? (
                        <div className="p-6 text-gray-500">Nog geen zorgverleners toegevoegd.</div>
                    ) : (
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Naam</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Uurtarief</th>
                                    <th className="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {caregivers.map((caregiver) => (
                                    <tr key={caregiver.id}>
                                        <td className="whitespace-nowrap px-6 py-4 font-medium text-gray-900">{caregiver.name}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-gray-500">{typeLabels[caregiver.type]}</td>
                                        <td className="whitespace-nowrap px-6 py-4 text-gray-500">
                                            {caregiver.hourly_rate ? `€${caregiver.hourly_rate}` : '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right space-x-3">
                                            <Link href={route('clients.caregivers.edit', [client.id, caregiver.id])} className="text-indigo-600 hover:text-indigo-900">
                                                Bewerken
                                            </Link>
                                            <button onClick={() => deleteCaregiver(caregiver)} className="text-red-600 hover:text-red-900">
                                                Verwijderen
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 8: Create Caregivers/Create.tsx and Caregivers/Edit.tsx**

Create `resources/js/Pages/Caregivers/Create.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { Client } from '@/types/models';
import { FormEvent } from 'react';

interface Props {
    client: Client;
}

export default function Create({ client }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'care_worker',
        hourly_rate: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('clients.caregivers.store', client.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Zorgverlener toevoegen - ${client.name}`} />

            <div className="mx-auto max-w-2xl sm:px-6 lg:px-8 py-12">
                <div className="mb-4">
                    <Link href={route('clients.caregivers.index', client.id)} className="text-sm text-indigo-600 hover:text-indigo-900">
                        &larr; Zorgverleners
                    </Link>
                </div>

                <h1 className="text-2xl font-semibold text-gray-900 mb-6">Zorgverlener toevoegen</h1>

                <form onSubmit={submit} className="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700">Naam</label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="type" className="block text-sm font-medium text-gray-700">Type</label>
                        <select
                            id="type"
                            value={data.type}
                            onChange={(e) => setData('type', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="parent">Ouder</option>
                            <option value="care_worker">Zorgmedewerker</option>
                            <option value="day_care">Dagbesteding</option>
                            <option value="zzp">ZZP'er</option>
                            <option value="other">Anders</option>
                        </select>
                        {errors.type && <p className="mt-1 text-sm text-red-600">{errors.type}</p>}
                    </div>

                    <div>
                        <label htmlFor="hourly_rate" className="block text-sm font-medium text-gray-700">Uurtarief (€)</label>
                        <input
                            id="hourly_rate"
                            type="number"
                            step="0.01"
                            value={data.hourly_rate}
                            onChange={(e) => setData('hourly_rate', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.hourly_rate && <p className="mt-1 text-sm text-red-600">{errors.hourly_rate}</p>}
                    </div>

                    <div className="flex items-center gap-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            Opslaan
                        </button>
                        <Link href={route('clients.caregivers.index', client.id)} className="text-sm text-gray-600 hover:text-gray-900">
                            Annuleren
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
```

Create `resources/js/Pages/Caregivers/Edit.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { Client, Caregiver } from '@/types/models';
import { FormEvent } from 'react';

interface Props {
    client: Client;
    caregiver: Caregiver;
}

export default function Edit({ client, caregiver }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: caregiver.name,
        type: caregiver.type,
        hourly_rate: caregiver.hourly_rate || '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('clients.caregivers.update', [client.id, caregiver.id]));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`${caregiver.name} bewerken`} />

            <div className="mx-auto max-w-2xl sm:px-6 lg:px-8 py-12">
                <div className="mb-4">
                    <Link href={route('clients.caregivers.index', client.id)} className="text-sm text-indigo-600 hover:text-indigo-900">
                        &larr; Zorgverleners
                    </Link>
                </div>

                <h1 className="text-2xl font-semibold text-gray-900 mb-6">{caregiver.name} bewerken</h1>

                <form onSubmit={submit} className="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700">Naam</label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="type" className="block text-sm font-medium text-gray-700">Type</label>
                        <select
                            id="type"
                            value={data.type}
                            onChange={(e) => setData('type', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="parent">Ouder</option>
                            <option value="care_worker">Zorgmedewerker</option>
                            <option value="day_care">Dagbesteding</option>
                            <option value="zzp">ZZP'er</option>
                            <option value="other">Anders</option>
                        </select>
                        {errors.type && <p className="mt-1 text-sm text-red-600">{errors.type}</p>}
                    </div>

                    <div>
                        <label htmlFor="hourly_rate" className="block text-sm font-medium text-gray-700">Uurtarief (€)</label>
                        <input
                            id="hourly_rate"
                            type="number"
                            step="0.01"
                            value={data.hourly_rate}
                            onChange={(e) => setData('hourly_rate', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.hourly_rate && <p className="mt-1 text-sm text-red-600">{errors.hourly_rate}</p>}
                    </div>

                    <div className="flex items-center gap-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            Opslaan
                        </button>
                        <Link href={route('clients.caregivers.index', client.id)} className="text-sm text-gray-600 hover:text-gray-900">
                            Annuleren
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 9: Run full test suite**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test
```

Expected: all tests pass.

- [ ] **Step 10: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Http/Controllers/CaregiverController.php app/Http/Requests/CaregiverRequest.php routes/web.php resources/js/Pages/Caregivers/ tests/Feature/CaregiverControllerTest.php
git commit -m "feat: add Caregiver management pages"
```

---

## Task 11: Budget Management Pages

**Files:**
- Create: `app/Http/Controllers/BudgetCategoryController.php`
- Create: `app/Http/Controllers/BudgetExpenseController.php`
- Create: `app/Http/Requests/BudgetCategoryRequest.php`
- Create: `app/Http/Requests/BudgetExpenseRequest.php`
- Create: `resources/js/Components/Budget/ProgressBar.tsx`
- Create: `resources/js/Pages/Budget/Index.tsx`
- Modify: `routes/web.php`
- Create: `tests/Feature/BudgetControllerTest.php`

- [ ] **Step 1: Write feature tests**

Create `tests/Feature/BudgetControllerTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;

it('shows budget overview for a client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    BudgetCategory::factory()->for($client)->count(3)->create();

    $response = $this->actingAs($user)->get("/clients/{$client->id}/budget");

    $response->assertOk();
    $response->assertInertia(fn ($page) =>
        $page->component('Budget/Index')
            ->has('categories', 3)
            ->has('client')
    );
});

it('can create a budget category', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();

    $response = $this->actingAs($user)->post("/clients/{$client->id}/budget-categories", [
        'name' => 'Begeleiding',
        'allocated_amount' => '5000.00',
    ]);

    $response->assertRedirect("/clients/{$client->id}/budget");
    $this->assertDatabaseHas('budget_categories', [
        'name' => 'Begeleiding',
        'client_id' => $client->id,
    ]);
});

it('can add an expense', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $category = BudgetCategory::factory()->for($client)->create(['allocated_amount' => 5000, 'spent_amount' => 0]);
    $caregiver = Caregiver::factory()->for($client)->create();

    $response = $this->actingAs($user)->post("/clients/{$client->id}/budget-expenses", [
        'budget_category_id' => $category->id,
        'caregiver_id' => $caregiver->id,
        'description' => 'Begeleiding maandag',
        'amount' => '150.00',
        'date' => '2026-05-01',
    ]);

    $response->assertRedirect("/clients/{$client->id}/budget");
    $this->assertDatabaseHas('budget_expenses', [
        'description' => 'Begeleiding maandag',
        'amount' => '150.00',
    ]);
    expect($category->fresh()->spent_amount)->toBe('150.00');
});

it('can delete an expense and recalculates spent amount', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $category = BudgetCategory::factory()->for($client)->create(['allocated_amount' => 5000, 'spent_amount' => 150]);
    $expense = BudgetExpense::factory()->for($category)->create(['amount' => 150]);

    $response = $this->actingAs($user)->delete("/clients/{$client->id}/budget-expenses/{$expense->id}");

    $response->assertRedirect("/clients/{$client->id}/budget");
    $this->assertDatabaseMissing('budget_expenses', ['id' => $expense->id]);
    expect($category->fresh()->spent_amount)->toBe('0.00');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/BudgetControllerTest.php
```

Expected: FAIL

- [ ] **Step 3: Create BudgetCategoryRequest and BudgetExpenseRequest**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:request BudgetCategoryRequest
php artisan make:request BudgetExpenseRequest
```

Edit `app/Http/Requests/BudgetCategoryRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BudgetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'allocated_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

Edit `app/Http/Requests/BudgetExpenseRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BudgetExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'budget_category_id' => ['required', 'exists:budget_categories,id'],
            'caregiver_id' => ['nullable', 'exists:caregivers,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Create BudgetCategoryController**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:controller BudgetCategoryController
```

Edit `app/Http/Controllers/BudgetCategoryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetCategoryRequest;
use App\Models\BudgetCategory;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BudgetCategoryController extends Controller
{
    public function index(Client $client): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('Budget/Index', [
            'client' => $client,
            'categories' => $client->budgetCategories()->with('expenses.caregiver')->get(),
            'caregivers' => $client->caregivers,
        ]);
    }

    public function store(BudgetCategoryRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $client->budgetCategories()->create($request->validated());

        return redirect()->route('clients.budget.index', $client);
    }

    public function update(BudgetCategoryRequest $request, Client $client, BudgetCategory $budgetCategory): RedirectResponse
    {
        $this->authorize('update', $budgetCategory);

        $budgetCategory->update($request->validated());

        return redirect()->route('clients.budget.index', $client);
    }

    public function destroy(Client $client, BudgetCategory $budgetCategory): RedirectResponse
    {
        $this->authorize('delete', $budgetCategory);

        $budgetCategory->delete();

        return redirect()->route('clients.budget.index', $client);
    }
}
```

- [ ] **Step 5: Create BudgetExpenseController**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:controller BudgetExpenseController
```

Edit `app/Http/Controllers/BudgetExpenseController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetExpenseRequest;
use App\Models\BudgetExpense;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;

class BudgetExpenseController extends Controller
{
    public function store(BudgetExpenseRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $expense = BudgetExpense::create($request->validated());
        $expense->budgetCategory->updateSpentAmount();

        return redirect()->route('clients.budget.index', $client);
    }

    public function update(BudgetExpenseRequest $request, Client $client, BudgetExpense $budgetExpense): RedirectResponse
    {
        $this->authorize('update', $budgetExpense);

        $budgetExpense->update($request->validated());
        $budgetExpense->budgetCategory->updateSpentAmount();

        return redirect()->route('clients.budget.index', $client);
    }

    public function destroy(Client $client, BudgetExpense $budgetExpense): RedirectResponse
    {
        $this->authorize('delete', $budgetExpense);

        $category = $budgetExpense->budgetCategory;
        $budgetExpense->delete();
        $category->updateSpentAmount();

        return redirect()->route('clients.budget.index', $client);
    }
}
```

- [ ] **Step 6: Add routes**

Add to `routes/web.php` inside the `role:budget_holder` middleware group:

```php
use App\Http\Controllers\BudgetCategoryController;
use App\Http\Controllers\BudgetExpenseController;

Route::get('clients/{client}/budget', [BudgetCategoryController::class, 'index'])->name('clients.budget.index');
Route::post('clients/{client}/budget-categories', [BudgetCategoryController::class, 'store'])->name('clients.budget-categories.store');
Route::put('clients/{client}/budget-categories/{budgetCategory}', [BudgetCategoryController::class, 'update'])->name('clients.budget-categories.update');
Route::delete('clients/{client}/budget-categories/{budgetCategory}', [BudgetCategoryController::class, 'destroy'])->name('clients.budget-categories.destroy');

Route::post('clients/{client}/budget-expenses', [BudgetExpenseController::class, 'store'])->name('clients.budget-expenses.store');
Route::put('clients/{client}/budget-expenses/{budgetExpense}', [BudgetExpenseController::class, 'update'])->name('clients.budget-expenses.update');
Route::delete('clients/{client}/budget-expenses/{budgetExpense}', [BudgetExpenseController::class, 'destroy'])->name('clients.budget-expenses.destroy');
```

- [ ] **Step 7: Run tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/BudgetControllerTest.php
```

Expected: PASS

- [ ] **Step 8: Create ProgressBar component**

Create `resources/js/Components/Budget/ProgressBar.tsx`:

```tsx
interface Props {
    allocated: number;
    spent: number;
}

export default function ProgressBar({ allocated, spent }: Props) {
    const percentage = allocated > 0 ? Math.min((spent / allocated) * 100, 100) : 0;
    const remaining = allocated - spent;
    const isOverBudget = spent > allocated;

    return (
        <div>
            <div className="flex justify-between text-sm mb-1">
                <span className="text-gray-600">
                    €{spent.toFixed(2)} / €{allocated.toFixed(2)}
                </span>
                <span className={isOverBudget ? 'text-red-600 font-medium' : 'text-gray-500'}>
                    {isOverBudget ? `-€${Math.abs(remaining).toFixed(2)}` : `€${remaining.toFixed(2)} over`}
                </span>
            </div>
            <div className="h-3 w-full rounded-full bg-gray-200">
                <div
                    className={`h-3 rounded-full transition-all ${isOverBudget ? 'bg-red-500' : percentage > 80 ? 'bg-yellow-500' : 'bg-green-500'}`}
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}
```

- [ ] **Step 9: Create Budget/Index.tsx**

Create `resources/js/Pages/Budget/Index.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { Client, BudgetCategory, Caregiver } from '@/types/models';
import ProgressBar from '@/Components/Budget/ProgressBar';
import { FormEvent, useState } from 'react';

interface Props {
    client: Client;
    categories: BudgetCategory[];
    caregivers: Caregiver[];
}

export default function Index({ client, categories, caregivers }: Props) {
    const [showCategoryForm, setShowCategoryForm] = useState(false);
    const [showExpenseForm, setShowExpenseForm] = useState(false);

    const categoryForm = useForm({ name: '', allocated_amount: '' });
    const expenseForm = useForm({
        budget_category_id: '',
        caregiver_id: '',
        description: '',
        amount: '',
        date: new Date().toISOString().split('T')[0],
    });

    const submitCategory = (e: FormEvent) => {
        e.preventDefault();
        categoryForm.post(route('clients.budget-categories.store', client.id), {
            onSuccess: () => {
                setShowCategoryForm(false);
                categoryForm.reset();
            },
        });
    };

    const submitExpense = (e: FormEvent) => {
        e.preventDefault();
        expenseForm.post(route('clients.budget-expenses.store', client.id), {
            onSuccess: () => {
                setShowExpenseForm(false);
                expenseForm.reset();
            },
        });
    };

    const deleteExpense = (expenseId: number) => {
        if (confirm('Weet je zeker dat je deze kosten wilt verwijderen?')) {
            router.delete(route('clients.budget-expenses.destroy', [client.id, expenseId]));
        }
    };

    const totalAllocated = categories.reduce((sum, c) => sum + parseFloat(c.allocated_amount), 0);
    const totalSpent = categories.reduce((sum, c) => sum + parseFloat(c.spent_amount), 0);

    return (
        <AuthenticatedLayout>
            <Head title={`Budget - ${client.name}`} />

            <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 py-12">
                <div className="mb-4">
                    <Link href={route('clients.show', client.id)} className="text-sm text-indigo-600 hover:text-indigo-900">
                        &larr; {client.name}
                    </Link>
                </div>

                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-semibold text-gray-900">Budget</h1>
                    <div className="flex gap-3">
                        <button
                            onClick={() => setShowExpenseForm(!showExpenseForm)}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Kosten toevoegen
                        </button>
                        <button
                            onClick={() => setShowCategoryForm(!showCategoryForm)}
                            className="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                        >
                            Categorie toevoegen
                        </button>
                    </div>
                </div>

                {/* Totaal overzicht */}
                <div className="bg-white p-6 shadow-sm sm:rounded-lg mb-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-3">Totaal budget</h2>
                    <ProgressBar allocated={totalAllocated} spent={totalSpent} />
                </div>

                {/* Categorie formulier */}
                {showCategoryForm && (
                    <form onSubmit={submitCategory} className="bg-white p-6 shadow-sm sm:rounded-lg mb-6 flex gap-4 items-end">
                        <div className="flex-1">
                            <label className="block text-sm font-medium text-gray-700">Naam</label>
                            <input
                                type="text"
                                value={categoryForm.data.name}
                                onChange={(e) => categoryForm.setData('name', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div className="w-48">
                            <label className="block text-sm font-medium text-gray-700">Budget (€)</label>
                            <input
                                type="number"
                                step="0.01"
                                value={categoryForm.data.allocated_amount}
                                onChange={(e) => categoryForm.setData('allocated_amount', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <button type="submit" disabled={categoryForm.processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            Opslaan
                        </button>
                    </form>
                )}

                {/* Expense formulier */}
                {showExpenseForm && (
                    <form onSubmit={submitExpense} className="bg-white p-6 shadow-sm sm:rounded-lg mb-6 grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Categorie</label>
                            <select
                                value={expenseForm.data.budget_category_id}
                                onChange={(e) => expenseForm.setData('budget_category_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Selecteer...</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Zorgverlener</label>
                            <select
                                value={expenseForm.data.caregiver_id}
                                onChange={(e) => expenseForm.setData('caregiver_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Geen</option>
                                {caregivers.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Omschrijving</label>
                            <input
                                type="text"
                                value={expenseForm.data.description}
                                onChange={(e) => expenseForm.setData('description', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div className="flex gap-4">
                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700">Bedrag (€)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={expenseForm.data.amount}
                                    onChange={(e) => expenseForm.setData('amount', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700">Datum</label>
                                <input
                                    type="date"
                                    value={expenseForm.data.date}
                                    onChange={(e) => expenseForm.setData('date', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                        <div className="col-span-2">
                            <button type="submit" disabled={expenseForm.processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                Opslaan
                            </button>
                        </div>
                    </form>
                )}

                {/* Categorieën met kosten */}
                <div className="space-y-6">
                    {categories.map((category) => (
                        <div key={category.id} className="bg-white shadow-sm sm:rounded-lg p-6">
                            <div className="flex items-center justify-between mb-3">
                                <h2 className="text-lg font-medium text-gray-900">{category.name}</h2>
                            </div>
                            <ProgressBar
                                allocated={parseFloat(category.allocated_amount)}
                                spent={parseFloat(category.spent_amount)}
                            />
                            {category.expenses && category.expenses.length > 0 && (
                                <table className="mt-4 min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th className="py-2 text-left text-xs font-medium uppercase text-gray-500">Datum</th>
                                            <th className="py-2 text-left text-xs font-medium uppercase text-gray-500">Omschrijving</th>
                                            <th className="py-2 text-left text-xs font-medium uppercase text-gray-500">Zorgverlener</th>
                                            <th className="py-2 text-right text-xs font-medium uppercase text-gray-500">Bedrag</th>
                                            <th className="py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {category.expenses.map((expense) => (
                                            <tr key={expense.id}>
                                                <td className="py-2 text-sm text-gray-500">
                                                    {new Date(expense.date).toLocaleDateString('nl-NL')}
                                                </td>
                                                <td className="py-2 text-sm text-gray-900">{expense.description}</td>
                                                <td className="py-2 text-sm text-gray-500">{expense.caregiver?.name || '—'}</td>
                                                <td className="py-2 text-sm text-gray-900 text-right">€{parseFloat(expense.amount).toFixed(2)}</td>
                                                <td className="py-2 text-right">
                                                    <button
                                                        onClick={() => deleteExpense(expense.id)}
                                                        className="text-sm text-red-600 hover:text-red-900"
                                                    >
                                                        Verwijderen
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 10: Run full test suite**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test
```

Expected: all tests pass.

- [ ] **Step 11: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Http/Controllers/BudgetCategoryController.php app/Http/Controllers/BudgetExpenseController.php app/Http/Requests/BudgetCategoryRequest.php app/Http/Requests/BudgetExpenseRequest.php routes/web.php resources/js/Components/Budget/ resources/js/Pages/Budget/ tests/Feature/BudgetControllerTest.php
git commit -m "feat: add Budget management pages with category/expense tracking"
```

---

## Task 12: Schedule/Planning Pages

**Files:**
- Create: `app/Http/Controllers/ScheduleController.php`
- Create: `app/Http/Controllers/ScheduleExceptionController.php`
- Create: `app/Http/Requests/ScheduleRequest.php`
- Create: `app/Http/Requests/ScheduleExceptionRequest.php`
- Create: `resources/js/Components/Schedule/WeekView.tsx`
- Create: `resources/js/Pages/Schedule/Index.tsx`
- Modify: `routes/web.php`
- Create: `tests/Feature/ScheduleControllerTest.php`

- [ ] **Step 1: Write feature tests**

Create `tests/Feature/ScheduleControllerTest.php`:

```php
<?php

use App\Enums\ScheduleExceptionType;
use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\ScheduleException;
use App\Models\User;

it('shows schedule for a client', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $caregiver = Caregiver::factory()->for($client)->create();
    Schedule::factory()->count(3)->create([
        'client_id' => $client->id,
        'caregiver_id' => $caregiver->id,
    ]);

    $response = $this->actingAs($user)->get("/clients/{$client->id}/schedule");

    $response->assertOk();
    $response->assertInertia(fn ($page) =>
        $page->component('Schedule/Index')
            ->has('schedules', 3)
            ->has('client')
    );
});

it('can create a recurring schedule', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $caregiver = Caregiver::factory()->for($client)->create();

    $response = $this->actingAs($user)->post("/clients/{$client->id}/schedules", [
        'caregiver_id' => $caregiver->id,
        'day_of_week' => 1,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $response->assertRedirect("/clients/{$client->id}/schedule");
    $this->assertDatabaseHas('schedules', [
        'client_id' => $client->id,
        'caregiver_id' => $caregiver->id,
        'day_of_week' => 1,
    ]);
});

it('can delete a schedule', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $caregiver = Caregiver::factory()->for($client)->create();
    $schedule = Schedule::factory()->create([
        'client_id' => $client->id,
        'caregiver_id' => $caregiver->id,
    ]);

    $response = $this->actingAs($user)->delete("/clients/{$client->id}/schedules/{$schedule->id}");

    $response->assertRedirect("/clients/{$client->id}/schedule");
    $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
});

it('can add a schedule exception', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $caregiver = Caregiver::factory()->for($client)->create();
    $schedule = Schedule::factory()->create([
        'client_id' => $client->id,
        'caregiver_id' => $caregiver->id,
    ]);

    $response = $this->actingAs($user)->post("/clients/{$client->id}/schedule-exceptions", [
        'schedule_id' => $schedule->id,
        'caregiver_id' => $caregiver->id,
        'date' => '2026-06-02',
        'start_time' => '10:00',
        'end_time' => '13:00',
        'type' => 'modified',
    ]);

    $response->assertRedirect("/clients/{$client->id}/schedule");
    $this->assertDatabaseHas('schedule_exceptions', [
        'schedule_id' => $schedule->id,
        'type' => 'modified',
    ]);
});

it('can add a standalone appointment', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $caregiver = Caregiver::factory()->for($client)->create();

    $response = $this->actingAs($user)->post("/clients/{$client->id}/schedule-exceptions", [
        'schedule_id' => null,
        'caregiver_id' => $caregiver->id,
        'date' => '2026-06-05',
        'start_time' => '14:00',
        'end_time' => '16:00',
        'type' => 'added',
    ]);

    $response->assertRedirect("/clients/{$client->id}/schedule");
    $this->assertDatabaseHas('schedule_exceptions', [
        'schedule_id' => null,
        'type' => 'added',
        'client_id' => $client->id,
    ]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/ScheduleControllerTest.php
```

Expected: FAIL

- [ ] **Step 3: Create ScheduleRequest and ScheduleExceptionRequest**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:request ScheduleRequest
php artisan make:request ScheduleExceptionRequest
```

Edit `app/Http/Requests/ScheduleRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caregiver_id' => ['required', 'exists:caregivers,id'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

Edit `app/Http/Requests/ScheduleExceptionRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\ScheduleExceptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'caregiver_id' => ['required', 'exists:caregivers,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', Rule::enum(ScheduleExceptionType::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create ScheduleController**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:controller ScheduleController
```

Edit `app/Http/Controllers/ScheduleController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleRequest;
use App\Models\Client;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Client $client): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('Schedule/Index', [
            'client' => $client,
            'schedules' => $client->schedules()->with('caregiver')->get(),
            'exceptions' => $client->scheduleExceptions()->with('caregiver')->get(),
            'caregivers' => $client->caregivers,
        ]);
    }

    public function store(ScheduleRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $client->schedules()->create($request->validated());

        return redirect()->route('clients.schedule.index', $client);
    }

    public function update(ScheduleRequest $request, Client $client, Schedule $schedule): RedirectResponse
    {
        $this->authorize('update', $schedule);

        $schedule->update($request->validated());

        return redirect()->route('clients.schedule.index', $client);
    }

    public function destroy(Client $client, Schedule $schedule): RedirectResponse
    {
        $this->authorize('delete', $schedule);

        $schedule->delete();

        return redirect()->route('clients.schedule.index', $client);
    }
}
```

- [ ] **Step 5: Create ScheduleExceptionController**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:controller ScheduleExceptionController
```

Edit `app/Http/Controllers/ScheduleExceptionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleExceptionRequest;
use App\Models\Client;
use App\Models\ScheduleException;
use Illuminate\Http\RedirectResponse;

class ScheduleExceptionController extends Controller
{
    public function store(ScheduleExceptionRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('view', $client);

        $client->scheduleExceptions()->create($request->validated());

        return redirect()->route('clients.schedule.index', $client);
    }

    public function destroy(Client $client, ScheduleException $scheduleException): RedirectResponse
    {
        $this->authorize('view', $client);

        $scheduleException->delete();

        return redirect()->route('clients.schedule.index', $client);
    }
}
```

- [ ] **Step 6: Add routes**

Add to `routes/web.php` inside the `role:budget_holder` middleware group:

```php
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleExceptionController;

Route::get('clients/{client}/schedule', [ScheduleController::class, 'index'])->name('clients.schedule.index');
Route::post('clients/{client}/schedules', [ScheduleController::class, 'store'])->name('clients.schedules.store');
Route::put('clients/{client}/schedules/{schedule}', [ScheduleController::class, 'update'])->name('clients.schedules.update');
Route::delete('clients/{client}/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('clients.schedules.destroy');

Route::post('clients/{client}/schedule-exceptions', [ScheduleExceptionController::class, 'store'])->name('clients.schedule-exceptions.store');
Route::delete('clients/{client}/schedule-exceptions/{scheduleException}', [ScheduleExceptionController::class, 'destroy'])->name('clients.schedule-exceptions.destroy');
```

- [ ] **Step 7: Run tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/ScheduleControllerTest.php
```

Expected: PASS

- [ ] **Step 8: Create WeekView component**

Create `resources/js/Components/Schedule/WeekView.tsx`:

```tsx
import { Schedule, ScheduleException } from '@/types/models';

interface Props {
    schedules: Schedule[];
    exceptions: ScheduleException[];
    weekStart: string;
    onDeleteSchedule?: (id: number) => void;
    onDeleteException?: (id: number) => void;
}

const dayNames = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];

export default function WeekView({ schedules, exceptions, weekStart, onDeleteSchedule, onDeleteException }: Props) {
    const weekStartDate = new Date(weekStart);

    const getDateForDay = (dayIndex: number): string => {
        const date = new Date(weekStartDate);
        date.setDate(date.getDate() + dayIndex);
        return date.toISOString().split('T')[0];
    };

    const getEntriesForDay = (dayIndex: number) => {
        const dateStr = getDateForDay(dayIndex);
        const daySchedules = schedules.filter((s) => s.day_of_week === dayIndex);
        const dayExceptions = exceptions.filter((e) => e.date === dateStr);

        const cancelledScheduleIds = dayExceptions
            .filter((e) => e.type === 'cancelled')
            .map((e) => e.schedule_id);

        const modifiedScheduleIds = dayExceptions
            .filter((e) => e.type === 'modified')
            .map((e) => e.schedule_id);

        const activeSchedules = daySchedules
            .filter((s) => !cancelledScheduleIds.includes(s.id) && !modifiedScheduleIds.includes(s.id));

        const modifications = dayExceptions.filter((e) => e.type === 'modified');
        const additions = dayExceptions.filter((e) => e.type === 'added');
        const cancellations = dayExceptions.filter((e) => e.type === 'cancelled');

        return { activeSchedules, modifications, additions, cancellations };
    };

    return (
        <div className="grid grid-cols-7 gap-2">
            {dayNames.map((name, index) => {
                const { activeSchedules, modifications, additions, cancellations } = getEntriesForDay(index);
                const dateStr = getDateForDay(index);
                const dateObj = new Date(dateStr);

                return (
                    <div key={index} className="border rounded-lg p-2 min-h-[120px]">
                        <div className="text-xs font-medium text-gray-500 mb-1">
                            {name}
                            <span className="ml-1 text-gray-400">
                                {dateObj.getDate()}/{dateObj.getMonth() + 1}
                            </span>
                        </div>

                        <div className="space-y-1">
                            {activeSchedules.map((s) => (
                                <div key={`s-${s.id}`} className="bg-blue-50 border border-blue-200 rounded px-2 py-1 text-xs group">
                                    <div className="font-medium text-blue-800">{s.caregiver?.name}</div>
                                    <div className="text-blue-600">{s.start_time.slice(0, 5)} - {s.end_time.slice(0, 5)}</div>
                                    {onDeleteSchedule && (
                                        <button onClick={() => onDeleteSchedule(s.id)} className="text-red-500 hidden group-hover:inline text-xs">
                                            ×
                                        </button>
                                    )}
                                </div>
                            ))}

                            {modifications.map((e) => (
                                <div key={`m-${e.id}`} className="bg-yellow-50 border border-yellow-200 rounded px-2 py-1 text-xs group">
                                    <div className="font-medium text-yellow-800">{e.caregiver?.name} (gewijzigd)</div>
                                    <div className="text-yellow-600">{e.start_time.slice(0, 5)} - {e.end_time.slice(0, 5)}</div>
                                    {onDeleteException && (
                                        <button onClick={() => onDeleteException(e.id)} className="text-red-500 hidden group-hover:inline text-xs">
                                            ×
                                        </button>
                                    )}
                                </div>
                            ))}

                            {additions.map((e) => (
                                <div key={`a-${e.id}`} className="bg-green-50 border border-green-200 rounded px-2 py-1 text-xs group">
                                    <div className="font-medium text-green-800">{e.caregiver?.name} (extra)</div>
                                    <div className="text-green-600">{e.start_time.slice(0, 5)} - {e.end_time.slice(0, 5)}</div>
                                    {onDeleteException && (
                                        <button onClick={() => onDeleteException(e.id)} className="text-red-500 hidden group-hover:inline text-xs">
                                            ×
                                        </button>
                                    )}
                                </div>
                            ))}

                            {cancellations.map((e) => (
                                <div key={`c-${e.id}`} className="bg-red-50 border border-red-200 rounded px-2 py-1 text-xs line-through group">
                                    <div className="font-medium text-red-800">{e.caregiver?.name} (geannuleerd)</div>
                                    <div className="text-red-600">{e.start_time.slice(0, 5)} - {e.end_time.slice(0, 5)}</div>
                                    {onDeleteException && (
                                        <button onClick={() => onDeleteException(e.id)} className="text-red-500 hidden group-hover:inline text-xs">
                                            ×
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
```

- [ ] **Step 9: Create Schedule/Index.tsx**

Create `resources/js/Pages/Schedule/Index.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { Client, Schedule, ScheduleException, Caregiver } from '@/types/models';
import WeekView from '@/Components/Schedule/WeekView';
import { FormEvent, useState } from 'react';

interface Props {
    client: Client;
    schedules: Schedule[];
    exceptions: ScheduleException[];
    caregivers: Caregiver[];
}

const dayNames = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];

function getMonday(date: Date): string {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    d.setDate(diff);
    return d.toISOString().split('T')[0];
}

export default function Index({ client, schedules, exceptions, caregivers }: Props) {
    const [weekStart, setWeekStart] = useState(getMonday(new Date()));
    const [showScheduleForm, setShowScheduleForm] = useState(false);
    const [showExceptionForm, setShowExceptionForm] = useState(false);

    const scheduleForm = useForm({
        caregiver_id: '',
        day_of_week: '0',
        start_time: '09:00',
        end_time: '17:00',
        notes: '',
    });

    const exceptionForm = useForm({
        schedule_id: '',
        caregiver_id: '',
        date: '',
        start_time: '09:00',
        end_time: '17:00',
        type: 'added',
        notes: '',
    });

    const navigateWeek = (direction: number) => {
        const current = new Date(weekStart);
        current.setDate(current.getDate() + direction * 7);
        setWeekStart(current.toISOString().split('T')[0]);
    };

    const submitSchedule = (e: FormEvent) => {
        e.preventDefault();
        scheduleForm.post(route('clients.schedules.store', client.id), {
            onSuccess: () => {
                setShowScheduleForm(false);
                scheduleForm.reset();
            },
        });
    };

    const submitException = (e: FormEvent) => {
        e.preventDefault();
        exceptionForm.post(route('clients.schedule-exceptions.store', client.id), {
            onSuccess: () => {
                setShowExceptionForm(false);
                exceptionForm.reset();
            },
        });
    };

    const deleteSchedule = (id: number) => {
        if (confirm('Weet je zeker dat je dit vaste moment wilt verwijderen?')) {
            router.delete(route('clients.schedules.destroy', [client.id, id]));
        }
    };

    const deleteException = (id: number) => {
        router.delete(route('clients.schedule-exceptions.destroy', [client.id, id]));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Planning - ${client.name}`} />

            <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 py-12">
                <div className="mb-4">
                    <Link href={route('clients.show', client.id)} className="text-sm text-indigo-600 hover:text-indigo-900">
                        &larr; {client.name}
                    </Link>
                </div>

                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-semibold text-gray-900">Planning</h1>
                    <div className="flex gap-3">
                        <button
                            onClick={() => setShowExceptionForm(!showExceptionForm)}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Afwijking/afspraak
                        </button>
                        <button
                            onClick={() => setShowScheduleForm(!showScheduleForm)}
                            className="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                        >
                            Vast moment toevoegen
                        </button>
                    </div>
                </div>

                {/* Week navigatie */}
                <div className="flex items-center justify-between mb-4">
                    <button onClick={() => navigateWeek(-1)} className="text-sm text-gray-600 hover:text-gray-900">
                        &larr; Vorige week
                    </button>
                    <span className="text-sm font-medium text-gray-700">
                        Week van {new Date(weekStart).toLocaleDateString('nl-NL')}
                    </span>
                    <button onClick={() => navigateWeek(1)} className="text-sm text-gray-600 hover:text-gray-900">
                        Volgende week &rarr;
                    </button>
                </div>

                {/* Vast moment formulier */}
                {showScheduleForm && (
                    <form onSubmit={submitSchedule} className="bg-white p-6 shadow-sm sm:rounded-lg mb-6 grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Zorgverlener</label>
                            <select
                                value={scheduleForm.data.caregiver_id}
                                onChange={(e) => scheduleForm.setData('caregiver_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Selecteer...</option>
                                {caregivers.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Dag</label>
                            <select
                                value={scheduleForm.data.day_of_week}
                                onChange={(e) => scheduleForm.setData('day_of_week', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {dayNames.map((name, i) => (
                                    <option key={i} value={i}>{name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Van</label>
                            <input
                                type="time"
                                value={scheduleForm.data.start_time}
                                onChange={(e) => scheduleForm.setData('start_time', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Tot</label>
                            <input
                                type="time"
                                value={scheduleForm.data.end_time}
                                onChange={(e) => scheduleForm.setData('end_time', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div className="col-span-2">
                            <button type="submit" disabled={scheduleForm.processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                Opslaan
                            </button>
                        </div>
                    </form>
                )}

                {/* Exception formulier */}
                {showExceptionForm && (
                    <form onSubmit={submitException} className="bg-white p-6 shadow-sm sm:rounded-lg mb-6 grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Type</label>
                            <select
                                value={exceptionForm.data.type}
                                onChange={(e) => exceptionForm.setData('type', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="added">Extra afspraak</option>
                                <option value="modified">Wijziging</option>
                                <option value="cancelled">Annulering</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Zorgverlener</label>
                            <select
                                value={exceptionForm.data.caregiver_id}
                                onChange={(e) => exceptionForm.setData('caregiver_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Selecteer...</option>
                                {caregivers.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Datum</label>
                            <input
                                type="date"
                                value={exceptionForm.data.date}
                                onChange={(e) => exceptionForm.setData('date', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div className="flex gap-4">
                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700">Van</label>
                                <input
                                    type="time"
                                    value={exceptionForm.data.start_time}
                                    onChange={(e) => exceptionForm.setData('start_time', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700">Tot</label>
                                <input
                                    type="time"
                                    value={exceptionForm.data.end_time}
                                    onChange={(e) => exceptionForm.setData('end_time', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                        <div className="col-span-2">
                            <button type="submit" disabled={exceptionForm.processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                Opslaan
                            </button>
                        </div>
                    </form>
                )}

                {/* Week view */}
                <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                    <WeekView
                        schedules={schedules}
                        exceptions={exceptions}
                        weekStart={weekStart}
                        onDeleteSchedule={deleteSchedule}
                        onDeleteException={deleteException}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 10: Run full test suite**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test
```

Expected: all tests pass.

- [ ] **Step 11: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Http/Controllers/ScheduleController.php app/Http/Controllers/ScheduleExceptionController.php app/Http/Requests/ScheduleRequest.php app/Http/Requests/ScheduleExceptionRequest.php routes/web.php resources/js/Components/Schedule/ resources/js/Pages/Schedule/ tests/Feature/ScheduleControllerTest.php
git commit -m "feat: add Schedule/Planning pages with week view"
```

---

## Task 13: Dashboard Page

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Dashboard.tsx`
- Create: `tests/Feature/DashboardTest.php`

- [ ] **Step 1: Write feature tests**

Create `tests/Feature/DashboardTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\BudgetCategory;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;

it('shows dashboard for budget holder with client data', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);
    $client = Client::factory()->for($user, 'budgetHolder')->create();
    $caregiver = Caregiver::factory()->for($client)->create();
    BudgetCategory::factory()->for($client)->count(2)->create();
    Schedule::factory()->create([
        'client_id' => $client->id,
        'caregiver_id' => $caregiver->id,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) =>
        $page->component('Dashboard')
            ->has('clients', 1)
    );
});

it('redirects caregiver to their own schedule view', function () {
    $user = User::factory()->create(['role' => UserRole::Caregiver]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect('/my-schedule');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/DashboardTest.php
```

Expected: FAIL

- [ ] **Step 3: Create DashboardController**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:controller DashboardController
```

Edit `app/Http/Controllers/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        $user = auth()->user();

        if ($user->role === UserRole::Caregiver) {
            return redirect()->route('my-schedule');
        }

        $clients = $user->clients()
            ->with(['budgetCategories', 'caregivers', 'schedules.caregiver'])
            ->get();

        return Inertia::render('Dashboard', [
            'clients' => $clients,
        ]);
    }
}
```

- [ ] **Step 4: Update routes**

Replace the existing dashboard route in `routes/web.php`:

```php
use App\Http\Controllers\DashboardController;

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
```

- [ ] **Step 5: Run tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/DashboardTest.php
```

Expected: PASS (the caregiver redirect test may need the `my-schedule` route — add a placeholder for now if needed).

- [ ] **Step 6: Replace Dashboard.tsx**

Replace `resources/js/Pages/Dashboard.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Client, Schedule } from '@/types/models';
import ProgressBar from '@/Components/Budget/ProgressBar';

interface ClientWithRelations extends Client {
    budget_categories: { id: number; name: string; allocated_amount: string; spent_amount: string }[];
    schedules: (Schedule & { caregiver: { name: string } })[];
}

interface Props {
    clients: ClientWithRelations[];
}

const dayNames = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];

export default function Dashboard({ clients }: Props) {
    const today = new Date().getDay();
    const todayIndex = today === 0 ? 6 : today - 1;

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 py-12">
                <h1 className="text-2xl font-semibold text-gray-900 mb-6">Dashboard</h1>

                {clients.length === 0 ? (
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg text-center">
                        <p className="text-gray-500 mb-4">Welkom! Voeg je eerste cliënt toe om te beginnen.</p>
                        <Link
                            href={route('clients.create')}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Cliënt toevoegen
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-8">
                        {clients.map((client) => {
                            const totalAllocated = client.budget_categories.reduce(
                                (sum, c) => sum + parseFloat(c.allocated_amount), 0
                            );
                            const totalSpent = client.budget_categories.reduce(
                                (sum, c) => sum + parseFloat(c.spent_amount), 0
                            );
                            const todaySchedules = client.schedules.filter(
                                (s) => s.day_of_week === todayIndex
                            );

                            return (
                                <div key={client.id} className="bg-white shadow-sm sm:rounded-lg p-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <Link
                                            href={route('clients.show', client.id)}
                                            className="text-xl font-semibold text-gray-900 hover:text-indigo-600"
                                        >
                                            {client.name}
                                        </Link>
                                        <div className="flex gap-2">
                                            <Link
                                                href={route('clients.budget.index', client.id)}
                                                className="text-sm text-indigo-600 hover:text-indigo-900"
                                            >
                                                Budget
                                            </Link>
                                            <Link
                                                href={route('clients.schedule.index', client.id)}
                                                className="text-sm text-indigo-600 hover:text-indigo-900"
                                            >
                                                Planning
                                            </Link>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        {/* Budget overzicht */}
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500 mb-3">Budget overzicht</h3>
                                            {client.budget_categories.length > 0 ? (
                                                <div className="space-y-3">
                                                    <ProgressBar allocated={totalAllocated} spent={totalSpent} />
                                                    {client.budget_categories.map((cat) => (
                                                        <div key={cat.id}>
                                                            <p className="text-xs text-gray-500 mb-1">{cat.name}</p>
                                                            <ProgressBar
                                                                allocated={parseFloat(cat.allocated_amount)}
                                                                spent={parseFloat(cat.spent_amount)}
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-sm text-gray-400">Nog geen budget ingesteld</p>
                                            )}
                                        </div>

                                        {/* Vandaag planning */}
                                        <div>
                                            <h3 className="text-sm font-medium text-gray-500 mb-3">
                                                Vandaag ({dayNames[todayIndex]})
                                            </h3>
                                            {todaySchedules.length > 0 ? (
                                                <div className="space-y-2">
                                                    {todaySchedules.map((s) => (
                                                        <div key={s.id} className="flex justify-between items-center bg-blue-50 rounded-lg px-3 py-2">
                                                            <span className="text-sm font-medium text-blue-800">
                                                                {s.caregiver.name}
                                                            </span>
                                                            <span className="text-sm text-blue-600">
                                                                {s.start_time.slice(0, 5)} - {s.end_time.slice(0, 5)}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-sm text-gray-400">Geen afspraken vandaag</p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 7: Run full test suite**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test
```

Expected: all tests pass.

- [ ] **Step 8: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Http/Controllers/DashboardController.php routes/web.php resources/js/Pages/Dashboard.tsx tests/Feature/DashboardTest.php
git commit -m "feat: add Dashboard with budget and schedule overview"
```

---

## Task 14: Caregiver Read-Only Schedule View

**Files:**
- Create: `app/Http/Controllers/CaregiverScheduleController.php`
- Create: `resources/js/Pages/CaregiverSchedule/Index.tsx`
- Modify: `routes/web.php`
- Create: `tests/Feature/CaregiverScheduleTest.php`

- [ ] **Step 1: Write feature tests**

Create `tests/Feature/CaregiverScheduleTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;

it('caregiver can view their own schedule', function () {
    $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->for($client)->create(['user_id' => $caregiverUser->id]);
    Schedule::factory()->count(3)->create([
        'client_id' => $client->id,
        'caregiver_id' => $caregiver->id,
    ]);

    $response = $this->actingAs($caregiverUser)->get('/my-schedule');

    $response->assertOk();
    $response->assertInertia(fn ($page) =>
        $page->component('CaregiverSchedule/Index')
            ->has('clients', 1)
    );
});

it('caregiver only sees schedules they are linked to', function () {
    $caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);
    $client = Client::factory()->create();
    $myCaregiver = Caregiver::factory()->for($client)->create(['user_id' => $caregiverUser->id]);
    $otherCaregiver = Caregiver::factory()->for($client)->create();

    Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $myCaregiver->id]);
    Schedule::factory()->create(['client_id' => $client->id, 'caregiver_id' => $otherCaregiver->id]);

    $response = $this->actingAs($caregiverUser)->get('/my-schedule');

    $response->assertInertia(fn ($page) =>
        $page->component('CaregiverSchedule/Index')
            ->where('clients.0.schedules', fn ($schedules) => count($schedules) === 1)
    );
});

it('budget holder cannot access caregiver schedule', function () {
    $user = User::factory()->create(['role' => UserRole::BudgetHolder]);

    $response = $this->actingAs($user)->get('/my-schedule');

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/CaregiverScheduleTest.php
```

Expected: FAIL

- [ ] **Step 3: Create CaregiverScheduleController**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan make:controller CaregiverScheduleController
```

Edit `app/Http/Controllers/CaregiverScheduleController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Caregiver;
use Inertia\Inertia;
use Inertia\Response;

class CaregiverScheduleController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        $caregiverProfiles = Caregiver::where('user_id', $user->id)
            ->with('client')
            ->get();

        $clients = $caregiverProfiles->map(function (Caregiver $caregiver) {
            $client = $caregiver->client;
            $client->schedules = $client->schedules()
                ->where('caregiver_id', $caregiver->id)
                ->with('caregiver')
                ->get();
            $client->exceptions = $client->scheduleExceptions()
                ->where('caregiver_id', $caregiver->id)
                ->with('caregiver')
                ->get();
            return $client;
        });

        return Inertia::render('CaregiverSchedule/Index', [
            'clients' => $clients,
        ]);
    }
}
```

- [ ] **Step 4: Add route**

Add to `routes/web.php`:

```php
use App\Http\Controllers\CaregiverScheduleController;

Route::get('/my-schedule', CaregiverScheduleController::class)
    ->middleware(['auth', 'verified', 'role:caregiver'])
    ->name('my-schedule');
```

- [ ] **Step 5: Run tests**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test tests/Feature/CaregiverScheduleTest.php
```

Expected: PASS

- [ ] **Step 6: Create CaregiverSchedule/Index.tsx**

Create `resources/js/Pages/CaregiverSchedule/Index.tsx`:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Client, Schedule, ScheduleException } from '@/types/models';
import WeekView from '@/Components/Schedule/WeekView';
import { useState } from 'react';

interface ClientWithSchedule extends Client {
    schedules: Schedule[];
    exceptions: ScheduleException[];
}

interface Props {
    clients: ClientWithSchedule[];
}

function getMonday(date: Date): string {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    d.setDate(diff);
    return d.toISOString().split('T')[0];
}

export default function Index({ clients }: Props) {
    const [weekStart, setWeekStart] = useState(getMonday(new Date()));

    const navigateWeek = (direction: number) => {
        const current = new Date(weekStart);
        current.setDate(current.getDate() + direction * 7);
        setWeekStart(current.toISOString().split('T')[0]);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Mijn rooster" />

            <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 py-12">
                <h1 className="text-2xl font-semibold text-gray-900 mb-6">Mijn rooster</h1>

                <div className="flex items-center justify-between mb-4">
                    <button onClick={() => navigateWeek(-1)} className="text-sm text-gray-600 hover:text-gray-900">
                        &larr; Vorige week
                    </button>
                    <span className="text-sm font-medium text-gray-700">
                        Week van {new Date(weekStart).toLocaleDateString('nl-NL')}
                    </span>
                    <button onClick={() => navigateWeek(1)} className="text-sm text-gray-600 hover:text-gray-900">
                        Volgende week &rarr;
                    </button>
                </div>

                {clients.length === 0 ? (
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg text-gray-500">
                        Je bent nog niet gekoppeld aan een cliënt.
                    </div>
                ) : (
                    <div className="space-y-8">
                        {clients.map((client) => (
                            <div key={client.id} className="bg-white shadow-sm sm:rounded-lg p-6">
                                <h2 className="text-lg font-medium text-gray-900 mb-4">{client.name}</h2>
                                <WeekView
                                    schedules={client.schedules}
                                    exceptions={client.exceptions}
                                    weekStart={weekStart}
                                />
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 7: Run full test suite**

```bash
cd /Users/jesse/dev/flowan/pgb
php artisan test
```

Expected: all tests pass.

- [ ] **Step 8: Commit**

```bash
cd /Users/jesse/dev/flowan/pgb
git add app/Http/Controllers/CaregiverScheduleController.php routes/web.php resources/js/Pages/CaregiverSchedule/ tests/Feature/CaregiverScheduleTest.php
git commit -m "feat: add read-only schedule view for caregivers"
```
