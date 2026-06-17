<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\User\MoneyExchangeController;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Coverage for the web money-exchange flow.
 *
 * Money-exchange has NO single-use token (a deliberate second submit is a second
 * exchange). The guarantee the overnight fix adds is atomicity + a balance
 * re-check under the wallet lock, so a double-submit cannot OVERDRAFT. This test
 * sets the balance to exactly one exchange and asserts the second submit is
 * rejected -> exactly one source debit and one destination credit.
 */
class MoneyExchangeFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_exchange_double_submit_cannot_overdraft(): void
    {
        // Zero out the exchange charge for a deterministic 1:1 conversion.
        TransactionCharge::where('slug', 'exchange_charge')->update([
            'percent_charge' => 0,
            'fixed_charge'   => 0,
            'cap'            => -1,
        ]);

        $from = $this->makeCurrency(['rate' => 1, 'currency_type' => 1]);
        $to   = $this->makeCurrency(['rate' => 1, 'currency_type' => 1]);
        $user = $this->makeUser();
        $fromWallet = $this->makeWallet($user, 'USER', $from, 10);  // exactly one exchange
        $toWallet   = $this->makeWallet($user, 'USER', $to, 0);

        $this->actingAs($user);
        $controller = new MoneyExchangeController();

        $make = fn () => Request::create('/exchange/money', 'POST', [
            'amount'         => 10,
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id'   => $toWallet->id,
        ]);

        $controller->exchangeConfirm($make());

        $this->assertEqualsWithDelta(0, $this->balanceOf($fromWallet), 0.00001, 'source debited once');
        $this->assertEqualsWithDelta(10, $this->balanceOf($toWallet), 0.00001, 'destination credited once');
        $this->assertSame(1, Transaction::where('wallet_id', $fromWallet->id)->where('remark', 'exchange_money')->count());
        $this->assertSame(1, Transaction::where('wallet_id', $toWallet->id)->where('remark', 'exchange_money')->count());

        // Second submit: source now 0, must be rejected (no overdraft, no partial move).
        $controller->exchangeConfirm($make());

        $this->assertEqualsWithDelta(0, $this->balanceOf($fromWallet), 0.00001, 'no overdraft on replay');
        $this->assertEqualsWithDelta(10, $this->balanceOf($toWallet), 0.00001, 'destination not credited again');
        $this->assertSame(1, Transaction::where('wallet_id', $fromWallet->id)->where('remark', 'exchange_money')->count(), 'no duplicate debit ledger');
        $this->assertSame(1, Transaction::where('wallet_id', $toWallet->id)->where('remark', 'exchange_money')->count(), 'no duplicate credit ledger');
    }
}
