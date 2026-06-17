<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Http\Controllers\Gateway\PaymentController;
use App\Models\Deposit;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Phase 3 coverage: the deposit credit path (PaymentController::userDataUpdate)
 * is transaction-wrapped and idempotent. Runs against the disposable
 * marketcapx_test DB; DatabaseTransactions rolls back per test. No real gateway
 * is contacted.
 */
class DepositIdempotencyTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_deposit_credit_is_idempotent(): void
    {
        $currency = $this->makeCurrency();
        $user     = $this->makeUser();
        $wallet   = $this->makeWallet($user, 'USER', $currency, 0);
        $this->makeGatewayCurrency(1001, $currency->currency_code, 'Test Gateway');
        $deposit  = $this->makeDeposit($user, $wallet, $currency, 40, 1001, Status::PAYMENT_INITIATE);

        PaymentController::userDataUpdate($deposit);

        $this->assertEqualsWithDelta(40, $this->balanceOf($wallet), 0.00001, 'wallet credited once');
        $this->assertSame(Status::PAYMENT_SUCCESS, (int) Deposit::whereKey($deposit->id)->value('status'));
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('trx', $deposit->trx)->count());

        // Reprocess the same deposit: now SUCCESS -> re-checked under the lock -> no-op.
        $fresh = Deposit::find($deposit->id);
        PaymentController::userDataUpdate($fresh);

        $this->assertEqualsWithDelta(40, $this->balanceOf($wallet), 0.00001, 'replay must not credit again');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('trx', $deposit->trx)->count(), 'no duplicate ledger row');
    }
}
