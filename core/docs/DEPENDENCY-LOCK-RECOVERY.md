# Dependency Lock Recovery Plan (Phase 10)

Controlled plan to bring `core/composer.lock` back into agreement with `composer.json`
and the installed `vendor/`. **No `composer update` has been run** as part of producing
this document — it is a plan only. Do not install from the current stale lock.

## 1. The mismatch (verified 2026-06-17)

Three sources of truth currently disagree:

| Source                         | Laravel framework | Notes                                  |
| ------------------------------ | ----------------- | -------------------------------------- |
| `composer.json` (`require`)    | `^13.0`           | Intended stack                         |
| `vendor/composer/installed.json` | `v13.3.0`        | What is actually installed and running |
| `composer.lock`                | `v11.47.0`        | **Stale** — written 2026-01-10         |

`composer.json` and the installed `vendor/` **agree** on Laravel 13. The committed
`composer.lock` is the lone stale artifact: it pins a Laravel 11 era stack.

Lock metadata: `content-hash d027ed3d249ef80276f18b8ca0d91956`, `plugin-api-version 2.6.0`,
`platform.php ^8.3` (the platform constraint is fine; the package set is not).

### Packages where the lock lags the installed/declared stack

| Package               | Lock pins   | Installed / declared | Severity        |
| --------------------- | ----------- | -------------------- | --------------- |
| laravel/framework     | v11.47.0    | v13.3.0              | **major (×2)**  |
| laravel/tinker        | v2.11.0     | v3.0.0 (json `^3.0`) | **major**       |
| stripe/stripe-php     | v13.18.0    | v20.0.0 (json `^20`) | **major (×7)**  |
| intervention/image    | 3.11.6      | 4.0.0 (json `^4.0`)  | **major**       |
| laravel/sanctum       | v4.2.2      | v4.3.1               | minor           |
| guzzlehttp/guzzle     | 7.10.0      | 7.10.0               | matches         |
| phpunit/phpunit       | 10.5.60     | (dev not installed)  | json wants `^12.5.12` |
| nunomaduro/collision  | v8.5.0      | (dev not installed)  | json wants `^8.6` |

The dev block in `composer.json` (`phpunit ^12.5.12`, `collision ^8.6`,
`laravel/pint ^1.27`, etc.) is also newer than what the lock would resolve, so the lock
cannot satisfy the dev requirements either.

## 2. Why `composer install` from the current lock is unsafe

`composer install` is lock-authoritative: it would install the Laravel **11** package set
the lock names, **downgrading** the running app from 13.3.0. Code in this repo already uses
Laravel 13 APIs (e.g. `Middleware::preventRequestForgery()` in `bootstrap/app.php`), so a
downgrade removes methods the app calls and breaks boot. The major jumps for Stripe (13→20),
Tinker (2→3), and Intervention/Image (3→4) compound the breakage.

Equally, `composer update` is **not** a casual fix here: it crosses four major versions and
must be done deliberately in a throwaway environment, never against this working tree blind.

## 3. Controlled recovery procedure (run in a DEV environment, not here)

Goal: regenerate a lock that matches `composer.json` (Laravel 13) and the installed vendor,
review it, and commit only the lock.

1. **Snapshot first.** Copy the current `vendor/` and `composer.lock` somewhere safe so the
   running app can be restored verbatim if the update misbehaves.
2. **Match PHP + extensions.** Use PHP 8.3 (per `platform.php ^8.3`). Ensure the extensions
   the package set needs are enabled — notably **`ext-gmp`** (crypto/gateway SDKs),
   `ext-bcmath`, `ext-intl`, `ext-gd` (intervention/image v4), `ext-curl`, `ext-mbstring`.
3. **Dry run to see the resolution** without writing anything:
   ```
   composer update --dry-run
   ```
   Confirm `laravel/framework` resolves to `13.x` (not 14), and review every major bump.
4. **Regenerate the lock** in the dev environment:
   ```
   composer update
   ```
   If you want to hold transitive packages and only refresh the lock to the installed set,
   prefer `composer update --lock` first and inspect; fall back to a full update if the hash
   still will not reconcile.
5. **Review the diff** of `composer.lock` (versions, new/removed packages, content-hash).
   Pay attention to the Stripe 13→20 and Intervention 3→4 API surfaces if any app code
   touches them directly.
6. **Run the test suite** (see `tests/README.md`) against the regenerated vendor before trusting it.
7. **Commit only `composer.lock`** (and `composer.json` if a constraint was intentionally
   adjusted). Do **not** commit `vendor/` — it is gitignored.

## 4. Hard constraints honored by this plan

- No `composer update` / `composer install` was executed to produce this document.
- No production or server settings touched; recovery is prescribed for a dev environment.
- `vendor/` remains gitignored and uncommitted.

## 5. Quick verification commands (read-only)

```
grep -m1 -A1 '"name": "laravel/framework"' composer.lock                 # -> v11.47.0 (stale)
grep -m1 -A1 '"name": "laravel/framework"' vendor/composer/installed.json # -> v13.3.0 (real)
```

See also `docs/HARDENING.md` §"Repo-health findings" for the short-form summary.
