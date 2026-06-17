<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Guards the public GET /cron endpoint.
 *
 * Behaviour:
 *  - If config('cron.secret') is configured, the request must present the same
 *    value via the "X-CRON-TOKEN" header or a "token" query parameter, compared
 *    with hash_equals(). Otherwise it is rejected with 403.
 *  - If no secret is configured, the request is allowed (backward compatible with
 *    existing schedulers) but a warning is logged so operators set CRON_SECRET.
 *
 * The secret value itself is never logged.
 */
class VerifyCronToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('cron.secret', '');

        if ($expected === '') {
            Log::warning('Cron endpoint accessed without CRON_SECRET configured; endpoint is currently unauthenticated. Set CRON_SECRET to secure it.');
            return $next($request);
        }

        $provided = $request->header('X-CRON-TOKEN');
        if (!is_string($provided) || $provided === '') {
            $provided = (string) $request->query('token', '');
        }

        if ($provided === '' || !hash_equals($expected, $provided)) {
            abort(403, 'Invalid or missing cron token.');
        }

        return $next($request);
    }
}
