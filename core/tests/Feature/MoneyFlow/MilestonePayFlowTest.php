<?php

namespace Tests\Feature\MoneyFlow;

use App\Constants\Status;
use App\Http\Controllers\User\MilestoneController;
use App\Models\Escrow;
use App\Models\Milestone;
use App\Models\Transaction;
use App\Models\UserAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the escrow milestone-pay flow (buyer wallet debit
 * into escrow). A refreshed/replayed done route must debit + fund once only.
 */
class MilestonePayFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_milestone_pay_double_submit_debits_and_funds_exactly_once(): void
    {
        $currency = $this->makeCurrency();
        $buyer    = $this->makeUser();
        $wallet   = $this->makeWallet($buyer, 'USER', $currency, 100);

        $escrow = new Escrow();
        $escrow->buyer_id     = $buyer->id;
        $escrow->seller_id    = $this->makeUser()->id;
        $escrow->currency_id  = $currency->id;
        $escrow->amount       = 50;
        $escrow->buyer_charge = 0;
        $escrow->paid_amount  = 0;
        $escrow->status       = Status::ESCROW_ACCEPTED;
        $escrow->save();

        $milestone = new Milestone();
        $milestone->escrow_id      = $escrow->id;
        $milestone->user_id        = $buyer->id;
        $milestone->amount         = 20;
        $milestone->payment_status = Status::MILESTONE_UNFUNDED;
        $milestone->save();

        $action = $this->makeUserAction($buyer->id, 'USER', 'milestone_pay', [
            'wallet_id'    => $wallet->id,
            'milestone_id' => $milestone->id,
            'done_route'   => route('user.escrow.milestone.pay.done'),
        ]);

        $this->actingAs($buyer);
        session(['action_id' => $action->id]);
        $controller = new MilestoneController();

        $controller->payMilestoneDone();

        $this->assertEqualsWithDelta(80, $this->balanceOf($wallet), 0.00001, 'buyer debited 20 once');
        $this->assertSame(Status::MILESTONE_FUNDED, (int) Milestone::whereKey($milestone->id)->value('payment_status'), 'milestone funded');
        $this->assertEqualsWithDelta(20, (float) Escrow::whereKey($escrow->id)->value('paid_amount'), 0.00001, 'escrow paid_amount bumped once');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'milestone_paid')->count());
        $this->assertNull(UserAction::find($action->id), 'one-shot action consumed');

        // Replay (one-shot action already consumed -> graceful no-op)
        $controller->payMilestoneDone();

        $this->assertEqualsWithDelta(80, $this->balanceOf($wallet), 0.00001, 'replay must not debit again');
        $this->assertEqualsWithDelta(20, (float) Escrow::whereKey($escrow->id)->value('paid_amount'), 0.00001, 'replay must not bump again');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'milestone_paid')->count());
    }
}
