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
- `Feature/DepositIdempotencyTest.php` — skipped placeholder for Phase 3
  (needs a seeded test DB + the transaction/idempotency refactor).

## Notes
- The application schema lives in the SQL dump, not in migrations, so feature
  tests that touch the database require a seeded test database (out of scope for
  the current hardening pass). The tests above intentionally avoid DB access.
- Do not point tests at the development `paytabz_DB`; use a disposable test DB.
