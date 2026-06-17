# Tests

Minimal test scaffold added during local hardening.

## Running
PHPUnit is now available (require-dev was restored when `composer.lock` was
regenerated for the Laravel 13 stack — see `docs/DEPENDENCY-LOCK-RECOVERY.md`).

```bash
cd core
cp .env.testing.example .env.testing   # then set a throwaway APP_KEY + local DB
APP_ENV=testing vendor/bin/phpunit     # run the suite
```

Notes specific to this app:
- **Run phpunit directly, not `php artisan test`.** `artisan` first boots the
  framework in the *console* env (using `.env`), and `AppServiceProvider::boot()`
  queries `module_settings` at boot — so `artisan test` crashes before tests run
  if the `.env` DB is unreachable. `vendor/bin/phpunit` boots with
  `APP_ENV=testing` and loads `.env.testing` instead.
- This PHP build needs **ext-gmp** at runtime (a gateway SDK requires it). If it
  is not enabled in `php.ini`, run with `php -d extension=gmp vendor/bin/phpunit`.
- App boot needs a DB with at least the `module_settings` / `general_settings`
  tables, so even the "DB-free" tests require the test DB connection below.

### Disposable test DB (`marketcapx_test` — never `paytabz_DB`)
The schema lives in the SQL dump, not in migrations. Seed a throwaway DB once:
```bash
mysql -h127.0.0.1 -uroot -e "DROP DATABASE IF EXISTS marketcapx_test; CREATE DATABASE marketcapx_test CHARACTER SET utf8mb4;"
mysql -h127.0.0.1 -uroot marketcapx_test < ../paytabz_DB.sql   # dump has no USE/CREATE DATABASE; lands in marketcapx_test only
```
Point `.env.testing` at `marketcapx_test`. Never run this against `paytabz_DB`.

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
- `Feature/DepositIdempotencyTest.php` — **implemented** (DB-backed): the deposit
  credit path is idempotent — reprocessing a SUCCESS deposit is a no-op (one
  credit, one ledger row).
- `Feature/BlockchainIpnTest.php` — still skipped: re-verifies the callback
  against the external blockchain.info API, which would need an HTTP mock; left
  skipped to honor "no real gateway calls".

## DB-backed money-flow tests (`Feature/MoneyFlow/`)
These use the `Tests\Concerns\CreatesMoneyFixtures` builders, the
`DatabaseTransactions` trait (rolled back per test against `marketcapx_test`),
and drive the controller `*Done/Confirm` methods directly with auth + session
set up by hand (no real gateway calls):
- `VoucherFlowTest` — redeem/create double-submit → exactly one effect.
- `MakePaymentFlowTest` / `ApiMakePaymentFlowTest` — payment double-submit → one
  payer debit + one merchant credit.
- `MoneyOutFlowTest` / `MoneyInFlowTest` — double-submit → one move + commission.
- `MoneyExchangeFlowTest` — double-submit cannot overdraft (lock + balance re-check).
- `WithdrawalRejectFlowTest` — double reject → one refund.
- `AdminBalanceFlowTest` — add/sub ledger; subtract beyond balance is a no-op.
- `WalletAtomicityTest` — exception after `WalletService::credit` inside a
  transaction rolls back balance + ledger.

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
