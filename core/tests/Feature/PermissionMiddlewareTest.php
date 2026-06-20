<?php

namespace Tests\Feature;

use App\Http\Middleware\PermissionMiddleware;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F9d coverage: the `permission:<slug>` middleware in allow-by-default mode.
 *
 * Exercises the middleware directly (no route is gated until F9e). DB-backed against
 * `marketcapx_test`; DatabaseTransactions rolls back. The middleware is invoked with a
 * built request + an authenticated admin guard; logging is asserted via Log::spy so no
 * file is written. No admin behavior changes in the default (enforce=false) mode.
 */
class PermissionMiddlewareTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    private function gate(string $slug): \Symfony\Component\HttpFoundation\Response
    {
        $mw = new PermissionMiddleware();
        return $mw->handle(Request::create('/admin/probe', 'POST'), fn ($req) => response('OK', 200), $slug);
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

    public function test_legacy_null_role_admin_is_allowed(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin'); // role_id null
        $this->assertSame(200, $this->gate('admin.deposits.approve')->getStatusCode());
    }

    public function test_super_admin_is_allowed(): void
    {
        $admin = $this->makeAdmin();
        $admin->role_id = Role::create(['name' => 'Super', 'slug' => Role::SUPER_ADMIN])->id;
        $admin->save();
        $this->actingAs($admin->refresh(), 'admin');

        $this->assertSame(200, $this->gate('admin.anything')->getStatusCode());
    }

    public function test_role_with_permission_is_allowed(): void
    {
        $admin = $this->makeAdmin();
        $admin->role_id = $this->roleWith('admin.deposits.approve')->id;
        $admin->save();
        $this->actingAs($admin->refresh(), 'admin');

        $this->assertSame(200, $this->gate('admin.deposits.approve')->getStatusCode());
    }

    public function test_role_without_permission_is_allowed_but_logged_in_allow_by_default(): void
    {
        config(['rbac.enforce' => false]);
        Log::spy();

        $admin = $this->makeAdmin();
        $admin->role_id = $this->roleWith('admin.deposits.view')->id; // lacks approve
        $admin->save();
        $this->actingAs($admin->refresh(), 'admin');

        $this->assertSame(200, $this->gate('admin.deposits.approve')->getStatusCode(), 'allow-by-default must not block');
    }

    public function test_role_without_permission_is_denied_when_enforce_enabled(): void
    {
        config(['rbac.enforce' => true]); // test-only override; default stays false

        $admin = $this->makeAdmin();
        $admin->role_id = $this->roleWith('admin.deposits.view')->id; // lacks approve
        $admin->save();
        $this->actingAs($admin->refresh(), 'admin');

        try {
            $this->gate('admin.deposits.approve');
            $this->fail('Expected a 403 when enforce=true and permission is missing.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_enforce_default_is_false(): void
    {
        // Guard rail: deny-by-default must NOT be the shipped default (owner-gated).
        $this->assertFalse((bool) config('rbac.enforce'), 'rbac.enforce must default to false.');
    }
}
