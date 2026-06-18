# Dependency Dry-Run Result (B9)

> Branch `work/laravel-upgrade-dry-run` — **isolated, NOT merged**. Composer
> changes are confined to this branch. A human reviews and decides whether to
> land the lock update.

## What was done

1. `composer update --dry-run` for the four safe minor/patch packages identified in
   `LARAVEL-UPGRADE-READINESS.md`. Result: `0 installs, 4 updates, 0 removals` — no
   Laravel change, no removals, no surprises.
2. Backed up `composer.lock` (to `%TEMP%\composer.lock.b9-backup`) and ran the real
   scoped update (ext-gmp enabled per-invocation). The post-update artisan scripts
   (`package:discover`, `vendor:publish`) ran cleanly — notably **without a DB
   connection error**, confirming the B2 console-boot guard.

## Lock delta (exactly four packages)

| Package | From | To | Type |
|---------|------|----|------|
| `guzzlehttp/guzzle` | 7.12.0 | 7.12.1 | patch |
| `guzzlehttp/psr7` | 2.12.0 | 2.12.1 | patch |
| `nesbot/carbon` | 3.12.3 | 3.13.0 | minor |
| `ramsey/uuid` | 4.9.2 | 4.9.3 | patch |

`laravel/framework` unchanged at `v13.16.1`. No other lock entries moved.
`composer.json` is **not** modified. `composer audit`: no advisories.

## Verification

- PHPUnit: **53 passed / 1 skipped** (unchanged from master).
- `artisan route:list`: **796 routes**.
- `composer validate`: valid.

## Breaking changes / required fixes

**None.** The update is a clean minor/patch refresh with no code changes required.

## Status

Merge-ready but **intentionally not merged** (B9 is never auto-merged). To land it:
review this branch, then fast-forward/merge `work/laravel-upgrade-dry-run` into
master and run `composer install` on deploy targets. The parent-constrained
transitive majors (brick/math, hamcrest, spatie/*) were deliberately left alone.
