<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\Agent\MoneyInController;
use App\Models\Transaction;
use App\Models\UserAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the agent web money-in flow (agent debit -> user
 * credit + agent commission).
 */
class MoneyInFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_money_in_double_submit_moves_funds_exactly_once(): void
    {
        $currency    = $this->makeCurrency();
        $agent       = $this->makeAgent();
        $agentWallet = $this->makeWallet($agent, 'AGENT', $currency, 100);
        $user        = $this->makeUser();
        $userWallet  = $this->makeWallet($user, 'USER', $currency, 0);

        $action = $this->makeUserAction($agent->id, 'AGENT', 'money_in', [
            'agentWallet_id'  => $agentWallet->id,
            'user_id'         => $user->id,
            'amount'          => 10,
            'totalCommission' => 2,
        ]);

        $this->actingAs($agent, 'agent');
        session(['action_id' => $action->id]);
        $controller = new MoneyInController();

        $controller->moneyInDone();

        $this->assertEqualsWithDelta(92, $this->balanceOf($agentWallet), 0.00001, 'agent: -10 +2 commission = 92');
        $this->assertEqualsWithDelta(10, $this->balanceOf($userWallet), 0.00001, 'user credited 10 once');
        $this->assertSame(1, Transaction::where('wallet_id', $agentWallet->id)->where('remark', 'money_in')->count());
        $this->assertSame(1, Transaction::where('wallet_id', $userWallet->id)->where('remark', 'money_in')->count());
        $this->assertSame(1, Transaction::where('wallet_id', $agentWallet->id)->where('remark', 'commission')->count());
        $this->assertNull(UserAction::find($action->id), 'one-shot action consumed');

        // Replay
        $controller->moneyInDone();

        $this->assertEqualsWithDelta(92, $this->balanceOf($agentWallet), 0.00001, 'replay must not move funds again');
        $this->assertEqualsWithDelta(10, $this->balanceOf($userWallet), 0.00001, 'replay must not credit again');
        $this->assertSame(1, Transaction::where('wallet_id', $userWallet->id)->where('remark', 'money_in')->count());
    }
}
