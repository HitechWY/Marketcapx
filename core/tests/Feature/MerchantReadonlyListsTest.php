<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckStatus;
use App\Http\Middleware\RegistrationStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F3b: the read-only merchant list pages (transactions, wallets) render via Inertia for an
 * authenticated merchant and redirect guests. Read-only; DatabaseTransactions = rollback
 * (no persistent mutation).
 */
class MerchantReadonlyListsTest extends TestCase
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

    public function test_guests_are_redirected(): void
    {
        foreach (['/merchant/transaction/history', '/merchant/wallets'] as $uri) {
            $this->get($uri)->assertStatus(302);
        }
    }

    public function test_transactions_renders_inertia_with_paginator(): void
    {
        $this->actingAs($this->makeMerchant(), 'merchant')
            ->get('/merchant/transaction/history')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Merchant/Transactions')
                ->has('transactions.data')
                ->has('transactions.links')
                ->has('filters')
                ->has('currencies'));
    }

    public function test_transactions_preserves_filter_query(): void
    {
        $this->actingAs($this->makeMerchant(), 'merchant')
            ->get('/merchant/transaction/history?type=plus_trx&operation=add_money')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('filters.type', 'plus_trx')
                ->where('filters.operation', 'add_money'));
    }

    public function test_wallets_renders_inertia(): void
    {
        $this->actingAs($this->makeMerchant(), 'merchant')
            ->get('/merchant/wallets')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Merchant/Wallets')->has('wallets'));
    }
}
