<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\Api\User\WithdrawController;
use App\Models\Transaction;
use App\Models\UserAction;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the API withdrawal-submit flow (used-flag variant,
 * debit at request time). withdrawSubmitDone($actionId) must debit once only.
 */
class ApiWithdrawalSubmitFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_api_withdraw_submit_double_submit_debits_wallet_exactly_once(): void
    {
        $currency = $this->makeCurrency();
        $user     = $this->makeUser();
        $wallet   = $this->makeWallet($user, 'USER', $currency, 100);
        $method   = $this->makeWithdrawMethod();
        $withdraw = $this->makeWithdrawal($user, $wallet, $method, 30, 0); // status 0 = pending submit

        $action = $this->makeUserAction($user->id, 'USER', 'withdraw_money', [
            'trx'        => $withdraw->trx,
            'done_route' => 'api.withdraw.submit.done',
        ], isApi: true);

        $this->actingAs($user);
        $controller = new WithdrawController();

        $controller->withdrawSubmitDone($action->id);

        $this->assertEqualsWithDelta(70, $this->balanceOf($wallet), 0.00001, 'wallet debited 30 once');
        $this->assertSame(2, (int) Withdrawal::whereKey($withdraw->id)->value('status'), 'withdrawal moved to pending');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'withdraw')->count());
        $this->assertSame(1, (int) UserAction::whereKey($action->id)->value('used'), 'action marked used');

        // Replay (used=1 now -> graceful no-op)
        $controller->withdrawSubmitDone($action->id);

        $this->assertEqualsWithDelta(70, $this->balanceOf($wallet), 0.00001, 'replay must not debit again');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'withdraw')->count());
    }
}
