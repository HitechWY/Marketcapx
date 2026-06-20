<?php

namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * RBAC (F9): the admin's role. belongsTo `roles` via the nullable `admins.role_id`.
     * A null role_id means legacy full access (backward-compatible — see hasPermission).
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * RBAC (F9): does this admin hold the given permission slug?
     *
     * - null role_id  => legacy full access (true) — keeps existing admins working
     *   until roles are seeded and deny-by-default is enabled (owner-gated, post-F9).
     * - super-admin   => true (Role::hasPermission short-circuit).
     * - role present  => true iff the role has the slug.
     * - dangling role_id (role missing) => false (unknown role grants nothing).
     *
     * NOTE: this only answers the question; the `permission` middleware decides
     * enforcement (allow-by-default in F9, so a false here is logged, not blocked).
     */
    public function hasPermission(string $slug): bool
    {
        if (is_null($this->role_id)) {
            return true;
        }

        return (bool) optional($this->role)->hasPermission($slug);
    }
}
