<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Role;
use Database\Seeders\RbacSeeder;
use Illuminate\Console\Command;

/**
 * RBAC install (F9c). Idempotently seeds permissions + the super-admin role, and
 * (only when --admin is passed) assigns an EXISTING admin to super-admin.
 *
 * Never creates accounts. Run freely against the disposable test DB; running against
 * any real/dev/prod DB is owner-gated (see docs/RBAC-SEEDING.md). This does NOT enable
 * deny-by-default — enforcement stays allow-by-default (config/rbac.php, F9d).
 */
class RbacInstall extends Command
{
    protected $signature = 'rbac:install {--admin= : ID of an EXISTING admin to assign the super-admin role (optional)}';

    protected $description = 'Idempotently seed RBAC permissions and the super-admin role; optionally assign an existing admin to super-admin.';

    public function handle(): int
    {
        app(RbacSeeder::class)->run();
        $this->info('Seeded RBAC permissions + super-admin role (idempotent).');

        $adminId = $this->option('admin');
        if ($adminId !== null) {
            $admin = Admin::find($adminId);
            if (!$admin) {
                $this->error("Admin [{$adminId}] not found — no assignment made. (rbac:install never creates accounts.)");
                return self::FAILURE;
            }

            $role = Role::where('slug', Role::SUPER_ADMIN)->firstOrFail();
            $admin->role_id = $role->id;
            $admin->save();
            $this->info("Assigned admin [{$adminId}] to the super-admin role.");
        }

        return self::SUCCESS;
    }
}
