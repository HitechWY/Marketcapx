<?php

namespace Tests\Concerns;

use App\Constants\Status;
use App\Models\Agent;
use App\Models\ApiPayment;
use App\Models\Conversation;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Escrow;
use App\Models\GatewayCurrency;
use App\Models\Merchant;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserAction;
use App\Models\Voucher;
use App\Models\Wallet;
use App\Models\WithdrawMethod;
use App\Models\Withdrawal;

/**
 * Disposable fixtures for money-flow tests. Rows are created with direct
 * attribute assignment (same as the controllers) so model mass-assignment
 * guards never get in the way. Everything is rolled back by DatabaseTransactions
 * after each test; nothing persists in marketcapx_test.
 */
trait CreatesMoneyFixtures
{
    protected int $fixtureSeq = 0;

    protected function uniq(string $prefix): string
    {
        $this->fixtureSeq++;
        // Date/random helpers are avoided in this codebase's tooling; use a
        // per-test counter plus the spl object id for uniqueness.
        return $prefix . spl_object_id($this) . '_' . $this->fixtureSeq;
    }

    protected function makeCurrency(array $overrides = []): Currency
    {
        $c = new Currency();
        $c->currency_code     = $overrides['currency_code'] ?? strtoupper($this->uniq('C'));
        $c->currency_symbol   = $overrides['currency_symbol'] ?? '$';
        $c->currency_fullname = $overrides['currency_fullname'] ?? 'Test Currency';
        $c->currency_type     = $overrides['currency_type'] ?? 1; // 1 = fiat
        $c->rate              = $overrides['rate'] ?? 1;
        $c->is_default        = $overrides['is_default'] ?? 0;
        $c->status            = $overrides['status'] ?? 1; // checkWallet requires active
        $c->save();

        return $c;
    }

    protected function makeUser(): User
    {
        $u = new User();
        $u->firstname = 'Test';
        $u->lastname  = 'User';
        $u->username  = $this->uniq('user');
        $u->email     = $this->uniq('user') . '@example.test';
        $u->password  = bcrypt('password');
        $u->status    = 1;
        $u->save();

        return $u;
    }

    protected function makeAgent(): Agent
    {
        $a = new Agent();
        $a->firstname = 'Test';
        $a->lastname  = 'Agent';
        $a->username  = $this->uniq('agent');
        $a->email     = $this->uniq('agent') . '@example.test';
        $a->password  = bcrypt('password');
        $a->status    = 1;
        $a->save();

        return $a;
    }

    protected function makeMerchant(): Merchant
    {
        $m = new Merchant();
        $m->firstname = 'Test';
        $m->lastname  = 'Merchant';
        $m->username  = $this->uniq('merchant');
        $m->email     = $this->uniq('merchant') . '@example.test';
        $m->password  = bcrypt('password');
        $m->status    = 1;
        $m->save();

        return $m;
    }

    /**
     * @param  User|Agent|Merchant  $owner
     */
    protected function makeWallet($owner, string $type, Currency $currency, $balance = 0): Wallet
    {
        $w = new Wallet();
        $w->user_id       = $owner->id;
        $w->user_type     = $type;
        $w->currency_id   = $currency->id;
        $w->currency_code = $currency->currency_code;
        $w->balance       = $balance;
        $w->save();

        return $w;
    }

    protected function makeUserAction(int $ownerId, string $type, string $act, array $details, bool $isApi = false): UserAction
    {
        $a = new UserAction();
        $a->user_id    = $ownerId;
        $a->user_type  = $type;
        $a->act        = $act;
        $a->details    = $details; // cast to object
        $a->is_api     = $isApi ? 1 : 0;
        $a->is_otp     = 0;
        $a->used       = 0;
        $a->send_at    = now();
        $a->used_at    = now();
        $a->expired_at = now()->addHour();
        $a->save();

        return $a;
    }

    protected function makeVoucher(User $owner, Currency $currency, $amount): Voucher
    {
        $v = new Voucher();
        $v->user_id      = $owner->id;
        $v->user_type    = 'USER';
        $v->currency_id  = $currency->id;
        $v->amount       = $amount;
        $v->voucher_code = $this->uniq('VC');
        $v->is_used      = 0;
        $v->save();

        return $v;
    }

    protected function makeAdmin(): Admin
    {
        $a = new Admin();
        $a->name     = 'Test Admin';
        $a->username = $this->uniq('admin');
        $a->email    = $this->uniq('admin') . '@example.test';
        $a->password = bcrypt('password');
        $a->save();

        return $a;
    }

    protected function makeWithdrawMethod(): WithdrawMethod
    {
        $m = new WithdrawMethod();
        $m->name        = 'Test Method';
        $m->min_limit   = 0;
        $m->max_limit   = 1000000;
        $m->fixed_charge = 0;
        $m->rate        = 1;
        $m->status      = 1;
        $m->save();

        return $m;
    }

