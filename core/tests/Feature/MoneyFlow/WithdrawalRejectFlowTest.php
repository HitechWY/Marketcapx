<?php

namespace Tests\Feature\MoneyFlow;

use App\Constants\Status;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the admin withdrawal-rejection refund.
 */
class WithdrawalRejectFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_reject_double_submit_refunds_exactly_once(): void
    {
        $currency = $this->makeCurrency();
        $user     = $this->makeUser();
        $wallet   = $this->makeWallet($user, 'USER', $currency, 50);
        $method   = $this->makeWithdrawMethod();
        $withdraw = $this->makeWithdrawal($user, $wallet, $method, 20, Status::PAYMENT_PENDING);

        $controller = new WithdrawalController();
        $make = fn () => Request::create('/admin/withdraw/reject', 'POST', [
            'id'      => $withdraw->id,
            'details' => 'rejected for test',
        ]);

        $controller->reject($make());

        $this->assertEqualsWithDelta(70, $this->balanceOf($wallet), 0.00001, 'refund credited once (50 + 20)');
        $this->assertSame(Status::PAYMENT_REJECT, (int) Withdrawal::whereKey($withdraw->id)->value('status'));
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'withdraw_reject')->count());

        // Replay: status is no longer PENDING -> idempotent no-op.
        $controller->reject($make());

        $this->assertEqualsWithDelta(70, $this->balanceOf($wallet), 0.00001, 'replay must not refund again');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'withdraw_reject')->count(), 'no duplicate refund ledger');
    }
}
