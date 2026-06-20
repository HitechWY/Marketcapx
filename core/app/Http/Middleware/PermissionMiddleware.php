<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC permission gate (F9d). Usage: ->middleware('permission:admin.deposits.approve').
 *
 * Runs AFTER the `admin` auth guard, so the admin is authenticated here. Decision:
 *  - admin has the permission (incl. super-admin, and legacy null role_id) => allow.
 *  - otherwise:
 *      - allow-by-default (config rbac.enforce = false, the F9 default): LOG a
 *        "would_deny" and ALLOW — zero behavior change vs the flat panel.
 *      - deny-by-default (rbac.enforce = true, OWNER-GATED): LOG "denied" + abort(403).
 *
 * Logging is metadata-only (who/what/when/route — never request body/secrets) and is
 * routed through the same admin-audit channel as AdminAuditLog. logDecision() is the
 * single sink so it can later be swapped for the DB audit-log foundation.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $admin = auth()->guard('admin')->user();

        if ($admin && $admin->hasPermission($slug)) {
            return $next($request);
        }

        $enforce = (bool) config('rbac.enforce', false);
        $this->logDecision($request, $admin, $slug, $enforce);

        if ($enforce) {
            abort(403, 'You do not have permission to perform this action.');
        }

        // allow-by-default: never block; the log records a would-deny for review.
        return $next($request);
    }

    private function logDecision(Request $request, $admin, string $slug, bool $enforce): void
    {
        try {
            Log::build([
                'driver' => 'daily',
                'path'   => storage_path('logs/admin-audit.log'),
                'days'   => 30,
            ])->info('admin.permission', [
                'decision'   => $enforce ? 'denied' : 'would_deny',
                'permission' => $slug,
                'admin_id'   => $admin?->id,
                'username'   => $admin?->username ?? null,
                'method'     => $request->method(),
                'path'       => $request->path(),
                'route'      => optional($request->route())->getName(),
                'ip'         => $request->ip(),
                'at'         => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break an admin action.
        }
    }
}
