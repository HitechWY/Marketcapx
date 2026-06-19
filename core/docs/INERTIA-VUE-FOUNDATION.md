# F0 — Inertia + Vue 3 Foundation

Status: **complete, gates green.** This document records the foundation added in
`frontend/inertia-vue-foundation`. No production page was migrated; the legacy Blade
frontend is untouched and remains the live UI for every existing route.

## What F0 adds

A modern frontend stack mounted alongside the existing Blade app, proven by one isolated
proof-of-concept page. The Laravel backend (routes, controllers, validation, guards,
sessions, CSRF, captcha, wallet/payment/KYC/admin logic, DB) is unchanged.

Stack: **Inertia.js + Vue 3 + TypeScript + Vite 5 + Tailwind CSS v4**, single Laravel deployable.

## The cPanel split & the Vite path decision (Option A)

This repo uses a CodeCanyon "split": the **web root is the project root** (`/index.php`
boots `core/`), while Laravel's base path is `core/`. By default `public_path()` resolves
to `core/public` — not web-served, and not present — which breaks Laravel's `@vite`
manifest lookup.

**Decision: Option A — rebind `public_path()` to the project root.** Chosen because
inspection proved `public_path()` is low-risk here:

- The **only** functional `public_path()` consumer in the codebase is
  `config/filesystems.php` `links` (the `storage:link` map), which is **unused** (no
  symlink exists; `core/public` does not exist).
- Uploads / KYC / downloads use `App\Lib\FileManager`, which writes via **CWD-relative
  `assets/` paths** (`move()`/`mkdir()`/`save()`) — independent of `public_path()`.
- Public URLs use `asset()`, which is **APP_URL-based** — independent of `public_path()`.

The rebind lives in `app/Providers/CpanelPublicPathServiceProvider.php` (registered in
`bootstrap/providers.php`). It is **isolated and reversible**: delete the provider and its
registration to revert.

### Path chain (verified end-to-end)

| Concern | Value |
|---|---|
| Vite root | `core/` |
| `laravel()` `publicDirectory` | `..` → emits into `<web-root>/build` |
| `laravel()` `buildDirectory` | `build` → public URL base `/build/` |
| Vite output | `<web-root>/build/{manifest.json, assets/*}` |
| Manifest (Vite 5) | forced to `manifest.json` by laravel-vite-plugin (not `.vite/`) |
| Laravel reads | `public_path('build/manifest.json')` = `<web-root>/build/manifest.json` |
| Browser loads | `/build/assets/app-*.{js,css}` → served from web root |

`<web-root>/build` and `<web-root>/hot` are gitignored (root `.gitignore`).

## Files

New:
- `core/vite.config.ts` — Vite + `@vitejs/plugin-vue` + `@tailwindcss/vite` + `laravel` plugin (cPanel paths).
- `core/tsconfig.json`, `core/resources/js/shims-vue.d.ts` — TypeScript / `.vue` SFC types.
- `core/resources/css/app.css` — Tailwind v4 entry + MD3 token bridge (ported subset; light `:root`, dark `[data-md-theme="dark"]`).
- `core/resources/js/app.ts` — Inertia client entry (page resolver + Ziggy).
- `core/resources/js/Pages/Poc/Hello.vue` — PoC page.
- `core/app/Providers/CpanelPublicPathServiceProvider.php` — `public_path()` rebind.
- `core/app/Http/Middleware/HandleInertiaRequests.php` — Inertia handler (web group only; inert for Blade).
- `core/resources/views/app.blade.php` — Inertia root view (`@routes` + `@vite` + `@inertia`, MD3 pre-paint theme).

Changed (additive):
- `core/composer.json` / `composer.lock` — `inertiajs/inertia-laravel ^3.1`, `tightenco/ziggy ^2.6`.
- `core/package.json` / `package-lock.json` — vue, @vitejs/plugin-vue, @inertiajs/vue3, typescript, vue-tsc, tailwindcss, @tailwindcss/vite, ziggy-js.
- `core/bootstrap/providers.php` — register the public-path provider.
- `core/bootstrap/app.php` — append `HandleInertiaRequests` to the **web** group only (API/IPN untouched).
- `core/routes/web.php` — one isolated PoC route.
- root `.gitignore` — ignore `/build/` and `/hot`.

## PoC

- Route: **`GET /poc/inertia`** (`poc.inertia`), guest-accessible, no DB, no business logic. Remove at F8.
- Proves: Vue mounts, TypeScript compiles (typed Inertia props), Tailwind utilities apply,
  MD3 token colours + light/dark theme toggle work, Laravel→Inertia props flow, and the Vite
  manifest resolves (`/build/assets/...`).

## How to build / run

```bash
# build (from core/)
npm run build            # emits <web-root>/build/{manifest.json, assets/*}

# type-check
npx vue-tsc --noEmit

# tests (gmp loaded via -d; the lock requires ext-gmp)
APP_ENV=testing php -d extension=gmp vendor/phpunit/phpunit/phpunit
```

Composer must run with gmp enabled (the lock requires `ext-gmp`, which is not in the CLI
`php.ini` but loads via `-d`):
`php -d extension=gmp <composer.phar> ...`.

## Verification results (F0)

| Gate | Result |
|---|---|
| `npm run build` | ✅ 798 modules; manifest + assets at `<web-root>/build` |
| `vue-tsc --noEmit` | ✅ exit 0 |
| PHPUnit | ✅ 53 tests / 216 assertions / 1 skipped — identical to pre-F0 baseline |
| `route:list` | ✅ 797 routes = 796 baseline + 1 PoC; no existing route changed |
| Smoke `/`, `/contact`, `/user/login` | ✅ 200, still Blade |
| Smoke `/admin` | ✅ 200, still Blade |
| Smoke `/user/dashboard`, `/merchant/dashboard` | ✅ 302 unauthenticated |
| Smoke `/poc/inertia` | ✅ 200, Inertia mount + `/build` asset resolves |
| `git diff --check` | ✅ clean |
| PHP lint (changed files) | ✅ no syntax errors |

## Known / accepted items (revisit later)

- **npm audit**: 2 advisories (esbuild/vite dev-server, GHSA-67mh-4wv8-2f99). **Dev-server
  only**; does not affect `npm run build` output. The only fix forces vite@8 (major break),
  out of F0 scope. Revisit in a later hardening slice (bump to vite 6/7 with matching plugins).
- **Ziggy `@routes`** currently exposes the full route table to the client. Route URIs are
  not secrets and admin routes stay auth-gated, but F1 should scope Ziggy (`only`/`except` or
  route groups) to trim payload and surface.
- **Inertia SSR** is intentionally not configured (cPanel has no Node SSR runtime). Client-only.

## Reverting F0

Delete `CpanelPublicPathServiceProvider` + its registration, remove the Inertia middleware
line in `bootstrap/app.php`, the PoC route, and the new resources/config files; `composer
remove inertiajs/inertia-laravel tightenco/ziggy`; drop the npm devDependencies. The legacy
Blade app is unaffected throughout.
