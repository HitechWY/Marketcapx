<?php

namespace Tests\Feature;

use App\Http\Middleware\PermissionMiddleware;
use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F10c coverage: PermissionMiddleware writes a structured DB audit row for every
 * decision (allowed / would_deny / denied). DB-backed on `marketcapx_test`;
 * DatabaseTransactions rolls back. Decision behavior is unchanged from F9.
 */
class PermissionMiddlewareAuditTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    private function gate(string $slug)
    {
        $mw = new PermissionMiddleware();
        return $mw->handle(Request::create('/admin/deposit/approve/9', 'POST'), fn ($r) => response('OK', 200), $slug);
    }

    private function roleWith(string ...$slugs): Role
    {
        $role = Role::create(['name' => 'R', 'slug' => $this->uniq('role')]);
        foreach ($slugs as $s) {
            $perm = Permission::firstOrCreate(['slug' => $s], ['name' => $s, 'group' => 'finance']);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }
        return $role->load('permissions');
    }

    public function test_allowed_decision_is_audited(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin'); // legacy null role => allowed
        $before = AdminAuditLog::count();

        $this->gate('admin.deposits.approve');

        $this->assertSame($before + 1, AdminAuditLog::count());
        $row = AdminAuditLog::latest('id')->first();
        $this->assertSame('rbac.permission.allowed', $row->action);
        $this->assertSame('allowed', $row->result);
        $this->assertSame('admin.deposits.approve', $row->permission_slug);
    }

    public function test_would_deny_is_audited_in_allow_by_default(): void
    {
        config(['rbac.enforce' => false]);
        $admin = $this->makeAdmin();
        $admin->role_id = $this->roleWith('admin.deposits.view')->id; // lacks approve
        $admin->save();
        $this->actingAs($admin->refresh(), 'admin');

        $this->gate('admin.deposits.approve');

        $row = AdminAuditLog::latest('id')->first();
        $this->assertSame('would_deny', $row->result);
        $this->assertSame('rbac.permission.would_deny', $row->action);
    }

    public function test_denied_is_audited_when_enforce_enabled(): void
    {
        config(['rbac.enforce' => true]); // test-only override
        $admin = $this->makeAdmin();
        $admin->role_id = $this->roleWith('admin.deposits.view')->id;
        $admin->save();
        $this->actingAs($admin->refresh(), 'admin');

        try {
            $this->gate('admin.deposits.approve');
            $this->fail('Expected 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $row = AdminAuditLog::latest('id')->first();
        $this->assertSame('denied', $row->result);
        $this->assertSame(403, $row->http_status);
    }

    public function test_audit_row_contains_no_request_body_or_secrets(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin');
        // Body carries a secret; the middleware must never persist it.
        $mw = new PermissionMiddleware();
        $req = Request::create('/admin/gateway/automatic/update/stripe', 'POST', ['secret_key' => 'sk_live_LEAK']);
        $mw->handle($req, fn ($r) => response('OK', 200), 'admin.gateway.update');

        $row = AdminAuditLog::latest('id')->first();
        $serialized = json_encode($row->toArray());
        $this->assertStringNotContainsString('sk_live_LEAK', $serialized);
        $this->assertNull($row->before_values);
        $this->assertNull($row->after_values);
    }
}
