<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCronToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 1 / 1B coverage: operational route hardening.
 *
 * These assertions inspect the registered route collection only (no HTTP
 * dispatch, no DB), so they are safe to run without a seeded test database.
 */
class OperationalRoutesTest extends TestCase
{
    public function test_public_clear_route_is_removed(): void
    {
        $hasClear = collect(Route::getRoutes()->getRoutes())
            ->contains(fn ($route) => $route->uri() === 'clear');

        $this->assertFalse($hasClear, 'Public GET /clear must not be registered.');
    }

    public function test_cron_route_exists_and_is_token_guarded(): void
    {
        $cron = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'cron');

        $this->assertNotNull($cron, 'GET /cron route should still be registered.');
        $this->assertContains(
            VerifyCronToken::class,
            $cron->gatherMiddleware(),
            'The /cron route must be guarded by VerifyCronToken.'
        );
    }

    public function test_home_route_is_registered(): void
    {
        $home = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === '/' && in_array('GET', $route->methods()));

        $this->assertNotNull($home, 'Home route (GET /) should be registered.');
    }
}
