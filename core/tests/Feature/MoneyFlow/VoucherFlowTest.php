<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\User\VoucherController;
use App\Models\Transaction;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Phase: money-flow idempotency coverage (overnight test sprint).
 *
 * Drives the web VoucherController *Done/Confirm methods directly (auth + session
 * set up by hand) against the disposable marketcapx_test DB. DatabaseTransactions
 * rolls everything back after each test, so nothing persists and paytabz_DB is
 * never touched. The controller's own DB::transaction runs as a savepoint inside
 * the test transaction.
 */
class VoucherFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_voucher_redeem_double_submit_credits_exactly_once(): void
    {
        $currency = $this->makeCurrency();
        $owner    = $this->makeUser();                  // voucher creator
        $redeemer = $this->makeUser();                  // the one redeeming
        $wallet   = $this->makeWallet($redeemer, 'USER', $currency, 0);
        $voucher  = $this->makeVoucher($owner, $currency, 50);

        $this->actingAs($redeemer);
        $controller = new VoucherController();

        $req = Request::create('/voucher/redeem', 'POST', ['code' => $voucher->voucher_code]);
        $controller->userVoucherRedeemConfirm($req);

        $this->assertEqualsWithDelta(50, $this->balanceOf($wallet), 0.00001, 'first redeem should credit once');
        $this->assertSame(1, (int) Voucher::whereKey($voucher->id)->value('is_used'), 'voucher should be marked used');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'redeem_voucher')->count());

        // Second submit of the SAME code -> the is_used=0 lookup finds nothing.
        $req2 = Request::create('/voucher/redeem', 'POST', ['code' => $voucher->voucher_code]);
        $controller->userVoucherRedeemConfirm($req2);

        $this->assertEqualsWithDelta(50, $this->balanceOf($wallet), 0.00001, 'replay must NOT credit a second time');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'redeem_voucher')->count(), 'no duplicate ledger row');
    }

    public function test_voucher_create_double_submit_debits_and_mints_exactly_once(): void
    {
        $currency = $this->makeCurrency();
        $user     = $this->makeUser();
        $wallet   = $this->makeWallet($user, 'USER', $currency, 100);

        $action = $this->makeUserAction($user->id, 'USER', 'create_voucher', [
            'wallet_id'   => $wallet->id,
            'amount'      => 10,
            'totalAmount' => 10,
            'totalCharge' => 0,
            'done_route'  => route('user.voucher.create.done'),
        ]);

        $this->actingAs($user);
        session(['action_id' => $action->id]);
        $controller = new VoucherController();

        $controller->userVoucherCreateDone();

        $this->assertEqualsWithDelta(90, $this->balanceOf($wallet), 0.00001, 'first create should debit once');
        $this->assertSame(1, Voucher::where('user_id', $user->id)->count(), 'exactly one voucher minted');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'create_voucher')->count());
        $this->assertNull(\App\Models\UserAction::find($action->id), 'one-shot action should be consumed');

        // Replay: session still points at the (now deleted) action.
        $controller->userVoucherCreateDone();

        $this->assertEqualsWithDelta(90, $this->balanceOf($wallet), 0.00001, 'replay must NOT debit again');
        $this->assertSame(1, Voucher::where('user_id', $user->id)->count(), 'no second voucher minted');
    }
}
