<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\Module;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F3d: the read-only agent list pages (transactions, wallets, commission log, add-money
 * history) render via Inertia for an authenticated agent and redirect guests. Read-only;
 * DatabaseTransactions = rollback (no persistent mutation).
 */
class AgentReadonlyListsTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Laramin\Utility\Utility::class,
            \Laramin\Utility\GoToCore::class,
            CheckStatus::class,
            RegistrationStep::class,
            Module::class, // agent.deposit.history is gated by module:withdraw_money
        ]);
    }

    public function test_guests_are_redirected(): void
    {
        foreach (['/agent/transaction/history', '/agent/wallets', '/agent/commission-log', '/agent/add-money/history'] as $uri) {
            $this->get($uri)->assertStatus(302);
        }
    }

    public function test_transactions_renders_inertia_with_paginator(): void
    {
        $this->actingAs($this->makeAgent(), 'agent')
            ->get('/agent/transaction/history')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Agent/Transactions')
                ->has('transactions.data')
                ->has('transactions.links')
                ->has('filters')
                ->has('currencies'));
    }

    public function test_transactions_preserves_filter_query(): void
    {
        $this->actingAs($this->makeAgent(), 'agent')
            ->get('/agent/transaction/history?type=plus_trx&operation=add_money')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('filters.type', 'plus_trx')
                ->where('filters.operation', 'add_money'));
    }

    public function test_wallets_renders_inertia(): void
    {
        $this->actingAs($this->makeAgent(), 'agent')
            ->get('/agent/wallets')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Agent/Wallets')->has('wallets'));
    }

    public function test_commission_log_renders_inertia_with_paginator(): void
    {
        $this->actingAs($this->makeAgent(), 'agent')
            ->get('/agent/commission-log')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Agent/CommissionLog')
                ->has('transactions.data')
                ->has('transactions.links'));
    }

    public function test_deposit_history_renders_inertia_with_paginator(): void
    {
        $this->actingAs($this->makeAgent(), 'agent')
            ->get('/agent/add-money/history')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Agent/DepositHistory')
                ->has('deposits.data')
                ->has('deposits.links'));
    }
}
