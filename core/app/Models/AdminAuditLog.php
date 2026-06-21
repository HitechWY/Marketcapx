<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Structured admin audit-log row (F10). Append-only: `created_at` only, no
 * `updated_at` (UPDATED_AT = null). Written exclusively by App\Services\AdminAuditLogger.
 *
 * NOTE: distinct from the App\Http\Middleware\AdminAuditLog middleware (different
 * namespace) — the middleware is a writer, this is the storage model.
 */
class AdminAuditLog extends Model
{
    /** Append-only: skip updated_at entirely. */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'before_values' => 'array',
        'after_values'  => 'array',
        'metadata'      => 'array',
        'entity_id'     => 'integer',
        'admin_id'      => 'integer',
        'http_status'   => 'integer',
        'created_at'    => 'datetime',
    ];

    /*
     * Read-only query scopes (F12) for would-deny review / audit reporting. These only
     * narrow SELECTs — they never write. Used by the rbac:would-deny-report command and
     * its tests. Adding them does not change how AdminAuditLogger writes rows.
     */

    /** RBAC permission-decision rows only (action `rbac.permission.*`). */
    public function scopeRbacDecision(Builder $query): Builder
    {
        return $query->where('action', 'like', 'rbac.permission.%');
    }

    /** Filter by result value (e.g. would_deny | allowed | denied). */
    public function scopeResult(Builder $query, string $result): Builder
    {
        return $query->where('result', $result);
    }

    /** Convenience: only would_deny rows. */
    public function scopeWouldDeny(Builder $query): Builder
    {
        return $query->where('result', 'would_deny');
    }

    public function scopeForAdmin(Builder $query, $adminId): Builder
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopePermission(Builder $query, string $slug): Builder
    {
        return $query->where('permission_slug', $slug);
    }

    public function scopeRouteName(Builder $query, string $name): Builder
    {
        return $query->where('route_name', $name);
    }

    /** Rows created within the last $days (by created_at). */
    public function scopeSince(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
