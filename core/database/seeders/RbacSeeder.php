<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * RBAC seeder (F9c). Idempotent.
 *
 * - Upserts every permission in App\Support\PermissionCatalog (by slug).
 * - Upserts the `super-admin` role.
 * - Grants ALL permissions to super-admin (syncWithoutDetaching — no dupes, no removals).
 *
 * It NEVER creates admin/user/merchant/agent accounts and never assigns a role to an
 * admin (that is the rbac:install --admin option, an explicit opt-in). Safe to run
 * repeatedly. Run only against the disposable `marketcapx_test` in tests; running
 * against any real DB is owner-gated (see docs/RBAC-SEEDING.md).
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = [];

        foreach (PermissionCatalog::all() as $p) {
            $permission = Permission::updateOrCreate(
                ['slug' => $p['slug']],
                ['name' => $p['name'], 'group' => $p['group']]
            );
            $permissionIds[] = $permission->id;
        }

        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super Admin']
        );

        // Idempotent: the pivot has a unique (role_id, permission_id) constraint and
        // syncWithoutDetaching adds only missing links — re-running changes nothing.
        $role->permissions()->syncWithoutDetaching($permissionIds);
    }
}
