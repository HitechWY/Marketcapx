<?php

namespace Tests\Feature\MoneyFlow;

use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Atomicity guarantee that every hardened money flow relies on: a wallet
 * mutation + ledger insert performed inside DB::transaction is fully rolled back
 * if anything later in the transaction throws -- no partial balance change, no
 * orphaned ledger row. Exercises the real WalletService::credit primitive.
 */
class WalletAtomicityTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_exception_after_credit_rolls_back_balance_and_ledger(): void
    {
        $currency = $this->makeCurrency();
        $user     = $this->makeUser();
        $wallet   = $this->makeWallet($user, 'USER', $currency, 100);

        $trx = $this->uniq('ROLLBACK');

        try {
            DB::transaction(function () use ($wallet, $user, $trx) {
                app(WalletService::class)->credit($wallet->id, 50, [
                    'user_id'     => $user->id,
                    'user_type'   => 'USER',
                    'currency_id' => $wallet->currency_id,
                    'trx'         => $trx,
                    'remark'      => 'rollback_probe',
                ]);

                // Simulate a failure later in the same transaction (e.g. the
                // second wallet update or a ledger insert blowing up).
                throw new RuntimeException('forced failure mid-transaction');
            });
            $this->fail('the transaction should have thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('forced failure mid-transaction', $e->getMessage());
        }

        $this->assertEqualsWithDelta(100, $this->balanceOf($wallet), 0.00001, 'balance must be unchanged after rollback');
        $this->assertSame(0, Transaction::where('trx', $trx)->count(), 'no ledger row should survive rollback');
    }
}
