<?php

namespace App\Http\Middleware;

use App\Services\AdminAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Audit trail for state-changing admin requests.
 *
 * Logs metadata ONLY (who/what/when/where + response status) for POST/PUT/PATCH/
 * DELETE admin requests. It never logs the request body, so passwords / gateway
 * secrets / tokens are never captured. Logging failures never affect the request.
 *
 * F10d: dual-writes a structured row to the DB audit log (AdminAuditLogger) in
 * addition to the file channel (kept as fallback). Entity_type/entity_id are inferred
 * from route name + {id} param inside the logger (non-invasive — no controller change).
 * Privileged GET actions (impersonation, KYC view) are audited separately via the
 * `permission` middleware's rbac.permission.* rows (F10c). Business logic unchanged.
 */
class AdminAuditLog
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $admin = auth()->guard('admin')->user();

            if ($admin) {
                // Structured DB audit (F10d) — fail-open logger.
                try {
                    app(AdminAuditLogger::class)->logAdminRequest($request, $response);
                } catch (\Throwable $e) {
                    // Auditing must never break an admin action.
                }

                // File-channel dual-write (retains the existing trail / fallback).
                try {
                    Log::build([
                        'driver' => 'daily',
                        'path'   => storage_path('logs/admin-audit.log'),
                        'days'   => 30,
                    ])->info('admin.action', [
                        'admin_id' => $admin->id,
                        'username' => $admin->username ?? null,
                        'method'   => $request->method(),
                        'path'     => $request->path(),
                        'route'    => optional($request->route())->getName(),
                        'ip'       => $request->ip(),
                        'status'   => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                        'at'       => now()->toDateTimeString(),
                    ]);
                } catch (\Throwable $e) {
                    // Auditing must never break an admin action.
                }
            }
        }

        return $response;
    }
}
