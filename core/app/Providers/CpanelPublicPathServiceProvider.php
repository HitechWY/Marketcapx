<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * F0 (Inertia/Vue foundation) — cPanel split path bridge.
 *
 * This project uses a CodeCanyon "split" layout: the web root is the PROJECT
 * ROOT (/index.php boots core/), while Laravel's base path is core/. By default
 * public_path() therefore resolves to core/public — a directory that is neither
 * web-served nor even present. That breaks Laravel's @vite manifest lookup,
 * which reads from public_path('build/manifest.json').
 *
 * This provider rebinds public_path() to the project root (one level above the
 * Laravel base path) so:
 *   - Laravel reads the manifest from <web-root>/build/manifest.json, and
 *   - @vite emits /build/* URLs that resolve at the web root.
 * Vite is configured to emit there (publicDirectory '..'); see vite.config.ts.
 *
 * SAFETY / SCOPE (verified in F0): public_path() has exactly one other consumer
 * in this codebase — config/filesystems.php 'links' (the `storage:link` map),
 * which is unused (no symlink exists and core/public does not exist). File
 * uploads / KYC documents / downloads use App\Lib\FileManager with CWD-relative
 * `assets/` paths, and public URLs use asset() (APP_URL-based) — neither depends
 * on public_path(). The rebind is therefore isolated and reversible: delete this
 * provider and its entry in bootstrap/providers.php to revert.
 */
class CpanelPublicPathServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // base_path() is core/; its parent directory is the project root (web root).
        $this->app->usePublicPath(dirname($this->app->basePath()));
    }
}
