<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectIfNotAdmin;
use App\Models\Admin;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F8a coverage, evolving with F9: the admin authorization model.
 *
 * F8a locked in the flat-admin baseline. F9 deliberately introduces RBAC structure,
 * so these assertions are updated phase-by-phase (this is intended drift, not
 * accidental):
 *  - F9b: the Admin model now HAS a role() relation + hasPermission() — asserted by
 *    test_admin_model_exposes_rbac_api below (replaces the old "no relation" guard).
 *  - Still true in F9b (updated later): high-risk routes carry only the `admin`
 *    guard (until F9e applies `permission:<slug>`), and no `permission` middleware
 *    alias is registered (until F9d).
 *
 * Route-collection + reflection inspection only (no HTTP dispatch, no DB), so safe
 * to run without a seeded test database.
 */
class RbacPermissionAuditTest extends TestCase
{
    /** High-risk admin mutation routes => the permission slug each now carries (F9e). */
    private const HIGH_RISK_MUTATION_ROUTES = [
        'admin.users.add.sub.balance' => 'permission:admin.users.balance.adjust',
        'admin.users.kyc.approve'     => 'permission:admin.users.kyc.approve',
        'admin.users.status'          => 'permission:admin.users.status.update',
        'admin.deposit.approve'       => 'permission:admin.deposits.approve',
        'admin.withdraw.data.approve' => 'permission:admin.withdrawals.approve',
    ];

    public function test_high_risk_admin_mutations_carry_auth_guard_and_permission(): void
    {
        // F9e: these routes are now gated by the admin guard AND a permission:<slug>
        // (allow-by-default until rbac.enforce). Replaces the F8a "only auth guard"
        // baseline — intended, documented evolution.
        $routes = Route::getRoutes();
        $aliases = app('router')->getMiddleware();

        foreach (self::HIGH_RISK_MUTATION_ROUTES as $name => $permission) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Expected admin mutation route [{$name}] to exist.");

            $gathered = $route->gatherMiddleware();

            // admin guard still present (resolve aliases to concrete classes).
            $resolved = array_map(fn ($mw) => $aliases[$mw] ?? $mw, $gathered);
            $this->assertContains(
                RedirectIfNotAdmin::class,
                $resolved,
                "[{$name}] must still be gated by the admin auth guard."
            );

            // permission middleware now present.
            $this->assertContains(
                $permission,
                $gathered,
                "[{$name}] must carry [{$permission}] (F9e)."
            );
        }
    }

    public function test_permission_middleware_alias_is_registered(): void
    {
        // F9d: the `permission` alias now exists (allow-by-default gate).
        // (Replaces the F8a "no alias" guard — intended, documented evolution.)
        // No separate `role` alias is used — gating is slug-based via `permission`.
        $aliases = app('router')->getMiddleware();

        $this->assertArrayHasKey('permission', $aliases, 'The `permission` alias should be registered (F9d).');
        $this->assertSame(\App\Http\Middleware\PermissionMiddleware::class, $aliases['permission']);
        $this->assertArrayNotHasKey('role', $aliases, 'No separate `role` alias — gating is slug-based.');
    }

    public function test_admin_model_exposes_rbac_api(): void
    {
        // F9b: Admin gained a role() belongsTo relation and a hasPermission() method.
        // (Replaces the F8a "no relation" guard — intended, documented evolution.)
        $this->assertTrue(
            method_exists(Admin::class, 'role'),
            'Admin model should expose a role() relation (F9b).'
        );
        $this->assertTrue(
            method_exists(Admin::class, 'hasPermission'),
            'Admin model should expose hasPermission() (F9b).'
        );
    }
}
