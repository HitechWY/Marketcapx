<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuditLog;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F9e coverage: high-risk admin routes carry the correct permission:<slug> middleware,
 * every applied slug exists in the catalog, no non-admin route is gated, and (one
 * integration check) enforcement returns 403 when enabled.
 *
 * Mostly route-collection inspection. The single end-to-end check hits a GET route
 * that is blocked by 403 BEFORE its controller runs, so no money/admin action executes.
 */
class RbacRouteCoverageTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Mirror the admin HTTP tests: disable the vendor license/redirect middleware
        // (web group) so requests reach the RBAC layer. Does NOT remove the
        // `permission` middleware under test.
        $this->withoutMiddleware([
            \Laramin\Utility\Utility::class,
            \Laramin\Utility\GoToCore::class,
            AdminAuditLog::class,
        ]);
    }

    /** Representative high-risk routes => expected permission middleware. */
    private const EXPECTED = [
        'admin.users.kyc.approve'                        => 'permission:admin.users.kyc.approve',
        'admin.agents.kyc.reject'                        => 'permission:admin.agents.kyc.reject',
        'admin.merchants.status'                         => 'permission:admin.merchants.status.update',
        'admin.users.add.sub.balance'                    => 'permission:admin.users.balance.adjust',
        'admin.users.login'                              => 'permission:admin.users.login',
        'admin.deposit.approve'                          => 'permission:admin.deposits.approve',
        'admin.deposit.reject'                           => 'permission:admin.deposits.reject',
        'admin.withdraw.data.approve'                    => 'permission:admin.withdrawals.approve',
        'admin.withdraw.data.reject'                     => 'permission:admin.withdrawals.reject',
        'admin.escrow.action'                            => 'permission:admin.escrow.action',
        'admin.ticket.delete'                            => 'permission:admin.support.delete',
        'admin.gateway.automatic.update'                 => 'permission:admin.gateway.update',
        'admin.currency.update'                          => 'permission:admin.currency.manage',
        'admin.setting.notification.global.email.update' => 'permission:admin.settings.notifications.update',
        'admin.plugins.upload'                           => 'permission:admin.system.plugins.manage',
        'admin.system.update.process'                    => 'permission:admin.system.update',
        'admin.cron.store'                               => 'permission:admin.system.cron.manage',
    ];

    public function test_high_risk_routes_carry_expected_permission_middleware(): void
    {
        $routes = Route::getRoutes();

        foreach (self::EXPECTED as $name => $expected) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Route [{$name}] should exist.");
            $this->assertContains(
                $expected,
                $route->gatherMiddleware(),
                "[{$name}] should carry [{$expected}]."
            );
        }
    }

    public function test_all_applied_permission_slugs_exist_in_catalog(): void
    {
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && str_starts_with($mw, 'permission:')) {
                    $slug = substr($mw, strlen('permission:'));
                    $this->assertTrue(
                        PermissionCatalog::has($slug),
                        "Applied slug [{$slug}] on [{$route->uri()}] is not in PermissionCatalog."
                    );
                }
            }
        }
    }

    public function test_no_non_admin_route_carries_permission_middleware(): void
    {
        foreach (Route::getRoutes() as $route) {
            $gated = false;
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && str_starts_with($mw, 'permission:')) {
                    $gated = true;
                    break;
                }
            }
            if ($gated) {
                $this->assertStringStartsWith(
                    'admin',
                    $route->uri(),
                    "Non-admin route [{$route->uri()}] must not carry permission middleware."
                );
            }
        }
    }

    public function test_enforcement_denies_without_permission_end_to_end(): void
    {
        // GET /admin/system/optimize is gated by permission:admin.system.optimize.
        // With enforce=true and a role lacking it, the middleware 403s BEFORE the
        // controller — so optimize never runs (no side effect).
        config(['rbac.enforce' => true]);

        $admin = $this->makeAdmin();
        $admin->role_id = Role::create(['name' => 'No-sys', 'slug' => $this->uniq('role')])->id; // no permissions
        $admin->save();

        $this->actingAs($admin->refresh(), 'admin')
            ->get('/admin/system/optimize')
            ->assertForbidden(); // 403
    }
}
