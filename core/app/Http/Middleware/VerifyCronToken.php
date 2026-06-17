<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Guards the public GET /cron endpoint.
 *
 * Behaviour (fail closed):
 *  - If no secret is configured (CRON_SECRET empty/unset), the request is rejected
 *    with 403 and cron work never runs.
 *  - If config('cron.secret') is configured, the request must present the same
 *    value via the "X-CRON-TOKEN" header or a "token" query parameter, compared
 *    with hash_equals(); otherwise it is rejected with 403.
 *
 * The secret value itself is never logged.
 */
class VerifyCronToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('cron.secret', '');

        // Fail closed: with no CRON_SECRET configured the endpoint is rejected and
        // cron work never runs. Set CRON_SECRET (env) to enable authenticated access.
        if ($expected === '') {
            Log::warning('Cron endpoint rejected: CRON_SECRET is not configured. Set CRON_SECRET to enable /cron.');
            abort(403, 'Cron endpoint is disabled until CRON_SECRET is configured.');
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
