# MarketCap X — Local Developer Setup

Developer-facing setup notes. **No real secrets appear here.** Production
deployment is out of scope.

## Stack
- **Laravel 13.x / PHP 8.3** (framework lives in `core/`; the web root is the
  project root, not `core/public`).
- MySQL/MariaDB. The application schema lives in the **SQL dump**, not in
  migrations (migrations exist only as drafts under
  `core/database/migrations-drafts/`).

## PHP extensions
The app requires **`ext-gmp`** at runtime (a gateway SDK depends on it). If your
PHP build ships `php_gmp.dll`/`gmp.so` but it is not enabled in `php.ini`, you
can enable it per-invocation for CLI/composer work:
```
php -d extension=gmp <command>
```
Otherwise enable `extension=gmp` in the CLI and serving `php.ini`.

## Install dependencies
```
cd core
php -d extension=gmp <composer.phar> install     # or: composer install (if gmp enabled)
```
`composer.lock` is committed and pinned to the Laravel 13 stack — do **not**
regenerate it casually (see `docs/DEPENDENCY-LOCK-RECOVERY.md`).

## Local run (127.0.0.1:1000)
`php artisan serve` does **not** work here (there is no `core/public`). Serve the
project root with PHP's built-in server plus a tiny router that points the
front controller at `index.php` (router kept outside the repo). Use
`127.0.0.1:1000` for smoke tests and stop the server afterward.

## Environment
- `core/.env` holds the real local config and is **gitignored** — never commit it.
- `core/.env.example` is the base template; `core/.env.testing.example` is the
  testing template (copy to `core/.env.testing`, also gitignored).
- Required keys for boot: `APP_KEY` (run `php artisan key:generate`), the `DB_*`
  connection, and a reachable DB with at least the `module_settings` /
  `general_settings` tables (queried at boot by `AppServiceProvider`).

## Cron endpoint (`GET /cron`)
Guarded by `App\Http\Middleware\VerifyCronToken`, **fail-closed**: if
`CRON_SECRET` is unset, `/cron` returns 403 and no jobs run. Set `CRON_SECRET`
in `core/.env` and have the scheduler call `/cron` with the token via the
`X-CRON-TOKEN` header or `?token=` query parameter.

## Tests
PHPUnit is available after `composer install`. Run **`vendor/bin/phpunit`**
(not `php artisan test`, which boots the console env against `.env` and queries
the DB at boot). Point `core/.env.testing` at a **disposable** DB
(`marketcapx_test`) — never `paytabz_DB`. Full instructions and the DB seed
steps are in `core/tests/README.md`.

## GitHub workflow
- Remote `origin` = `git@github.com:HitechWY/Marketcapx.git` (**private**).
- Day-to-day hardening happens on `work/overnight-hardening`; merge to `master`
  only after the test suite passes and the working tree is clean.
- Before every push: `git status --short`, `git diff --check`, and the
  forbidden-file `git ls-files` grep (no `.env`, `.sql`, `vendor/`, `node_modules`,
  runtime/cache/log/session files, keys/certs, PII uploads). The four Laravel
  `.gitignore` keepers under `storage/framework/*` and `storage/logs` are
  expected and safe.

## Security hardening reference
See `docs/HARDENING.md`, `docs/MONEY-FLOW-AUDIT.md`, `docs/GATEWAY-IPN-AUDIT.md`,
and `docs/RBAC-DESIGN.md`.
