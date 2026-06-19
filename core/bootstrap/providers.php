<?php

return [
    App\Providers\AppServiceProvider::class,
    // F0 (Inertia/Vue foundation): rebind public_path() to the project root for the
    // cPanel split so @vite resolves its manifest. Isolated & reversible — see the
    // provider's docblock and core/docs/INERTIA-VUE-FOUNDATION.md.
    App\Providers\CpanelPublicPathServiceProvider::class,
];
