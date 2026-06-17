<?php

namespace Tests\Concerns;

use App\Models\Agent;
use App\Models\Currency;
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

    protected function balanceOf(Wallet $wallet)
    {
        return (float) Wallet::whereKey($wallet->id)->value('balance');
    }
}
