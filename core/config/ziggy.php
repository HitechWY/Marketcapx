<?php

/*
|--------------------------------------------------------------------------
| Ziggy route exposure (F1a — public Inertia PoC)
|--------------------------------------------------------------------------
|
| The Inertia root view (resources/views/app.blade.php) uses @routes('public'),
| so ONLY the route names listed in the 'public' group below are serialized into
| the page (the `Ziggy` object the Vue client reads). This deliberately keeps the
| application's full route table OUT of page source — admin, user/merchant/agent
| money flows, gateway/KYC mutation, and IPN/webhook routes are NOT exposed.
|
| Route URIs are not secrets (and protected routes stay auth-gated), but trimming
| the client payload is good hygiene for a payment app and avoids advertising the
| whole surface. Extend this group — or add new named groups consumed by a
| phase-specific @routes('<group>') — as later phases migrate more surfaces.
|
| Note: only PUBLIC, guest-safe routes belong here. Do not add admin/*, user
| money, gateway, KYC, ipn.*, or API routes.
*/

return [
    'groups' => [
        'public' => [
            // Inertia proof-of-concept / preview routes (removed at F8).
            'poc.inertia',
            'poc.public-home',

            // Public marketing surface (all guest-accessible).
            'home',
            'contact',
            'blogs',
            'blog.details',
            'pages',
            'policy.pages',
            'cookie.policy',
            'cookie.accept',
            'lang',
            'subscribe',
        ],
    ],
];
