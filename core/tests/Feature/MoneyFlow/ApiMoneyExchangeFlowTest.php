<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\Api\User\MoneyExchangeController;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Scope coverage for the API money-exchange flow (twin of the web controller).
 *
 * exchangeForm() returns to_wallets = $user->wallets(), so the destination is
 * always one of the caller's OWN wallets. exchangeConfirm() must enforce the same
 * scope on to_wallet_id, otherwise a caller can credit an arbitrary/other-user
 * wallet. See MoneyExchangeFlowTest for the web equivalent.
 */
class ApiMoneyExchangeFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_api_exchange_destination_must_belong_to_the_user(): void
    {
        TransactionCharge::where('slug', 'exchange_charge')->update([
            'percent_charge' => 0,
            'fixed_charge'   => 0,
            'cap'            => -1,
        ]);

        $from = $this->makeCurrency(['rate' => 1, 'currency_type' => 1]);
        $to   = $this->makeCurrency(['rate' => 1, 'currency_type' => 1]);

        $attacker     = $this->makeUser();
        $attackerFrom = $this->makeWallet($attacker, 'USER', $from, 100);

        $victim       = $this->makeUser();
        $victimWallet = $this->makeWallet($victim, 'USER', $to, 0);

        $this->actingAs($attacker);
        $controller = new MoneyExchangeController();

        $response = $controller->exchangeConfirm(Request::create('/api/exchange/money', 'POST', [
            'amount'         => 10,
            'from_wallet_id' => $attackerFrom->id,
            'to_wallet_id'   => $victimWallet->id,
        ]));

        $payload = json_decode($response->getContent(), true);
        $this->assertSame('error', $payload['status'], 'cross-user destination is rejected');

        $this->assertEqualsWithDelta(100, $this->balanceOf($attackerFrom), 0.00001, 'attacker source must not be debited');
        $this->assertEqualsWithDelta(0, $this->balanceOf($victimWallet), 0.00001, 'victim wallet must not be credited');
        $this->assertSame(0, Transaction::where('wallet_id', $attackerFrom->id)->where('remark', 'exchange_money')->count());
        $this->assertSame(0, Transaction::where('wallet_id', $victimWallet->id)->where('remark', 'exchange_money')->count());
    }

    public function test_api_exchange_between_own_wallets_succeeds(): void
    {
        TransactionCharge::where('slug', 'exchange_charge')->update([
            'percent_charge' => 0,
            'fixed_charge'   => 0,
            'cap'            => -1,
        ]);

        $from = $this->makeCurrency(['rate' => 1, 'currency_type' => 1]);
        $to   = $this->makeCurrency(['rate' => 1, 'currency_type' => 1]);
        $user = $this->makeUser();
        $fromWallet = $this->makeWallet($user, 'USER', $from, 50);
        $toWallet   = $this->makeWallet($user, 'USER', $to, 0);

        $this->actingAs($user);
        $controller = new MoneyExchangeController();

        $response = $controller->exchangeConfirm(Request::create('/api/exchange/money', 'POST', [
            'amount'         => 10,
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id'   => $toWallet->id,
        ]));

        $payload = json_decode($response->getContent(), true);
        $this->assertSame('success', $payload['status'], 'own-wallet exchange still works');
        $this->assertEqualsWithDelta(40, $this->balanceOf($fromWallet), 0.00001, 'source debited once');
        $this->assertEqualsWithDelta(10, $this->balanceOf($toWallet), 0.00001, 'destination credited once');
    }
}
