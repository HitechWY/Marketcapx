# Backend Overnight Sprint — Baseline & Risk Map

> Status doc for the post-frontend backend-hardening / Laravel-readiness sprint (B0–B10).
> Created at sprint start; superseded at sprint end by `OVERNIGHT-BACKEND-SPRINT-REPORT.md`.

## 1. Confirmed baseline (read-only)

| Item | Value |
|------|-------|
| `origin/master` | `ed27f82` (merge: document R6 admin componentization deferral) |
| local `master` | `ed27f82` — clean tree, 0/0 vs origin |
| Remote | `git@github.com:HitechWY/Marketcapx.git` |
| PHP | 8.3.31 (ZTS, ext-gmp loaded per-invocation) |
| Laravel | `laravel/framework v13.16.1` (composer.lock); `composer.json` requires `^13.0` |
| PHPUnit | 12.5.30 (`composer.json` `^12.5`) |
| Test suite | **42 passed / 1 skipped** (170 assertions) against `marketcapx_test` |
| Route boot | **796 routes** under `APP_ENV=testing` |
| Test DB | `marketcapx_test` (root@127.0.0.1, MariaDB 11.4.5), 54 tables, seeded from dump |
| Prod-like DB | `paytabz_DB` — 54 tables, **read-only / untouched** for the entire sprint |

Verified test command (the only supported one):

```bash
APP_ENV=testing php -d extension=gmp vendor/phpunit/phpunit/phpunit
APP_ENV=testing php -d extension=gmp artisan route:list   # route boot
```

`php artisan test` is NOT used — console boot runs `AppServiceProvider::boot()` which queries the
configured DB (see §3, item *boot-isolation*).

## 2. Prioritized backend risk map

Ordered highest-value/highest-confidence first. Each maps to a sprint phase.

| # | Area | Phase | Current state on master | Planned action |
|---|------|-------|-------------------------|----------------|
| 1 | **Escrow atomicity** | B1 | `Admin/EscrowController` + `User/EscrowController` have **0** `DB::transaction`/`lockForUpdate`. Last money flow still un-hardened (all others hardened in prior waves). | Cherry-pick the 3 escrow commits from `work/escrow-atomicity`; verify tx+lock+re-check; tests for double dispatch/dispute-resolve. |
| 2 | **Boot / test isolation** | B2 | `AppServiceProvider::boot()` issues DB queries at boot (`ModuleSetting::get()` :77, `AdminNotification…->get()` :111), so console/`artisan test` needs a reachable DB. | Guard boot-time view-share/admin queries for `runningInConsole()`/testing without changing web behavior. |
| 3 | **Exchange wallet scope** | B3 | Money-exchange flows hardened for atomicity already; target-wallet scoping not yet re-audited. | Tests capturing scope; scoped validation only if confidently correct. |
| 4 | **API idempotency / replay** | B4 | Web + API money flows consume one-shot UserAction; broader API replay surface (deposit/hosted) not re-audited. | Duplicate-reference/replay tests; narrow safeguards preserving response shapes. |
| 5 | **KYC / PII exposure** | B5 | Upload dirs gitignored; existing filename entropy / public-access assumptions not hardened. | Doc risk; raise entropy for **new uploads only**; no existing-file migration. |
| 6 | **Gateway residuals** | B6 | Most IPN handlers hardened (Skrill/Cashmaal/Blockchain fixed; detail-persist moved post-verify in 6 handlers). Coinpayments + residuals + exception-exposure not finished. | Local-safe exception cleanup + docs + creds-required list; no HMAC change without verification. |
| 7 | **Model / Eloquent quality** | B7 | Mass-assignment risk (`$guarded=[]` on Agent/Merchant), sensitive fields not all `$hidden`, casts uneven. | `MODEL-AUDIT.md`; safe `hidden`/casts/phpdoc/scopes only — no schema/money change. |
| 8 | **Laravel upgrade readiness** | B8/B9 | Already on **latest major (13)**, `v13.16.1`. | Readiness doc; `composer validate`/`outdated`; isolated minor/patch dry-run (B9, never merged). |
| — | Admin RBAC | (deferred) | Flat admin model; RBAC migration is a **draft only**, not enforced; `AdminAuditLog` covers the admin group. | Out of scope this sprint (needs schema migration on prod-like DB). Documented, not implemented. |

## 3. Corrections to the original sprint assumptions

1. **Laravel is already on the latest major (13).** The prompt framed B8/B9 as "upgrade to the
   latest major." There is no newer major to jump to (`v13.16.1` installed). B8/B9 are therefore
   reframed to: confirm currency, enumerate minor/patch deltas, assess any future-major surface, and
   dry-run only safe minor/patch bumps in isolation. **No major upgrade is performed; B9 is never merged.**

2. **`work/escrow-atomicity` must NOT be merged as a branch.** It was branched before the
   Material/rebuild merges (merge-base `8d74c3f`); its diff vs current `ed27f82` *deletes* all frontend
   CSS/components (≈3804 deletions). Its escrow value is isolated to exactly **3 commits**
   (`2b3f735` tests, `f6c0a7b` lock dispatch/dispute, `b9fb2f9` doc) — these are **cherry-picked** onto
   a fresh branch off current master; the stale branch itself is left as-is for the record.

3. **Boot fragility is precisely located** at `core/app/Providers/AppServiceProvider.php` lines 77 and
   111 (boot-time DB queries feeding the global view-share), which is why the documented PHPUnit command
   works (web-kernel path is not hit the same way) while `artisan test`/`route:list` need a reachable DB.

## 4. Safety envelope (whole sprint)

No deploy · no force-push · no history rewrite · no `paytabz_DB` mutation or migration · all tests/DB
work against `marketcapx_test` only · `core/.env*` never staged · no secrets/PII/artifacts committed ·
small reversible branches with the standard green gate before any merge · each merge re-checks
`origin/master` first and hard-stops if it moved.
