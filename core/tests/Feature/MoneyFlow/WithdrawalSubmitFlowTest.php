<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\User\UserWithdrawController;
use App\Models\Transaction;
use App\Models\UserAction;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the web withdrawal-submit flow (debit at request
 * time). A refreshed/replayed done route must debit the wallet once only.
 */
class WithdrawalSubmitFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_withdraw_submit_double_submit_debits_wallet_exactly_once(): void
    {
        $currency = $this->makeCurrency();
        $user     = $this->makeUser();
        $wallet   = $this->makeWallet($user, 'USER', $currency, 100);
        $method   = $this->makeWithdrawMethod();
        $withdraw = $this->makeWithdrawal($user, $wallet, $method, 30, 0); // status 0 = pending submit

        $action = $this->makeUserAction($user->id, 'USER', 'withdraw_money', [
            'done_route' => route('user.withdraw.submit.done'),
        ]);

        $this->actingAs($user);
        session(['wtrx' => $withdraw->trx, 'action_id' => $action->id]);
        $controller = new UserWithdrawController();

        $controller->withdrawSubmitDone();

        $this->assertEqualsWithDelta(70, $this->balanceOf($wallet), 0.00001, 'wallet debited 30 once');
        $this->assertSame(2, (int) Withdrawal::whereKey($withdraw->id)->value('status'), 'withdrawal moved to submitted');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'withdraw')->count());
        $this->assertNull(UserAction::find($action->id), 'one-shot action consumed');

        // Replay (one-shot action already consumed -> graceful no-op)
        $controller->withdrawSubmitDone();

        $this->assertEqualsWithDelta(70, $this->balanceOf($wallet), 0.00001, 'replay must not debit again');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'withdraw')->count());
    }
}
