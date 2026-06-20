<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesMoneyFixtures;
use Tests\TestCase;

/**
 * F9b coverage: RBAC model structure + permission resolution.
 *
 * DB-backed against the disposable `marketcapx_test` (RBAC tables applied by the
 * F9a migration). DatabaseTransactions rolls back every row created here — nothing
 * persists and no real DB is touched. No admin behavior is changed (enforcement is
 * allow-by-default and not wired to routes until F9e).
 */
class RbacStructureTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesMoneyFixtures;

    private function makeRole(string $slug): Role
    {
        return Role::create(['name' => 'Role ' . $slug, 'slug' => $slug]);
    }

    public function test_role_permission_attachment_resolves(): void
    {
        $role = $this->makeRole($this->uniq('role'));
        $perm = Permission::create(['name' => 'Approve deposit', 'slug' => 'admin.deposits.approve', 'group' => 'finance']);
        $role->permissions()->attach($perm->id);

        $role->load('permissions');
        $this->assertTrue($role->hasPermission('admin.deposits.approve'));
        $this->assertFalse($role->hasPermission('admin.withdrawals.approve'));
    }

    public function test_admin_with_role_has_only_granted_permissions(): void
    {
        $role = $this->makeRole($this->uniq('finance'));
        $perm = Permission::create(['name' => 'Approve deposit', 'slug' => 'admin.deposits.approve', 'group' => 'finance']);
        $role->permissions()->attach($perm->id);

        $admin = $this->makeAdmin();
        $admin->role_id = $role->id;
        $admin->save();
        $admin->refresh();

        $this->assertTrue($admin->hasPermission('admin.deposits.approve'));
        $this->assertFalse($admin->hasPermission('admin.withdrawals.approve'));
    }

    public function test_super_admin_role_allows_everything(): void
    {
        $super = $this->makeRole(Role::SUPER_ADMIN);

        $admin = $this->makeAdmin();
        $admin->role_id = $super->id;
        $admin->save();
        $admin->refresh();

        $this->assertTrue($admin->hasPermission('admin.deposits.approve'));
        $this->assertTrue($admin->hasPermission('admin.literally.anything'));
    }

    public function test_null_role_is_legacy_full_access(): void
    {
        $admin = $this->makeAdmin(); // role_id stays null

        $this->assertNull($admin->role_id);
        $this->assertTrue($admin->hasPermission('admin.deposits.approve'));
    }

    public function test_dangling_role_id_grants_nothing(): void
    {
        $admin = $this->makeAdmin();
        $admin->role_id = 999999; // no such role
        $admin->save();
        $admin->refresh();

        $this->assertFalse($admin->hasPermission('admin.deposits.approve'));
    }

    public function test_permission_catalog_has_unique_slugs_and_core_entries(): void
    {
        $slugs = PermissionCatalog::slugs();

        $this->assertSame(count($slugs), count(array_unique($slugs)), 'Catalog slugs must be unique.');
        $this->assertNotEmpty($slugs);

        foreach ([
            'admin.dashboard.view',
            'admin.users.status.update',
            'admin.users.balance.adjust',
            'admin.users.kyc.approve',
            'admin.deposits.approve',
            'admin.withdrawals.reject',
            'admin.gateway.update',
            'admin.settings.update',
            'admin.support.reply',
            'admin.roles.manage',
        ] as $slug) {
            $this->assertTrue(PermissionCatalog::has($slug), "Catalog should contain {$slug}.");
        }
    }
}
