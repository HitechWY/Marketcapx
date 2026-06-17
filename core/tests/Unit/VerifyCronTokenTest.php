<?php

namespace Tests\Unit;

use App\Http\Middleware\VerifyCronToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Phase 1B coverage: the /cron guard middleware.
 *
 * DB-free: these tests only exercise the middleware logic via config(), no
 * database access and the CronController is never invoked.
 */
class VerifyCronTokenTest extends TestCase
{
    private function runMiddleware(Request $request): string
    {
        $middleware = new VerifyCronToken();

        $response = $middleware->handle($request, function () {
            return 'PASSED';
        });

        return is_string($response) ? $response : 'PASSED';
    }

    public function test_blocks_when_no_secret_configured(): void
    {
        // Fail-closed (Phase 1B): with no CRON_SECRET set, /cron must be rejected
        // and cron work must never run. (Earlier behavior allowed it; the guard was
        // hardened in 63be19f and this test now asserts the hardened contract.)
        config(['cron.secret' => null]);

        $this->expectException(HttpException::class);
        $this->runMiddleware(Request::create('/cron', 'GET'));
    }

    public function test_blocks_when_secret_set_but_no_token(): void
    {
        config(['cron.secret' => 'unit-test-secret']);

        $this->expectException(HttpException::class);
        $this->runMiddleware(Request::create('/cron', 'GET'));
    }

    public function test_blocks_when_secret_set_but_wrong_token(): void
    {
        config(['cron.secret' => 'unit-test-secret']);

        $this->expectException(HttpException::class);
        $this->runMiddleware(Request::create('/cron', 'GET', ['token' => 'nope']));
    }

    public function test_allows_with_correct_query_token(): void
    {
        config(['cron.secret' => 'unit-test-secret']);

        $request = Request::create('/cron', 'GET', ['token' => 'unit-test-secret']);
        $this->assertSame('PASSED', $this->runMiddleware($request));
    }

    public function test_allows_with_correct_header_token(): void
    {
        config(['cron.secret' => 'unit-test-secret']);

        $request = Request::create('/cron', 'GET');
        $request->headers->set('X-CRON-TOKEN', 'unit-test-secret');

        $this->assertSame('PASSED', $this->runMiddleware($request));
    }
}
