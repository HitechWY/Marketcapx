<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Phase 5/F + Phase 11 coverage: the named rate limiters used by the auth/OTP/
 * password-reset routes are actually registered in AppServiceProvider and yield
 * the documented per-minute limits.
 *
 * This complements RateLimitRoutesTest: that test asserts the "throttle:<name>"
 * middleware string is attached to the routes; this one asserts the named
 * limiter resolves and produces the expected Limit. A typo in the limiter name
 * (so "throttle:login" has no backing definition) would pass the route test but
 * fail here. Resolves the limiter closure against a fake Request only -- no HTTP
 * dispatch and no DB, so it is safe without a seeded test database.
 */
class RateLimiterDefinitionTest extends TestCase
{
    private function resolveLimit(string $name): Limit
    {
        $limiter = RateLimiter::limiter($name);
        $this->assertNotNull($limiter, "Named rate limiter '{$name}' must be registered.");

        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7']);
        $result = $limiter($request);

        // A limiter may return a single Limit or an array of Limits.
        $limit = is_array($result) ? $result[0] : $result;
        $this->assertInstanceOf(Limit::class, $limit, "Limiter '{$name}' should return a Limit.");

        return $limit;
    }

    public function test_login_limiter_allows_ten_per_minute(): void
    {
        $this->assertSame(10, $this->resolveLimit('login')->maxAttempts);
    }

    public function test_otp_limiter_allows_six_per_minute(): void
    {
        $this->assertSame(6, $this->resolveLimit('otp')->maxAttempts);
    }

    public function test_password_reset_limiter_allows_six_per_minute(): void
    {
        $this->assertSame(6, $this->resolveLimit('password-reset')->maxAttempts);
    }

    public function test_registration_limiter_allows_five_per_minute(): void
    {
        $this->assertSame(5, $this->resolveLimit('registration')->maxAttempts);
    }

    public function test_limiters_are_keyed_per_client(): void
    {
        // Each sensitive limiter is keyed (by IP) rather than global, so one
        // client cannot exhaust the bucket for everyone.
        foreach (['login', 'otp', 'password-reset', 'registration'] as $name) {
            $this->assertNotSame(
                '',
                (string) $this->resolveLimit($name)->key,
                "Limiter '{$name}' should be keyed per client, not global."
            );
        }
    }
}
