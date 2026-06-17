<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\User\MoneyOutController;
use App\Models\Transaction;
use App\Models\UserAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the web money-out flow (user debit -> agent credit
 * + agent commission).
 */
class MoneyOutFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_money_out_double_submit_moves_funds_exactly_once(): void
    {
        $currency    = $this->makeCurrency();
        $user        = $this->makeUser();
        $userWallet  = $this->makeWallet($user, 'USER', $currency, 100);
        $agent       = $this->makeAgent();
        $agentWallet = $this->makeWallet($agent, 'AGENT', $currency, 0);

        $action = $this->makeUserAction($user->id, 'USER', 'money_out', [
            'wallet_id'        => $userWallet->id,
            'amount'           => 10,
            'totalAmount'      => 11,    // amount + charge
            'totalCharge'      => 1,
            'agent_id'         => $agent->id,
            'agent_wallet_id'  => $agentWallet->id,
            'total_commission' => 2,
            'done_route'       => route('user.money.out.done'),
        ]);

        $this->actingAs($user);
        session(['action_id' => $action->id]);
        $controller = new MoneyOutController();

        $controller->moneyOutDone();

        $this->assertEqualsWithDelta(89, $this->balanceOf($userWallet), 0.00001, 'user debited 11 once');
        $this->assertEqualsWithDelta(12, $this->balanceOf($agentWallet), 0.00001, 'agent credited 10 + 2 commission once');
        $this->assertSame(1, Transaction::where('wallet_id', $userWallet->id)->where('remark', 'money_out')->count());
        $this->assertSame(1, Transaction::where('wallet_id', $agentWallet->id)->where('remark', 'money_out')->count());
        $this->assertSame(1, Transaction::where('wallet_id', $agentWallet->id)->where('remark', 'commission')->count());
        $this->assertNull(UserAction::find($action->id), 'one-shot action consumed');

        // Replay
        $controller->moneyOutDone();

        $this->assertEqualsWithDelta(89, $this->balanceOf($userWallet), 0.00001, 'replay must not debit again');
        $this->assertEqualsWithDelta(12, $this->balanceOf($agentWallet), 0.00001, 'replay must not credit again');
        $this->assertSame(1, Transaction::where('wallet_id', $userWallet->id)->where('remark', 'money_out')->count());
    }
}