    protected function makeWithdrawal(User $user, Wallet $wallet, WithdrawMethod $method, $amount, int $status): Withdrawal
    {
        $w = new Withdrawal();
        $w->method_id    = $method->id;
        $w->user_id      = $user->id;
        $w->user_type    = 'USER';
        $w->wallet_id    = $wallet->id;
        $w->currency_id  = $wallet->currency_id;
        $w->currency     = $wallet->currency_code;
        $w->amount       = $amount;
        $w->charge       = 0;
        $w->rate         = 1;
        $w->final_amount = $amount;
        $w->after_charge = $amount;
        $w->trx          = $this->uniq('WD');
        $w->status       = $status;
        $w->save();

        return $w;
    }

    protected function makeGatewayCurrency(int $methodCode, string $currencyCode, string $name = 'Test Gateway'): GatewayCurrency
    {
        $gc = new GatewayCurrency();
        $gc->name          = $name;
        $gc->method_code   = $methodCode;
        $gc->currency      = $currencyCode;
        $gc->symbol        = '$';
        $gc->gateway_alias = 'test';
        $gc->min_amount    = 0;
        $gc->max_amount    = 1000000;
        $gc->rate          = 1;
        $gc->save();

        return $gc;
    }

    protected function makeDeposit(User $user, Wallet $wallet, Currency $currency, $amount, int $methodCode, int $status): Deposit
    {
        $d = new Deposit();
        $d->user_id         = $user->id;
        $d->user_type       = 'USER';
        $d->method_code     = $methodCode;
        $d->method_currency = $currency->currency_code;
        $d->amount          = $amount;
        $d->wallet_id       = $wallet->id;
        $d->currency_id     = $currency->id;
        $d->charge          = 0;
        $d->rate            = 1;
        $d->final_amount    = $amount;
        $d->btc_amount      = 0;
        $d->btc_wallet      = '';
        $d->trx             = $this->uniq('DEP');
        $d->status          = $status;
        $d->save();

        return $d;
    }

    /**
     * Minimal escrow row for escrow-flow tests. Defaults to an accepted escrow
     * fully funded (paid_amount == amount); pass overrides for status/amounts.
     */
    protected function makeEscrow(array $overrides = []): Escrow
    {
        $e = new Escrow();
        $e->escrow_number = $this->uniq('ESC');
        $e->buyer_id      = $overrides['buyer_id'] ?? 0;
        $e->seller_id     = $overrides['seller_id'] ?? 0;
        $e->creator_id    = $overrides['creator_id'] ?? ($overrides['buyer_id'] ?? 0);
        $e->currency_id   = $overrides['currency_id'] ?? 0;
        $e->amount        = $overrides['amount'] ?? 0;
        $e->charge        = $overrides['charge'] ?? 0;
        $e->buyer_charge  = $overrides['buyer_charge'] ?? 0;
        $e->seller_charge = $overrides['seller_charge'] ?? 0;
        $e->paid_amount   = $overrides['paid_amount'] ?? ($overrides['amount'] ?? 0);
        $e->title         = $overrides['title'] ?? 'Test Escrow';
        $e->status        = $overrides['status'] ?? Status::ESCROW_ACCEPTED;
        $e->save();

        return $e;
    }

    /**
     * Hosted (api_payments) checkout row for replay-safety tests. Defaults to an
     * already-settled payment (status 1) so getPaymentInfo() returns its error
     * array; pass status:0 for a live, confirmable payment.
     */
    protected function makeApiPayment(array $overrides = []): ApiPayment
    {
        $p = new ApiPayment();
        $p->trx         = $overrides['trx'] ?? $this->uniq('TRX');
        $p->merchant_id = $overrides['merchant_id'] ?? 0;
        $p->payer_id    = $overrides['payer_id'] ?? 0;
        $p->currency_id = $overrides['currency_id'] ?? 0;
        $p->identifier  = $overrides['identifier'] ?? $this->uniq('ID');
        $p->amount      = $overrides['amount'] ?? 0;
        $p->ipn_url     = $overrides['ipn_url'] ?? 'https://merchant.test/ipn';
        $p->cancel_url  = $overrides['cancel_url'] ?? 'https://merchant.test/cancel';
        $p->success_url = $overrides['success_url'] ?? 'https://merchant.test/success';
        $p->ver_code    = $overrides['ver_code'] ?? null;
        $p->ver_code_at = $overrides['ver_code_at'] ?? null;
        $p->status      = $overrides['status'] ?? 1; // 1 = already settled
        $p->save();

        return $p;
    }

    protected function makeConversation(Escrow $escrow, array $overrides = []): Conversation
    {
        $c            = new Conversation();
        $c->escrow_id = $escrow->id;
        $c->buyer_id  = $escrow->buyer_id;
        $c->seller_id = $escrow->seller_id;
        $c->status    = $overrides['status'] ?? Status::YES;
        $c->save();

        return $c;
    }

    protected function balanceOf(Wallet $wallet)
    {
        return (float) Wallet::whereKey($wallet->id)->value('balance');
    }
}
