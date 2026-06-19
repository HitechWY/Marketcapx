<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * F0 (Inertia/Vue foundation) — Inertia request handler.
 *
 * Registered on the `web` middleware group only (see bootstrap/app.php). It is
 * inert for ordinary Blade responses: it only shapes responses for requests that
 * carry the X-Inertia header, so every existing Blade page is unaffected.
 *
 * `share()` is intentionally minimal for F0 (no payment/auth/business data). The
 * existing `notify` flash system and per-guard auth sharing are wired in later
 * phases (F1+) as those surfaces migrate.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root Blade template that boots the Inertia app.
     */
    protected $rootView = 'app';

    /**
     * Asset version — busts the client cache when the build changes.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every Inertia response.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
        ];
    }
}
