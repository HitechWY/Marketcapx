<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RBAC permission (F9). A named, slugged capability grouped by area. The canonical
 * list of slugs lives in App\Support\PermissionCatalog (the single source of truth);
 * rows are seeded from it by RbacSeeder. Additive — unused until the `permission`
 * middleware is applied (F9e) and enforced (owner-gated, post-F9).
 */
class Permission extends Model
{
    protected $guarded = [];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
