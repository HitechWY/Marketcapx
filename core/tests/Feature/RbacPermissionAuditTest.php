<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectIfNotAdmin;
use App\Models\Admin;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F8a coverage: documents the CURRENT (flat) admin authorization model.
 *
 * These assertions lock in today's behavior so the RBAC foundation (RBAC-DESIGN.md)
 * is a deliberate, reviewed change rather than an accidental drift. They prove:
 *  - high-risk admin mutation routes are gated ONLY by the `admin` auth guard
 *    (RedirectIfNotAdmin), with NO permission/role middleware;
 *  - no `permission`/`role` middleware alias is registered;
 *  - the Admin model carries no role/permission relation.
 *
 * Route-collection + reflection inspection only (no HTTP dispatch, no DB), so safe
 * to run without a seeded test database. When RBAC is implemented these tests are
 * expected to be updated alongside it.
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

    public function test_admin_model_has_no_role_or_permission_relation(): void
    {
        $this->assertFalse(
            method_exists(Admin::class, 'role'),
            'Admin model has no role() relation today (flat admin).'
        );
        $this->assertFalse(
            method_exists(Admin::class, 'permissions'),
            'Admin model has no permissions() relation today (flat admin).'
        );
    }
}
