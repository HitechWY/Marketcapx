import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

// MarketCap X — Vite config (F0: Inertia/Vue foundation).
//
// cPanel split layout: the web root is the PROJECT ROOT (/index.php boots core/),
// while this Vite project + Laravel base path live in core/. Assets must therefore
// be emitted into the project root (one level above core/) so the browser can load
// them, and Laravel's @vite must read the manifest from the matching location.
//
//   publicDirectory: '..'   -> outDir = <project-root>/build, hotFile = <root>/hot
//   buildDirectory:  'build' -> public URL base = '/build/'  (served at web root)
//
// App\Providers\CpanelPublicPathServiceProvider rebinds public_path() to the
// project root so Laravel reads <root>/build/manifest.json and emits /build/* URLs.
// See core/docs/INERTIA-VUE-FOUNDATION.md.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            publicDirectory: '..',
            buildDirectory: 'build',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    build: {
        // outDir (<project-root>/build) sits outside the Vite root (core/); Vite will
        // not clean an out-of-root directory unless this is explicitly allowed.
        emptyOutDir: true,
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
