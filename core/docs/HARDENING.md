# MarketCap X — Local Hardening & Operations Notes

Developer-facing notes from the local hardening pass on `work/autonomous-hardening`.
No secrets are included here. Detailed audit/evidence lives outside the repo.

## Local run (development)
- Stack: **Laravel 11.47** / PHP 8.3. Framework lives in `core/`; web root is the project root.
- `php artisan serve` does NOT work (no `core/public`). Serve the project root with a PHP
  built-in server + a small router (kept outside the repo).
- DB: MySQL/MariaDB; the schema lives in the bundled SQL dump (54 tables), not in migrations.

## Repo-health findings (action for maintainers)
- **`composer.json` ↔ `composer.lock` mismatch.** `composer.json` declares Laravel ^13 / PHPUnit ^12,
  but the lock + installed `vendor/` are **Laravel v11.47.0 / PHPUnit 10.5.60** (and several other
  packages differ). Because of this, `composer install` aborts (lock does not satisfy json).
  Resolve deliberately — either correct `composer.json` constraints to match the intended/locked
  stack, or run a controlled `composer update` as a real upgrade project. Do not paper over it.

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
- **Not yet runnable here**: PHPUnit is absent from the production `vendor/`, and the composer
  mismatch above blocks `composer install`. After resolving the mismatch, run:
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
