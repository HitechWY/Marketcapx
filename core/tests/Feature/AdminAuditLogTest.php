<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuditLog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 6 coverage: the admin audit-log middleware is attached to admin routes.
 *
 * Route-collection inspection only (no HTTP dispatch, no DB), so safe to run
 * without a seeded test database.
 */
class AdminAuditLogTest extends TestCase
{
    public function test_admin_routes_have_audit_log_middleware(): void
    {
        $adminRoute = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => str_starts_with($route->uri(), 'admin'));

        $this->assertNotNull($adminRoute, 'Expected at least one admin/* route.');
        $this->assertContains(
            AdminAuditLog::class,
            $adminRoute->gatherMiddleware(),
            'Admin routes must carry the AdminAuditLog middleware.'
        );
    }
}
