<?php

namespace App\Http\Middleware;

use App\Models\ModuleSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

/**
 * Inertia request handler (registered on the `web` group only — see bootstrap/app.php).
 * Inert for ordinary Blade responses (only shapes X-Inertia responses).
 *
 * Shared props are GUARD-AWARE (F3): they resolve the active panel via userGuard()
 * (web/user, agent, merchant) so the user/merchant/agent Inertia surfaces each get the
 * right curated user, module flags, and navigation. All lazy — evaluated only on Inertia
 * responses; null/empty for guests and the admin guard (out of scope). Only a curated,
 * non-sensitive subset is exposed (never password/secret/API-key fields).
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth'    => [
                'user' => fn () => $this->userProps(),
            ],
            'flash'   => [
                'notify' => fn () => session('notify', []),
            ],
            'modules' => fn () => $this->panelModules(),
            'nav'     => fn () => $this->panelNav(),
            'locale'  => app()->getLocale(),
        ];
    }

    /**
     * Curated snapshot of the active panel user (web/agent/merchant), or null.
     *
     * @return array<string, mixed>|null
     */
    protected function userProps(): ?array
    {
        $guard = userGuard();
        $user  = $guard['user'] ?? null;
        if (!$user) {
            return null;
        }

        return [
            'id'       => $user->id,
            'username' => $user->username,
            'fullname' => $user->fullname,
            'email'    => $user->email,
            'image'    => $this->userImage($user, $guard['type']),
            'kv'       => (int) $user->kv,
            'ts'       => (int) $user->ts,
            'type'     => $guard['type'], // USER | AGENT | MERCHANT
        ];
    }

    protected function userImage($user, ?string $type): ?string
    {
        if (empty($user->image)) {
            return null;
        }
        $key = match ($type) {
            'AGENT'    => 'agentProfile',
            'MERCHANT' => 'merchantProfile',
            default    => 'userProfile',
        };
        try {
            return getImage(getFilePath($key) . '/' . $user->image);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string, bool>
     */
    protected function panelModules(): array
    {
        $type = userGuard()['type'] ?? null;
        if (!$type) {
            return [];
        }

        return ModuleSetting::where('user_type', $type)
            ->pluck('status', 'slug')
            ->map(fn ($status) => (bool) $status)
            ->all();
    }

    /**
     * Server-resolved navigation for the active panel. Route names are emitted as
     * resolved URLs (kept out of the client Ziggy table). Each entry is guarded by
     * Route::has() so a panel only ever links to routes that exist.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    protected function panelNav(): array
    {
        $type = userGuard()['type'] ?? null;
        $empty = ['primary' => [], 'actions' => [], 'account' => []];
        if (!$type) {
            return $empty;
        }

        $modules = $this->panelModules();
        $on      = fn (string $key) => $modules[$key] ?? false;
        $item    = fn (string $name, string $label, string $icon, array $params = []) => Route::has($name)
            ? ['label' => $label, 'url' => route($name, $params), 'icon' => $icon]
            : null;
        $clean   = fn (array $items) => array_values(array_filter($items));

        if ($type === 'MERCHANT') {
            return [
                'primary' => $clean([
                    $item('merchant.home', 'Dashboard', 'dashboard'),
                    $item('merchant.transactions', 'Transactions', 'history'),
                    $item('merchant.wallets', 'Wallets', 'wallet'),
                    $item('merchant.withdraw.history', 'Withdraw History', 'receipt'),
                ]),
                'actions' => $clean([
                    $item('merchant.withdraw', 'Withdraw', 'withdraw'),
                ]),
                'account' => $clean([
                    $item('merchant.profile.setting', 'Profile', 'profile'),
                    $item('merchant.change.password', 'Change Password', 'lock'),
                    $item('merchant.twofactor', '2FA Security', 'shield'),
                    $item('merchant.qr.code', 'My QR Code', 'qr'),
                    $item('merchant.api.key', 'API Key', 'lock'),
                    $item('merchant.logout', 'Logout', 'logout'),
                ]),
            ];
        }

        if ($type === 'AGENT') {
            return [
                'primary' => $clean([
                    $item('agent.home', 'Dashboard', 'dashboard'),
                    $item('agent.transactions', 'Transactions', 'history'),
                    $item('agent.commission.log', 'Commission Log', 'invoice'),
                    $item('agent.wallets', 'Wallets', 'wallet'),
                ]),
                'actions' => $clean([
                    $on('money_in') ? $item('agent.money.in', 'Money In', 'add-money') : null,
                    $on('add_money') ? $item('agent.deposit.history', 'Add Money', 'add-money') : null,
                    $item('agent.withdraw', 'Withdraw', 'withdraw'),
                ]),
                'account' => $clean([
                    $item('agent.profile.setting', 'Profile', 'profile'),
                    $item('agent.change.password', 'Change Password', 'lock'),
                    $item('agent.twofactor', '2FA Security', 'shield'),
                    $item('agent.qr.code', 'My QR Code', 'qr'),
                    $item('agent.logout', 'Logout', 'logout'),
                ]),
            ];
        }

        // USER (web guard)
        return [
            'primary' => $clean([
                $item('user.home', 'Dashboard', 'dashboard'),
                $item('user.transactions', 'Transactions', 'history'),
                $item('user.wallets', 'Wallets', 'wallet'),
                $item('user.deposit.history', 'Deposit History', 'receipt'),
            ]),
            'actions' => $clean([
                $on('add_money') ? $item('user.deposit.index', 'Add Money', 'add-money') : null,
                $on('withdraw_money') ? $item('user.withdraw', 'Withdraw', 'withdraw') : null,
                $on('transfer_money') ? $item('user.transfer', 'Transfer', 'transfer') : null,
                $on('money_out') ? $item('user.money.out', 'Money Out', 'money-out') : null,
                $on('make_payment') ? $item('user.payment', 'Make Payment', 'payment') : null,
                $on('money_exchange') ? $item('user.exchange.money', 'Exchange', 'exchange') : null,
                $on('request_money') ? $item('user.request.money', 'Request Money', 'request') : null,
                $on('create_invoice') ? $item('user.invoice.all', 'Invoice', 'invoice') : null,
                $on('create_voucher') ? $item('user.voucher.list', 'Voucher', 'voucher') : null,
                $on('escrow') ? $item('user.escrow.index', 'Escrow', 'escrow') : null,
            ]),
            'account' => $clean([
                $item('user.profile.setting', 'Profile', 'profile'),
                $item('user.change.password', 'Change Password', 'lock'),
                $item('user.twofactor', '2FA Security', 'shield'),
                $item('user.qr.code', 'My QR Code', 'qr'),
                $item('user.logout', 'Logout', 'logout'),
            ]),
        ];
    }
}
