<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuditLog;
use App\Models\AdminAuditLog as AuditRow;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F10d coverage: the AdminAuditLog middleware dual-writes a structured DB row for
 * admin state-changing requests, with route-based entity inference.
 *
 * The HTTP check targets a gated POST with enforce=true + a permission-less role, so
 * the request is 403'd by the permission middleware BEFORE any controller runs (no
 * money/admin side effect) while the AdminAuditLog middleware still records the
 * request. DB-backed on `marketcapx_test`; DatabaseTransactions rolls back.
 */
class AdminRequestAuditTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable only the vendor license/redirect middleware so the request reaches
        // the admin stack. AdminAuditLog + PermissionMiddleware stay active (under test).
        $this->withoutMiddleware([
            \Laramin\Utility\Utility::class,
            \Laramin\Utility\GoToCore::class,
        ]);
    }

    public function test_admin_post_writes_structured_row_with_inferred_entity(): void
    {
        config(['rbac.enforce' => true]); // 403 before controller → no side effect
        $admin = $this->makeAdmin();
        $admin->role_id = Role::create(['name' => 'None', 'slug' => $this->uniq('role')])->id;
        $admin->save();

        $this->actingAs($admin->refresh(), 'admin')
            ->post('/admin/users/status/7', ['reason' => 'x'])
            ->assertForbidden();

        // AdminAuditLog middleware row for the POST (403 => failed), entity inferred.
        $row = AuditRow::where('action', 'admin.request.failed')->latest('id')->first();
        $this->assertNotNull($row, 'Expected an admin.request.failed row.');
        $this->assertSame('POST', $row->method);
        $this->assertSame('admin.users.status', $row->route_name);
        $this->assertSame('users', $row->entity_type);
        $this->assertSame(7, $row->entity_id);
        $this->assertSame(403, $row->http_status);
        $this->assertSame($admin->id, $row->admin_id);
    }

    public function test_request_body_secret_is_not_persisted(): void
    {
        config(['rbac.enforce' => true]);
        $admin = $this->makeAdmin();
        $admin->role_id = Role::create(['name' => 'None', 'slug' => $this->uniq('role')])->id;
        $admin->save();

        $this->actingAs($admin->refresh(), 'admin')
            ->post('/admin/gateway/automatic/update/stripe', ['secret_key' => 'sk_live_LEAK', 'token' => 'tok_LEAK'])
            ->assertForbidden();

        foreach (AuditRow::all() as $row) {
            $blob = json_encode($row->toArray());
            $this->assertStringNotContainsString('sk_live_LEAK', $blob);
            $this->assertStringNotContainsString('tok_LEAK', $blob);
        }
    }

    public function test_admin_audit_middleware_is_only_on_admin_routes(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (in_array(AdminAuditLog::class, $route->gatherMiddleware(), true)) {
                $this->assertStringStartsWith(
                    'admin',
                    $route->uri(),
                    "AdminAuditLog middleware must only be on admin routes; found on [{$route->uri()}]."
                );
            }
        }
    }
}
