<?php

namespace App\Models;

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
}
