<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 5/F coverage: sensitive auth routes carry their rate-limit middleware.
 *
 * Route-collection inspection only (no HTTP dispatch, no DB), so safe to run
 * without a seeded test database. gatherMiddleware() returns the assigned
 * middleware strings (e.g. "throttle:login").
 */
class RateLimitRoutesTest extends TestCase
{
    private function postRoute(string $uri)
    {
        return collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === $uri && in_array('POST', $r->methods()));
    }

    private function anyRoute(string $uri)
    {
        return collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === $uri);
    }

    public function test_user_login_is_throttled(): void
    {
        $route = $this->postRoute('user/login');
        $this->assertNotNull($route, 'user/login POST should exist.');
        $this->assertContains('throttle:login', $route->gatherMiddleware());
    }

    public function test_api_login_is_throttled(): void
    {
        $route = $this->postRoute('api/login');
        $this->assertNotNull($route, 'api/login POST should exist.');
        $this->assertContains('throttle:login', $route->gatherMiddleware());
    }

    public function test_api_password_reset_email_is_throttled(): void
    {
        $route = $this->postRoute('api/password/email');
        $this->assertNotNull($route, 'api/password/email POST should exist.');
        $this->assertContains('throttle:password-reset', $route->gatherMiddleware());
    }

    public function test_otp_resend_is_throttled(): void
    {
        $route = $this->anyRoute('otp-resend');
        $this->assertNotNull($route, 'otp-resend route should exist.');
        $this->assertContains('throttle:otp', $route->gatherMiddleware());
    }
}
