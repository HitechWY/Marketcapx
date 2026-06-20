<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F6a: the admin dashboard renders via Inertia for an authenticated admin and redirects
 * guests to the admin login. Read-only; admin shared props expose only safe fields (no
 * password/remember_token). DatabaseTransactions = rollback (no persistent mutation).
 */
class AdminDashboardTest extends TestCase
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

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/dashboard')->assertStatus(302);
    }

    public function test_dashboard_renders_inertia_for_admin(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Dashboard')
                ->has('widget')
                ->has('deposits')
                ->has('withdrawals')
                ->has('loginCharts')
                ->has('chartEndpoints')
                ->where('auth.user.type', 'ADMIN')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token'));
    }
}
