<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_role_attribute(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->role);
        $this->assertInstanceOf(UserRole::class, $user->role);
    }

    public function test_user_role_defaults_to_budget_holder(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::BudgetHolder, $user->role);
    }

    public function test_user_role_can_be_set_to_caregiver(): void
    {
        $user = User::factory()->create(['role' => UserRole::Caregiver]);

        $this->assertSame(UserRole::Caregiver, $user->role);
    }

    public function test_user_role_is_cast_to_enum(): void
    {
        $user = User::factory()->create(['role' => 'caregiver']);

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertSame(UserRole::Caregiver, $user->role);
    }

    public function test_user_role_is_fillable(): void
    {
        $user = new User;
        $user->fill(['role' => UserRole::BudgetHolder->value]);

        $this->assertSame(UserRole::BudgetHolder->value, $user->getAttributes()['role']);
    }

    public function test_user_factory_defaults_to_budget_holder(): void
    {
        $user = User::factory()->make();

        $this->assertSame(UserRole::BudgetHolder, $user->role);
    }
}
