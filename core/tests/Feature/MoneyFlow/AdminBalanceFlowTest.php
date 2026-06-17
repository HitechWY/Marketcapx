<?php

namespace Tests\Feature\MoneyFlow;

use App\Http\Controllers\Admin\ManageGuardController;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * Coverage for admin add/subtract balance: correct ledger entries and a
 * fail-safe (no mutation, no ledger row) on insufficient balance.
 *
 * The controller normally derives its model/type from the route action in its
 * constructor. To exercise the action logic in isolation (no admin auth/route
 * middleware), we build it without the constructor and set those props directly,
 * then call addSubBalance() with a hand-built request.
 */
class AdminBalanceFlowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    private function userBalanceController(): ManageGuardController
    {
        $ref        = new ReflectionClass(ManageGuardController::class);
        $controller = $ref->newInstanceWithoutConstructor();
        foreach (['type' => 'USER', 'model' => 'App\\Models\\User', 'guardType' => 'users'] as $prop => $val) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($controller, $val);
        }

        return $controller;
    }

    private function request(int $walletId, string $act, $amount): Request
    {
        return Request::create('/admin/users/add-sub-balance', 'POST', [
            'amount'    => $amount,
            'act'       => $act,
            'remark'    => 'test ' . $act,
            'wallet_id' => $walletId,
        ]);
    }

    public function test_admin_add_balance_records_one_ledger_entry(): void
    {
        $currency = $this->makeCurrency();
        $user     = $this->makeUser();
        $wallet   = $this->makeWallet($user, 'USER', $currency, 100);

        $this->userBalanceController()->addSubBalance($this->request($wallet->id, 'add', 25), $user->id);

        $this->assertEqualsWithDelta(125, $this->balanceOf($wallet), 0.00001, 'balance increased by 25');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'balance_add')->count());
    }

    public function test_admin_subtract_balance_records_one_ledger_entry(): void
    {
        $currency = $this->makeCurrency();
        $user     = $this->makeUser();
        $wallet   = $this->makeWallet($user, 'USER', $currency, 100);

        $this->userBalanceController()->addSubBalance($this->request($wallet->id, 'sub', 40), $user->id);

        $this->assertEqualsWithDelta(60, $this->balanceOf($wallet), 0.00001, 'balance reduced by 40');
        $this->assertSame(1, Transaction::where('wallet_id', $wallet->id)->where('remark', 'balance_subtract')->count());
    }

    public function test_admin_subtract_more_than_balance_fails_safely(): void
    {
        $currency = $this->makeCurrency();
        $user     = $this->makeUser();
        $wallet   = $this->makeWallet($user, 'USER', $currency, 30);

        $this->userBalanceController()->addSubBalance($this->request($wallet->id, 'sub', 100), $user->id);

        $this->assertEqualsWithDelta(30, $this->balanceOf($wallet), 0.00001, 'balance unchanged on insufficient funds');
        $this->assertSame(0, Transaction::where('wallet_id', $wallet->id)->where('remark', 'balance_subtract')->count(), 'no ledger row written');
    }
}
