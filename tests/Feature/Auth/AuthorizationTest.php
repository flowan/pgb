<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\BudgetCategory;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $budgetHolder;
    private User $otherBudgetHolder;
    private User $caregiverUser;
    private Client $client;
    private Client $otherClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->budgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $this->otherBudgetHolder = User::factory()->create(['role' => UserRole::BudgetHolder]);
        $this->caregiverUser = User::factory()->create(['role' => UserRole::Caregiver]);

        $this->client = Client::factory()->create(['budget_holder_id' => $this->budgetHolder->id]);
        $this->otherClient = Client::factory()->create(['budget_holder_id' => $this->otherBudgetHolder->id]);

        // Register a temporary test route protected by the role middleware
        Route::middleware(['web', 'auth', 'role:budget_holder'])->get('/test-budget-holder-route', function () {
            return response('OK');
        });
    }

    public function test_budget_holder_can_view_own_client(): void
    {
        $this->assertTrue($this->budgetHolder->can('view', $this->client));
    }

    public function test_budget_holder_cannot_view_other_holders_client(): void
    {
        $this->assertFalse($this->budgetHolder->can('view', $this->otherClient));
    }

    public function test_caregiver_cannot_view_clients(): void
    {
        $this->assertFalse($this->caregiverUser->can('viewAny', Client::class));
        $this->assertFalse($this->caregiverUser->can('view', $this->client));
    }

    public function test_budget_holder_can_manage_caregivers_of_their_client(): void
    {
        $caregiver = Caregiver::factory()->create(['client_id' => $this->client->id]);

        $this->assertTrue($this->budgetHolder->can('view', $caregiver));
        $this->assertTrue($this->budgetHolder->can('update', $caregiver));
        $this->assertTrue($this->budgetHolder->can('delete', $caregiver));
        $this->assertTrue($this->budgetHolder->can('create', Caregiver::class));
    }

    public function test_budget_holder_cannot_manage_caregivers_of_other_client(): void
    {
        $caregiver = Caregiver::factory()->create(['client_id' => $this->otherClient->id]);

        $this->assertFalse($this->budgetHolder->can('view', $caregiver));
        $this->assertFalse($this->budgetHolder->can('update', $caregiver));
        $this->assertFalse($this->budgetHolder->can('delete', $caregiver));
    }

    public function test_budget_holder_can_manage_budget_categories_of_their_client(): void
    {
        $category = BudgetCategory::factory()->create(['client_id' => $this->client->id]);

        $this->assertTrue($this->budgetHolder->can('view', $category));
        $this->assertTrue($this->budgetHolder->can('update', $category));
        $this->assertTrue($this->budgetHolder->can('delete', $category));
        $this->assertTrue($this->budgetHolder->can('create', BudgetCategory::class));
    }

    public function test_budget_holder_cannot_manage_budget_categories_of_other_client(): void
    {
        $category = BudgetCategory::factory()->create(['client_id' => $this->otherClient->id]);

        $this->assertFalse($this->budgetHolder->can('view', $category));
        $this->assertFalse($this->budgetHolder->can('update', $category));
        $this->assertFalse($this->budgetHolder->can('delete', $category));
    }

    public function test_role_middleware_allows_budget_holder(): void
    {
        $response = $this->actingAs($this->budgetHolder)->get('/test-budget-holder-route');

        $response->assertOk();
    }

    public function test_role_middleware_blocks_caregiver_from_budget_holder_routes(): void
    {
        $response = $this->actingAs($this->caregiverUser)->get('/test-budget-holder-route');

        $response->assertForbidden();
    }

    public function test_role_middleware_blocks_unauthenticated_users(): void
    {
        $response = $this->get('/test-budget-holder-route');

        // Unauthenticated users get redirected to login by auth middleware
        $response->assertRedirect();
    }
}
