<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F2b coverage: the read-only user list pages (transactions, wallets, deposit/withdraw
 * history) render via Inertia for an authenticated user and redirect guests to login.
 * Read-only; DatabaseTransactions = rollback (no persistent mutation).
 */
class UserReadonlyListsTest extends TestCase
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
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/user/transactions', '/user/wallets', '/user/deposit/history', '/user/withdraw/history'] as $uri) {
            $res = $this->get($uri);
            $res->assertStatus(302);
            $this->assertStringContainsString('login', (string) $res->headers->get('Location'), "Route $uri should redirect to login");
        }
    }

    public function test_transactions_renders_inertia_with_paginator(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/user/transactions')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('User/Transactions')
                ->has('transactions.data')
                ->has('transactions.links')
                ->has('filters')
                ->has('currencies'));
    }

    public function test_transactions_preserves_filter_query(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/user/transactions?type=plus_trx&operation=add_money')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('filters.type', 'plus_trx')
                ->where('filters.operation', 'add_money'));
    }

    public function test_wallets_renders_inertia(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/user/wallets')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('User/Wallets')->has('wallets'));
    }

    public function test_deposit_history_renders_inertia_with_paginator(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/user/deposit/history')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('User/DepositHistory')
                ->has('deposits.data')
                ->has('deposits.links'));
    }

    public function test_withdraw_history_renders_inertia_with_paginator(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/user/withdraw/history')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('User/WithdrawHistory')
                ->has('withdraws.data')
                ->has('withdraws.links'));
    }
}
