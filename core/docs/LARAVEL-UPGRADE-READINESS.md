# Laravel Upgrade Readiness (B8)

## Bottom line

The application is **already on the latest Laravel major (13)**. There is no
major-version upgrade to perform. composer is healthy; only a handful of safe
minor/patch dependency bumps are available. Action reduces to an optional,
isolated minor/patch refresh (B9 dry-run), not a framework migration.

## Current state (verified)

| Item | Value |
|------|-------|
| `laravel/framework` (installed) | `v13.16.1` |
| `composer.json` constraint | `^13.0` (matches installed) |
| PHP | 8.3.31 (`composer.json` requires `^8.3`) |
| `composer validate` | `./composer.json is valid` |
| Latest `laravel/framework` on Packagist | 13.x (13.16.1) — **no v14 published** |

Other first-party packages: `laravel/sanctum ^4.0`, `laravel/socialite ^5.6`,
`laravel/tinker ^3.0`, `laravel/ui ^4.5`, `laravel/pail`, `laravel/pint`,
`phpunit/phpunit ^12.5`. All current for the Laravel 13 line.

## Outdated dependencies (from `composer outdated`, network-live)

Safe minor/patch (update recommended):
- `guzzlehttp/guzzle` 7.12.0 → 7.12.1 (direct; patch)
- `guzzlehttp/psr7` 2.12.0 → 2.12.1 (transitive; patch)
- `nesbot/carbon` 3.12.3 → 3.13.0 (transitive; minor)
- `ramsey/uuid` 4.9.2 → 4.9.3 (transitive; patch)

Major available but **parent-constrained — do not force-bump**:
- `brick/math` 0.14.8 → 0.18.0, `hamcrest/hamcrest-php` 2.1.1 → 3.0.0 (test-only),
  `spatie/error-solutions` 1.1.3 → 2.0.5, `spatie/flare-client-php` 1.11.1 → 3.1.0.
  These are pulled transitively (Laravel / ignition / mockery) and their versions
  are dictated by those parents; bumping them independently is not appropriate.

## Framework-integration surface (for a FUTURE major, when L14 ships)

The app already uses the modern Laravel 11+ skeleton, which de-risks a future major:

- **Bootstrap**: `bootstrap/app.php` uses `Application::configure()` with a custom
  `using:` routing closure, `withMiddleware` (CSRF exemptions for
  `user/deposit`, `ipn*`, `payment/initiate`, …), global `checkProject`
  (laramin/utility license) + `maintenance` middleware, and `preventRequestForgery`.
  A future major's middleware/CSRF API is the most likely break point — re-verify
  the exemption list and the `using:` closure first.
- **Providers**: `AppServiceProvider::boot()` registers named rate limiters
  (`login`/`otp`/`password-reset`/`registration`), view composers, and (now
  console-guarded) settings reads. Standard API; low risk.
- **Auth**: multi-guard (users/admins/agents/merchants) on Sanctum 4 + `laravel/ui`
  4. Sanctum/ui majors usually accompany a framework major — bump together.
- **Routing/validation/queues/mail/filesystem/helpers/config**: no deprecated
  helper usage found that blocks a minor refresh; full deprecation scan belongs to
  the actual L14 upgrade when it exists.
- **Third-party watch item**: `laramin/utility` (obfuscated license client calling
  `license.viserlab.com`) is the dependency most likely to lag a future Laravel
  major and is not under our control — confirm vendor compatibility before any
  major jump.

## Recommendation

1. **No major upgrade** — already current; nothing to do until Laravel 14 exists.
2. Optionally apply the safe **minor/patch** bumps (guzzle/psr7/carbon/uuid) via the
   isolated **B9 dry-run** branch, then full gate + route boot before considering a
   real lock update. Never merged automatically.
3. Do **not** force the parent-constrained transitive majors.
4. Re-run this readiness check when a new Laravel major is published; start the real
   upgrade from the middleware/CSRF and `laramin/utility` compatibility checks above.
