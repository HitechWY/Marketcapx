<?php

namespace App\Http\Middleware;

use App\Models\ModuleSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia request handler (registered on the `web` group only — see bootstrap/app.php).
 * Inert for ordinary Blade responses: it only shapes responses carrying the X-Inertia
 * header, so existing Blade pages are unaffected.
 *
 * F2a adds authenticated-user shared props as LAZY closures, so they evaluate ONLY when
 * an Inertia response is serialized (never on Blade pages or for guests where they return
 * null/empty). Only the curated, non-sensitive subset is exposed (see userProps()).
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root Blade template that boots the Inertia app.
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every Inertia response.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            // Lazy: only evaluated when an Inertia response is built.
            'auth'    => [
                'user' => fn () => $this->userProps($request),
            ],
            'flash'   => [
                // The existing session('notify') tuples ([status, message]); rendered by useFlash().
                'notify' => fn () => session('notify', []),
            ],
            'modules' => fn () => $this->userModules($request),
            'nav'     => fn () => $this->userNav($request),
            'locale'  => app()->getLocale(),
        ];
    }

    /**
     * Curated, non-sensitive snapshot of the authenticated WEB-guard user (null otherwise).
     * Never exposes password/remember_token/ver_code/balance/kyc_data (all in $hidden).
     *
     * @return array<string, mixed>|null
     */
    protected function userProps(Request $request): ?array
    {
        $user = $request->user(); // web guard
        if (!$user) {
            return null;
        }

        return [
            'id'       => $user->id,
            'username' => $user->username,
            'fullname' => $user->fullname,
            'email'    => $user->email,
            'image'    => $user->image ? getImage(getFilePath('userProfile') . '/' . $user->image) : null,
            'kv'       => (int) $user->kv, // KYC status
            'ts'       => (int) $user->ts, // 2FA status
        ];
    }

    /**
     * Enabled feature-module flags for the USER type (booleans only).
     *
     * @return array<string, bool>
     */
    protected function userModules(Request $request): array
    {
        if (!$request->user()) {
            return [];
        }

        return ModuleSetting::where('user_type', 'USER')
            ->pluck('status', 'slug')
            ->map(fn ($status) => (bool) $status)
            ->all();
    }

    /**
     * Server-resolved navigation for the user shell. Money-movement links are emitted as
     * plain resolved URLs here (not via client Ziggy) so their route names stay out of the
     * page's Ziggy table. `actions` items are gated by the enabled modules.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    protected function userNav(Request $request): array
    {
        $empty = ['primary' => [], 'actions' => [], 'account' => []];
        if (!$request->user()) {
            return $empty;
        }

        $modules = $this->userModules($request);
        $on      = fn (string $key) => $modules[$key] ?? false;

        $actions = array_values(array_filter([
            $on('add_money')      ? ['label' => 'Add Money', 'url' => route('user.deposit.index'), 'icon' => 'add-money'] : null,
            $on('withdraw_money') ? ['label' => 'Withdraw', 'url' => route('user.withdraw'), 'icon' => 'withdraw'] : null,
            $on('transfer_money') ? ['label' => 'Transfer', 'url' => route('user.transfer'), 'icon' => 'transfer'] : null,
            $on('money_out')      ? ['label' => 'Money Out', 'url' => route('user.money.out'), 'icon' => 'money-out'] : null,
            $on('make_payment')   ? ['label' => 'Make Payment', 'url' => route('user.payment'), 'icon' => 'payment'] : null,
            $on('money_exchange') ? ['label' => 'Exchange', 'url' => route('user.exchange.money'), 'icon' => 'exchange'] : null,
            $on('request_money')  ? ['label' => 'Request Money', 'url' => route('user.request.money'), 'icon' => 'request'] : null,
            $on('create_invoice') ? ['label' => 'Invoice', 'url' => route('user.invoice.all'), 'icon' => 'invoice'] : null,
            $on('create_voucher') ? ['label' => 'Voucher', 'url' => route('user.voucher.list'), 'icon' => 'voucher'] : null,
            $on('escrow')         ? ['label' => 'Escrow', 'url' => route('user.escrow.index'), 'icon' => 'escrow'] : null,
        ]));

        return [
            'primary' => [
                ['label' => 'Dashboard', 'url' => route('user.home'), 'icon' => 'dashboard'],
                ['label' => 'Transactions', 'url' => route('user.transactions'), 'icon' => 'history'],
                ['label' => 'Wallets', 'url' => route('user.wallets'), 'icon' => 'wallet'],
                ['label' => 'Deposit History', 'url' => route('user.deposit.history'), 'icon' => 'receipt'],
            ],
            'actions' => $actions,
            'account' => [
                ['label' => 'Profile', 'url' => route('user.profile.setting'), 'icon' => 'profile'],
                ['label' => 'Change Password', 'url' => route('user.change.password'), 'icon' => 'lock'],
                ['label' => '2FA Security', 'url' => route('user.twofactor'), 'icon' => 'shield'],
                ['label' => 'My QR Code', 'url' => route('user.qr.code'), 'icon' => 'qr'],
                ['label' => 'Logout', 'url' => route('user.logout'), 'icon' => 'logout'],
            ],
        ];
    }
}
