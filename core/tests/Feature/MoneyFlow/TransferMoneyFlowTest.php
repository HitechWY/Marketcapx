<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\User\UserOperationController;
use App\Models\Transaction;
use App\Models\UserAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the web user->user transfer flow (sender debit ->
 * receiver credit). A refreshed/replayed done route must move funds once only.
 */
class TransferMoneyFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_transfer_double_submit_moves_funds_exactly_once(): void
    {
        $currency       = $this->makeCurrency();
        $sender         = $this->makeUser();
        $senderWallet   = $this->makeWallet($sender, 'USER', $currency, 100);
        $receiver       = $this->makeUser();
        $receiverWallet = $this->makeWallet($receiver, 'USER', $currency, 0);

        $action = $this->makeUserAction($sender->id, 'USER', 'transfer_money', [
            'wallet_id'   => $senderWallet->id,
            'amount'      => 10,
            'totalAmount' => 11,    // amount + charge
            'totalCharge' => 1,
            'receiver_id' => $receiver->id,
            'done_route'  => route('user.transfer.done'),
        ]);

        $this->actingAs($sender);
        session(['action_id' => $action->id]);
        $controller = new UserOperationController();

        $controller->transferMoneyDone();

        $this->assertEqualsWithDelta(89, $this->balanceOf($senderWallet), 0.00001, 'sender debited 11 once');
        $this->assertEqualsWithDelta(10, $this->balanceOf($receiverWallet), 0.00001, 'receiver credited 10 once');
        $this->assertSame(1, Transaction::where('wallet_id', $senderWallet->id)->where('remark', 'transfer_money')->count());
        $this->assertSame(1, Transaction::where('wallet_id', $receiverWallet->id)->where('remark', 'transfer_money')->count());
        $this->assertNull(UserAction::find($action->id), 'one-shot action consumed');

        // Replay
        $controller->transferMoneyDone();

        $this->assertEqualsWithDelta(89, $this->balanceOf($senderWallet), 0.00001, 'replay must not debit again');
        $this->assertEqualsWithDelta(10, $this->balanceOf($receiverWallet), 0.00001, 'replay must not credit again');
        $this->assertSame(1, Transaction::where('wallet_id', $senderWallet->id)->where('remark', 'transfer_money')->count());
    }
}
