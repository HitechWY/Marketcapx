<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F6c: admin read-only report pages (transaction report, login history) render via Inertia
 * for an authenticated admin and redirect guests. Notification history + email details stay
 * Blade (sensitive payloads). DatabaseTransactions = rollback (no persistent mutation).
 */
class AdminReportsTest extends TestCase
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
        foreach (['/admin/report/transaction', '/admin/report/login/history'] as $uri) {
            $this->get($uri)->assertStatus(302);
        }
    }

    public function test_transaction_report_renders_inertia(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/report/transaction')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Transactions')
                ->has('transactions.data')
                ->has('transactions.links')
                ->has('filters')
                ->has('currencies')
                ->has('remarks'));
    }

    public function test_transaction_report_preserves_filters(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/report/transaction?search=ZZ&trx_type=%2B')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('filters.search', 'ZZ')
                ->where('filters.trx_type', '+'));
    }

    public function test_login_history_renders_inertia(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/report/login/history')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/LoginHistory')
                ->has('logins.data')
                ->has('logins.links')
                ->where('showFilter', true));
    }
}
