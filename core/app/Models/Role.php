<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RBAC role (F9). A named set of permissions. The `super-admin` role short-circuits
 * to allow everything. Additive — admins with a null role_id keep legacy full access
 * (see Admin::hasPermission); enforcement is owner-gated and not part of F9.
 */
class Role extends Model
{
    /** Slug of the all-powerful role (bypasses per-permission checks). */
    public const SUPER_ADMIN = 'super-admin';

    protected $guarded = [];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->slug === self::SUPER_ADMIN) {
            return true;
        }

        return $this->permissions->contains('slug', $slug);
    }
}
