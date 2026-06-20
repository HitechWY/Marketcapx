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
    /** High-risk admin mutation routes that today rely solely on the admin guard. */
    private const HIGH_RISK_MUTATION_ROUTES = [
        'admin.users.add.sub.balance',
        'admin.users.kyc.approve',
        'admin.users.status',
        'admin.deposit.approve',
        'admin.withdraw.data.approve',
    ];

    public function test_high_risk_admin_mutations_are_gated_only_by_auth_guard(): void
    {
        $routes = Route::getRoutes();
        $aliases = app('router')->getMiddleware();

        foreach (self::HIGH_RISK_MUTATION_ROUTES as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Expected admin mutation route [{$name}] to exist.");

            // Resolve route middleware, expanding aliases (e.g. 'admin' => RedirectIfNotAdmin)
            // to their concrete classes.
            $resolved = array_map(
                fn ($mw) => $aliases[$mw] ?? $mw,
                $route->gatherMiddleware()
            );

            $this->assertContains(
                RedirectIfNotAdmin::class,
                $resolved,
                "[{$name}] must be gated by the admin auth guard."
            );

            // No authorization layer exists today: assert no permission/role middleware.
            foreach ($resolved as $mw) {
                $this->assertStringNotContainsStringIgnoringCase(
                    'permission',
                    (string) $mw,
                    "[{$name}] unexpectedly carries permission middleware [{$mw}]."
                );
                $this->assertDoesNotMatchRegularExpression(
                    '/\\\\Role[A-Za-z]*$|\\bRoleMiddleware\\b/',
                    (string) $mw,
                    "[{$name}] unexpectedly carries role middleware [{$mw}]."
                );
            }
        }
    }

    public function test_no_permission_or_role_middleware_alias_is_registered(): void
    {
        $aliases = array_keys(app('router')->getMiddleware());

        $this->assertNotContains('permission', $aliases, 'No RBAC permission middleware should be registered yet.');
        $this->assertNotContains('role', $aliases, 'No RBAC role middleware should be registered yet.');
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
