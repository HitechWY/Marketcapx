<?php

namespace App\Http\Middleware;

use App\Services\AdminAuditLogger;
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
 *      - allow-by-default (config rbac.enforce = false, the F9 default): record a
 *        "would_deny" and ALLOW — zero behavior change vs the flat panel.
 *      - deny-by-default (rbac.enforce = true, OWNER-GATED): record "denied" + abort(403).
 *
 * F10c: every decision (allowed | would_deny | denied) is recorded to the structured
 * DB audit log (AdminAuditLogger) AND the file channel (dual-write). The decision logic
 * is unchanged — only logging was added.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $admin = auth()->guard('admin')->user();

        if ($admin && $admin->hasPermission($slug)) {
            $this->logDecision($request, $admin, $slug, 'allowed', null);
            return $next($request);
        }

        if ((bool) config('rbac.enforce', false)) {
            $this->logDecision($request, $admin, $slug, 'denied', 403);
            abort(403, 'You do not have permission to perform this action.');
        }

        // allow-by-default: never block; the record marks a would-deny for review.
        $this->logDecision($request, $admin, $slug, 'would_deny', null);
        return $next($request);
    }

    private function logDecision(Request $request, $admin, string $slug, string $result, ?int $httpStatus): void
    {
        // Structured DB audit (F10c). The logger is fail-open (never throws); the
        // extra guard here is belt-and-suspenders.
        try {
            app(AdminAuditLogger::class)->logPermissionDecision($admin, $slug, $result, $request, $httpStatus);
        } catch (\Throwable $e) {
            // Auditing must never break an admin action.
        }

        // File-channel dual-write (retains the F9 trail / fallback).
        try {
            Log::build([
                'driver' => 'daily',
                'path'   => storage_path('logs/admin-audit.log'),
                'days'   => 30,
            ])->info('admin.permission', [
                'decision'   => $result,
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
