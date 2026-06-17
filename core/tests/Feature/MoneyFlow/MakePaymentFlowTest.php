<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\User\MakePaymentController;
use App\Models\Transaction;
use App\Models\UserAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Idempotency coverage for the web user -> merchant payment flow.
 */
class MakePaymentFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_payment_double_submit_debits_payer_and_credits_merchant_exactly_once(): void
    {
        $currency      = $this->makeCurrency();
        $user          = $this->makeUser();
        $userWallet    = $this->makeWallet($user, 'USER', $currency, 100);
        $merchant      = $this->makeMerchant();
        $merchantWallet = $this->makeWallet($merchant, 'MERCHANT', $currency, 0);

        $action = $this->makeUserAction($user->id, 'USER', 'make_payment', [
            'wallet_id'           => $userWallet->id,
            'amount'              => 10,
            'userTotalAmount'     => 11,
            'totalCharge'         => 1,
            'merchant_id'         => $merchant->id,
            'merchantTotalCharge' => 0,
            'done_route'          => route('user.payment.done'),
        ]);

        $this->actingAs($user);
        session(['action_id' => $action->id]);
        $controller = new MakePaymentController();

        $controller->paymentDone();

        $this->assertEqualsWithDelta(89, $this->balanceOf($userWallet), 0.00001, 'payer debited once (10 + 1 charge)');
        $this->assertEqualsWithDelta(10, $this->balanceOf($merchantWallet), 0.00001, 'merchant credited once');
        $this->assertSame(1, Transaction::where('wallet_id', $userWallet->id)->where('remark', 'make_payment')->count());
        $this->assertSame(1, Transaction::where('wallet_id', $merchantWallet->id)->where('remark', 'make_payment')->count());
        $this->assertNull(UserAction::find($action->id), 'one-shot action consumed');

        // Replay
        $controller->paymentDone();

        $this->assertEqualsWithDelta(89, $this->balanceOf($userWallet), 0.00001, 'replay must not debit again');
        $this->assertEqualsWithDelta(10, $this->balanceOf($merchantWallet), 0.00001, 'replay must not credit again');
        $this->assertSame(1, Transaction::where('wallet_id', $userWallet->id)->where('remark', 'make_payment')->count(), 'no duplicate payer ledger');
    }
}
