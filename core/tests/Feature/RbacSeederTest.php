<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F9c coverage: the RBAC seeder + rbac:install command.
 *
 * DB-backed against the disposable `marketcapx_test`; DatabaseTransactions rolls back
 * every row (including anything the command writes via the same connection). No real
 * DB is touched and no accounts are created.
 */
class RbacSeederTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    public function test_seeder_creates_all_catalog_permissions_and_super_admin(): void
    {
        app(RbacSeeder::class)->run();

        $this->assertSame(count(PermissionCatalog::slugs()), Permission::count());

        $role = Role::where('slug', Role::SUPER_ADMIN)->first();
        $this->assertNotNull($role);
        $this->assertSame(count(PermissionCatalog::slugs()), $role->permissions()->count());
        $this->assertTrue($role->fresh()->load('permissions')->hasPermission('admin.deposits.approve'));
    }

    public function test_seeder_is_idempotent(): void
    {
        app(RbacSeeder::class)->run();
        $permsAfterFirst = Permission::count();
        $roleId = Role::where('slug', Role::SUPER_ADMIN)->value('id');
        $pivotAfterFirst = Role::find($roleId)->permissions()->count();

        app(RbacSeeder::class)->run(); // second run

        $this->assertSame($permsAfterFirst, Permission::count(), 'Permissions must not duplicate.');
        $this->assertSame(1, Role::where('slug', Role::SUPER_ADMIN)->count(), 'Super-admin role must not duplicate.');
        $this->assertSame($pivotAfterFirst, Role::find($roleId)->permissions()->count(), 'Pivot links must not duplicate.');
    }

    public function test_seeder_never_creates_admins(): void
    {
        $before = Admin::count();
        app(RbacSeeder::class)->run();
        $this->assertSame($before, Admin::count(), 'Seeder must not create admin accounts.');
    }

    public function test_command_assigns_super_admin_only_when_requested(): void
    {
        // Without --admin: no admin is touched.
        $admin = $this->makeAdmin();
        $this->assertNull($admin->role_id);

        $this->artisan('rbac:install')->assertExitCode(0);
        $this->assertNull($admin->fresh()->role_id, 'No admin should be assigned without --admin.');

        // With --admin: that existing admin gets super-admin.
        $this->artisan('rbac:install', ['--admin' => $admin->id])->assertExitCode(0);
        $assignedRoleId = $admin->fresh()->role_id;
        $this->assertNotNull($assignedRoleId);
        $this->assertSame(Role::SUPER_ADMIN, Role::find($assignedRoleId)->slug);
    }

    public function test_command_fails_for_unknown_admin_and_creates_nothing(): void
    {
        $before = Admin::count();
        $this->artisan('rbac:install', ['--admin' => 999999])->assertExitCode(1);
        $this->assertSame($before, Admin::count(), 'Unknown --admin must not create an account.');
    }
}
