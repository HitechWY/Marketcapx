<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F6b: admin read-only list pages (users/agents/merchants, deposits, withdrawals, support
 * tickets) render via Inertia for an authenticated admin and redirect guests. Read-only —
 * no mutation forms migrated. DatabaseTransactions = rollback (no persistent mutation).
 */
class AdminReadonlyListsTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Laramin\Utility\Utility::class,
            \Laramin\Utility\GoToCore::class,
            AdminAuditLog::class,
        ]);
    }

    public function test_guests_are_redirected(): void
    {
        foreach (['/admin/users', '/admin/agents', '/admin/merchants', '/admin/deposit/all', '/admin/withdraw/all', '/admin/ticket'] as $uri) {
            $this->get($uri)->assertStatus(302);
        }
    }

    public function test_guard_lists_render_inertia(): void
    {
        $admin = $this->makeAdmin();
        foreach (['users', 'agents', 'merchants'] as $guard) {
            $this->actingAs($admin, 'admin')
                ->get("/admin/$guard")
                ->assertOk()
                ->assertInertia(fn (Assert $p) => $p
                    ->component('Admin/GuardList')
                    ->where('guardType', $guard)
                    ->has('users.data')
                    ->has('users.links')
                    ->has('filters'));
        }
    }

    public function test_deposits_list_renders_inertia(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/deposit/all')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Deposits')
                ->has('deposits.data')
                ->has('deposits.links')
                ->has('filters')
                ->has('currencies'));
    }

    public function test_withdrawals_list_renders_inertia(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/withdraw/all')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Withdrawals')
                ->has('withdrawals.data')
                ->has('withdrawals.links')
                ->has('filters'));
    }

    public function test_support_tickets_list_renders_inertia(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/ticket')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/SupportTickets')
                ->has('tickets.data')
                ->has('tickets.links')
                ->has('filters'));
    }

    public function test_deposit_filter_query_preserved(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/deposit/all?search=ABC&user_type=user')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('filters.search', 'ABC')
                ->where('filters.user_type', 'user'));
    }
}
