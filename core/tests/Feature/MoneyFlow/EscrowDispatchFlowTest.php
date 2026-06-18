<?php

namespace Tests\Feature\MoneyFlow;

use App\Constants\Status;
use App\Http\Controllers\User\EscrowController;
use App\Models\Escrow;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Escrow release (buyer dispatches the held funds to the seller).
 *
 * Risk (audit F1): dispatchEscrow() credited the seller and deducted the
 * seller charge with no DB transaction / row lock, guarded only by an
 * accepted() read outside any lock — so a concurrent/duplicate dispatch could
 * double-credit the seller, and a mid-sequence failure could leave the escrow
 * "completed" but the seller unpaid. After hardening, a single dispatch credits
 * exactly once and a replay makes no further change.
 */
class EscrowDispatchFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_escrow_dispatch_credits_seller_once_and_completes(): void
    {
        $currency     = $this->makeCurrency();
        $buyer        = $this->makeUser();
        $seller       = $this->makeUser();
        $sellerWallet = $this->makeWallet($seller, 'USER', $currency, 0);

        $escrow = $this->makeEscrow([
            'buyer_id'      => $buyer->id,
            'seller_id'     => $seller->id,
            'creator_id'    => $buyer->id,
            'currency_id'   => $currency->id,
            'amount'        => 50,
            'charge'        => 5,
            'seller_charge' => 5,
            'paid_amount'   => 50,
            'status'        => Status::ESCROW_ACCEPTED,
            'title'         => 'Dispatch test',
        ]);

        $this->actingAs($buyer);
        $controller = new EscrowController();
        $controller->dispatchEscrow($escrow->id);

        $this->assertSame(Status::ESCROW_COMPLETED, (int) Escrow::whereKey($escrow->id)->value('status'), 'escrow completed once');
        $this->assertEqualsWithDelta(45, $this->balanceOf($sellerWallet), 0.00001, 'seller credited amount (50) minus seller_charge (5)');
        $this->assertSame(1, Transaction::where('wallet_id', $sellerWallet->id)->where('remark', 'escrow_payment_dispatched')->count(), 'one dispatch ledger row');
        $this->assertSame(1, Transaction::where('wallet_id', $sellerWallet->id)->where('remark', 'escrow_charge')->count(), 'one charge ledger row');

        // Replay: escrow is no longer ACCEPTED -> rejected/idempotent, no second credit.
        try {
            $controller->dispatchEscrow($escrow->id);
        } catch (\Throwable $e) {
            // A rejection (e.g. ModelNotFoundException on the status-scoped lookup) is acceptable.
        }

        $this->assertEqualsWithDelta(45, $this->balanceOf($sellerWallet), 0.00001, 'replay must not credit the seller again');
        $this->assertSame(1, Transaction::where('wallet_id', $sellerWallet->id)->where('remark', 'escrow_payment_dispatched')->count(), 'still one dispatch ledger row after replay');
    }
}
