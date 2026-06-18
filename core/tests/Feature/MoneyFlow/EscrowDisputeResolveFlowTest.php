<?php

namespace Tests\Feature\MoneyFlow;

use App\Constants\Status;
use App\Http\Controllers\Admin\EscrowController;
use App\Models\Escrow;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Admin dispute resolution (split a disputed escrow's funds between buyer and
 * seller).
 *
 * Risks:
 *  - F2: action() credited BOTH wallets with no DB transaction / row lock,
 *    guarded only by a disputed() read outside any lock -> a concurrent/double
 *    resolve could double-pay. After hardening, a single resolve pays each side
 *    exactly once and a replay makes no further change.
 *  - F8: the recorded dispute charge used `paid_amount - buyer + seller`
 *    (operator precedence) instead of the leftover held funds
 *    `paid_amount - (buyer + seller)`. This only sets the recorded
 *    `dispute_charge` field (not the payouts), so correcting it changes no money
 *    moved to users.
 */
class EscrowDisputeResolveFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_admin_dispute_resolution_pays_both_once_and_records_correct_charge(): void
    {
        $currency     = $this->makeCurrency();
        $buyer        = $this->makeUser();
        $seller       = $this->makeUser();
        $buyerWallet  = $this->makeWallet($buyer, 'USER', $currency, 0);
        $sellerWallet = $this->makeWallet($seller, 'USER', $currency, 0);

        $escrow = $this->makeEscrow([
            'buyer_id'    => $buyer->id,
            'seller_id'   => $seller->id,
            'creator_id'  => $buyer->id,
            'currency_id' => $currency->id,
            'amount'      => 100,
            'paid_amount' => 100,
            'status'      => Status::ESCROW_DISPUTED,
            'title'       => 'Dispute test',
        ]);
        $this->makeConversation($escrow);

        $controller = new EscrowController();
        $request    = Request::create('/admin/escrow/action', 'POST', [
            'escrow_id'     => $escrow->id,
            'buyer_amount'  => 30,
            'seller_amount' => 60,
            'status'        => Status::ESCROW_COMPLETED,
        ]);

        $controller->action($request);

        $this->assertEqualsWithDelta(30, $this->balanceOf($buyerWallet), 0.00001, 'buyer refunded 30 once');
        $this->assertEqualsWithDelta(60, $this->balanceOf($sellerWallet), 0.00001, 'seller paid 60 once');
        $this->assertSame(Status::ESCROW_COMPLETED, (int) Escrow::whereKey($escrow->id)->value('status'), 'escrow resolved once');
        // F8: dispute charge = leftover held funds = 100 - (30 + 60) = 10 (not 130).
        $this->assertEqualsWithDelta(10, (float) Escrow::whereKey($escrow->id)->value('dispute_charge'), 0.00001, 'dispute charge = paid_amount - (buyer + seller)');
        $this->assertSame(1, Transaction::where('wallet_id', $buyerWallet->id)->where('remark', 'escrow_amount_refunded')->count(), 'one buyer ledger row');
        $this->assertSame(1, Transaction::where('wallet_id', $sellerWallet->id)->where('remark', 'escrow_amount_received')->count(), 'one seller ledger row');

        // Replay: escrow is no longer DISPUTED -> rejected/idempotent, no double-pay.
        try {
            $controller->action($request);
        } catch (\Throwable $e) {
            // A rejection (e.g. ModelNotFoundException on the status-scoped lookup) is acceptable.
        }

        $this->assertEqualsWithDelta(30, $this->balanceOf($buyerWallet), 0.00001, 'replay must not double-pay the buyer');
        $this->assertEqualsWithDelta(60, $this->balanceOf($sellerWallet), 0.00001, 'replay must not double-pay the seller');
        $this->assertSame(1, Transaction::where('wallet_id', $sellerWallet->id)->where('remark', 'escrow_amount_received')->count(), 'still one seller ledger row after replay');
    }
}
