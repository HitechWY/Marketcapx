<?php

namespace App\Support;

/**
 * RBAC permission catalog (F9) — the single source of truth for permission slugs.
 *
 * RbacSeeder upserts exactly these into the `permissions` table and grants them all
 * to the `super-admin` role. The `permission` middleware (F9e) may only reference
 * slugs that exist here; a drift test asserts catalog↔route consistency.
 *
 * Slugs mirror the admin route groups (see routes/admin.php / ADMIN-MUTATION-CONTRACT-AUDIT.md).
 * Per-account-type actions are namespaced per guard (users/agents/merchants) so a role
 * can, e.g., manage users but not merchants.
 */
class PermissionCatalog
{
    /** Account guards that share the same management action set. */
    private const GUARDS = ['users', 'agents', 'merchants'];

    /** Per-guard actions => label suffix. */
    private const GUARD_ACTIONS = [
        'view'           => 'View',
        'status.update'  => 'Block/Unblock',
        'update'         => 'Edit profile',
        'balance.adjust' => 'Adjust balance',
        'login'          => 'Impersonate (login-as)',
        'notify'         => 'Send notification',
        'kyc.review'     => 'Review KYC documents',
        'kyc.approve'    => 'Approve KYC',
        'kyc.reject'     => 'Reject KYC',
    ];

    /** Non-guard permissions: slug => [name, group]. */
    private const FLAT = [
        'admin.dashboard.view'              => ['View dashboard', 'dashboard'],
        'admin.reports.view'                => ['View reports', 'reports'],

        'admin.deposits.view'               => ['View deposits', 'finance'],
        'admin.deposits.approve'            => ['Approve deposit', 'finance'],
        'admin.deposits.reject'             => ['Reject deposit', 'finance'],

        'admin.withdrawals.view'            => ['View withdrawals', 'finance'],
        'admin.withdrawals.approve'         => ['Approve withdrawal', 'finance'],
        'admin.withdrawals.reject'          => ['Reject withdrawal', 'finance'],
        'admin.withdrawals.methods.manage'  => ['Manage withdrawal methods', 'finance'],

        'admin.escrow.view'                 => ['View escrow', 'finance'],
        'admin.escrow.action'               => ['Resolve escrow (fund movement)', 'finance'],

        'admin.charges.update'              => ['Update transaction charges', 'finance'],
        'admin.currency.manage'             => ['Manage currencies', 'settings'],

        'admin.gateway.view'                => ['View gateways', 'gateway'],
        'admin.gateway.update'              => ['Update gateway config/secrets', 'gateway'],
        'admin.gateway.status'              => ['Toggle gateway status', 'gateway'],

        'admin.settings.view'               => ['View settings', 'settings'],
        'admin.settings.update'             => ['Update general/system settings', 'settings'],
        'admin.settings.notifications.update' => ['Update notification settings/templates', 'settings'],
        'admin.settings.socialite.update'   => ['Update social login credentials', 'settings'],
        'admin.kyc.settings.update'         => ['Update KYC form settings', 'settings'],

        'admin.support.view'                => ['View support tickets', 'support'],
        'admin.support.reply'               => ['Reply to ticket', 'support'],
        'admin.support.close'               => ['Close ticket', 'support'],
        'admin.support.delete'              => ['Delete ticket', 'support'],

        'admin.content.frontend.manage'     => ['Manage frontend/CMS content', 'content'],
        'admin.content.pages.manage'        => ['Manage pages (page builder)', 'content'],
        'admin.language.manage'             => ['Manage languages', 'content'],
        'admin.category.manage'             => ['Manage categories', 'content'],
        'admin.subscriber.manage'           => ['Manage subscribers', 'content'],

        'admin.system.cron.manage'          => ['Manage cron/schedules', 'system'],
        'admin.system.optimize'             => ['Run optimize/clear cache', 'system'],
        'admin.system.update'               => ['Run system update', 'system'],
        'admin.system.extensions.manage'    => ['Manage extensions', 'system'],
        'admin.system.plugins.manage'       => ['Manage plugins', 'system'],
        'admin.module.update'               => ['Toggle feature modules', 'system'],

        'admin.roles.view'                  => ['View roles', 'roles'],
        'admin.roles.manage'                => ['Manage roles/permissions', 'roles'],
    ];

    /**
     * Full catalog as a list of ['slug' => ..., 'name' => ..., 'group' => ...].
     *
     * @return array<int, array{slug:string, name:string, group:string}>
     */
    public static function all(): array
    {
        $out = [];

        foreach (self::GUARDS as $guard) {
            $label = ucfirst($guard);
            foreach (self::GUARD_ACTIONS as $action => $desc) {
                $out[] = [
                    'slug'  => "admin.$guard.$action",
                    'name'  => "$label — $desc",
                    'group' => $action === 'view' ? 'users'
                        : (str_starts_with($action, 'kyc') ? 'kyc'
                        : ($action === 'balance.adjust' ? 'finance' : 'users')),
                ];
            }
        }

        foreach (self::FLAT as $slug => [$name, $group]) {
            $out[] = ['slug' => $slug, 'name' => $name, 'group' => $group];
        }

        return $out;
    }

    /**
     * All permission slugs.
     *
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_map(static fn ($p) => $p['slug'], self::all());
    }

    public static function has(string $slug): bool
    {
        return in_array($slug, self::slugs(), true);
    }
}
