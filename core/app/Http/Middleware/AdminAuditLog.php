<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight, schema-free audit trail for privileged admin actions (Phase 6).
 *
 * Logs metadata ONLY (who/what/when/where + response status) for state-changing
 * admin requests to a dedicated daily log file (storage/logs/admin-audit.log).
 * It never logs the request body, so passwords / gateway secrets / tokens are not
 * captured. Logging failures never affect the request.
 *
 * This is a non-repudiation stop-gap until a proper DB-backed audit log + RBAC
 * are implemented (see phase6-admin-rbac-audit.md). It changes no business logic.
 */
class AdminAuditLog
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            try {
                $admin = auth()->guard('admin')->user();

                if ($admin) {
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
                }
            } catch (\Throwable $e) {
                // Auditing must never break an admin action.
            }
        }

        return $response;
    }
}
