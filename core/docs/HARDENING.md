# MarketCap X — Local Hardening & Operations Notes

Developer-facing notes from the local hardening pass on `work/autonomous-hardening`.
No secrets are included here. Detailed audit/evidence lives outside the repo.

## Local run (development)
- Stack: **Laravel 13.3.0** / PHP 8.3 (confirmed from the installed `vendor/`). Framework lives in
  `core/`; web root is the project root.
- `php artisan serve` does NOT work (no `core/public`). Serve the project root with a PHP
  built-in server + a small router (kept outside the repo).
- DB: MySQL/MariaDB; the schema lives in the bundled SQL dump (54 tables), not in migrations.

## Repo-health findings (action for maintainers)
- **Stale `composer.lock`.** The installed `vendor/` is **Laravel 13.3.0** and `composer.json`
  correctly declares `^13.0` — these AGREE. The committed **`composer.lock` is stale**: it pins an
  older **Laravel v11.47.0 / PHPUnit 10.5.60** stack that does NOT match the vendor/json. Because of
  this, `composer install` either aborts or (if forced from the lock) **downgrades and breaks** the
  app (Laravel 13 → 11 removes APIs the code uses, e.g. `Middleware::preventRequestForgery()`).
  Resolve deliberately by regenerating the lock for the real stack: run a controlled
  `composer update` (Laravel 13) in a dev environment with `ext-gmp` enabled, review the resulting
  lock, and commit it. Do NOT install from the current stale lock. (Note: an earlier doc revision
  in this branch mis-stated the stack as Laravel 11 — that was based on the stale lock; the vendor is
  Laravel 13.3.0.)

## Cron endpoint (`GET /cron`)
- Guarded by `App\Http\Middleware\VerifyCronToken` and **fail-closed**: if `CRON_SECRET` is unset,
  `/cron` returns 403 and no jobs run.
- To enable scheduled jobs, set `CRON_SECRET` in `core/.env` and have the scheduler call
  `/cron` with the token via the `X-CRON-TOKEN` header or `?token=` query parameter.

## Rate limiting
- Named limiters in `AppServiceProvider`: `login` (10/min/IP), `otp` (6/min/IP),
  `password-reset` (6/min/IP), applied to the login, OTP, and password-reset routes across
  web (user/admin/agent/merchant) and API guards.

## Admin audit log
- `App\Http\Middleware\AdminAuditLog` writes **metadata-only** entries (no request bodies, no secrets)
  for state-changing admin requests to `storage/logs/admin-audit.log` (on-demand daily channel).
- This is a stop-gap; a DB-backed audit log + role/permission RBAC are planned (need schema).

## Tests
- A scaffold exists under `core/tests/` (DB-free unit/route tests + skipped DB-dependent stubs).
- **Not yet runnable here**: PHPUnit is absent from the production `vendor/`, and the stale lock
  blocks a clean dev install (installing from the lock downgrades to Laravel 11 and breaks boot).
  After regenerating the lock for Laravel 13 (`composer update` with `ext-gmp`), run:
  `cd core && composer install && php artisan test` (use a disposable copy DB, never `paytabz_DB`).

## Payment gateway IPNs
- Blockchain IPN was hardened (configured secret via `hash_equals`, fail-closed, no pre-validation
  persistence). Other gateways were categorized; several rely on server-side API verification or
  HMAC signatures and need per-gateway review with sandbox credentials before further changes.

## Database integrity
- See `core/database/migrations-drafts/` for non-executed drafts (wallets uniqueness, indexes) and
  the FK strategy notes (polymorphic ownership caveat). Validate on a COPY DB before applying.

## Hard boundaries respected during hardening
- No deploy, no push, no remote, no DB mutation, no migrations run, no secrets committed.
