# Tests

Minimal test scaffold added during local hardening.

## Running
The shipped `core/vendor/` is a production install (`--no-dev`), so PHPUnit is
not present. To run these tests, first install dev dependencies:

```bash
cd core
composer install            # restores require-dev (phpunit, mockery, ...)
php artisan test            # or: vendor/bin/phpunit
```

## Current tests (DB-free by design)
- `Unit/VerifyCronTokenTest.php` — `/cron` token middleware (Phase 1B): allows when
  no secret is set; 403 when a secret is set and the token is missing/wrong; allows
  with the correct header or query token.
- `Feature/OperationalRoutesTest.php` — route registration only (no HTTP/DB):
  public `GET /clear` is removed; `GET /cron` exists and is guarded by
  `VerifyCronToken`; home route (`GET /`) is registered.
- `Feature/RateLimitRoutesTest.php` — route-collection inspection (Phase 5/F):
  the login / API-login / API-password-reset / OTP-resend routes carry their
  `throttle:<name>` middleware.
- `Feature/RateLimiterDefinitionTest.php` — resolves the named limiters from
  `AppServiceProvider` against a fake `Request` (Phase 11): `login` = 10/min,
  `otp` and `password-reset` = 6/min, each keyed per client. Catches a renamed
  limiter that `RateLimitRoutesTest` (string-only) would miss.
- `Feature/AdminAuditLogTest.php` — admin audit-log middleware behavior.
- `Feature/BlockchainIpnTest.php` — skipped placeholder for Phase 2 IPN
  authenticity (needs a seeded test DB with a configured Blockchain gateway).
- `Feature/DepositIdempotencyTest.php` — skipped placeholder for Phase 3
  (needs a seeded test DB + the transaction/idempotency refactor).

## Notes
- The application schema lives in the SQL dump, not in migrations, so feature
  tests that touch the database require a seeded test database (out of scope for
  the current hardening pass). The tests above intentionally avoid DB access.
- Do not point tests at the development `paytabz_DB`; use a disposable test DB.

## Seeding a disposable test DB (for the skipped DB-backed tests)
The skipped tests above need real rows. To enable them without touching
`paytabz_DB`:
1. Create an empty throwaway database, e.g. `marketcapx_test`.
2. Load the shipped schema into it from the SQL dump (the schema is not in
   migrations): import the dump into `marketcapx_test` only.
3. Point the test connection at it — either uncomment the `DB_CONNECTION` /
   `DB_DATABASE` lines in `phpunit.xml` for a SQLite `:memory:` run (schema must
   then be created some other way), or set `DB_DATABASE=marketcapx_test` in a
   test-only `.env.testing`.
4. Seed the minimal fixtures each test documents (e.g. a Deposit row + a gateway
   with a configured `secret`) and remove the `markTestSkipped(...)` guard.

Never run migrations/seeders against `paytabz_DB`, and never call real payment
gateway or IPN endpoints from tests — stub the HTTP layer.
