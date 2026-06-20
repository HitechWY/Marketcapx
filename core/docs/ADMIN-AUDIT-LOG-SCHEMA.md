# Admin Audit-Log Schema (F10a)

Schema for the DB-backed admin audit-log foundation:
`database/migrations/2026_01_01_000004_create_admin_audit_logs.php` → table **`admin_audit_logs`**.
Append-only, additive, backward-compatible. Companions: `ADMIN-AUDIT-LOG-READINESS.md` (F8c, the gap
this closes), `ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md` (F8d), `RBAC-SCHEMA.md` (sibling pattern).

## Table `admin_audit_logs`

| column | type | notes |
|---|---|---|
| id | bigIncrements | |
| admin_id | unsignedBigInteger nullable, **indexed** | acting admin; no FK (admins ship in the dump; integrity at app layer) |
| admin_username | string nullable | |
| action | string, **indexed** | event taxonomy (see action coverage doc) |
| permission_slug | string nullable, **indexed** | set for RBAC decision rows |
| route_name | string nullable, **indexed** | |
| method | string(10) | HTTP verb |
| path | string | request path (no query string) |
| entity_type | string nullable | inferred from route (F10d/e) |
| entity_id | unsignedBigInteger nullable | inferred from `{id}` route param |
| result | string, **indexed** | `allowed` / `would_deny` / `denied` / `succeeded` / `failed` |
| http_status | unsignedSmallInteger nullable | |
| ip | string(45) nullable | IPv4/IPv6 |
| user_agent | string nullable | truncated by the logger |
| correlation_id | string(64) nullable, **indexed** | request correlation id |
| before_values | json nullable | **reserved** — null in F10; populated by F14+ mutation hooks |
| after_values | json nullable | **reserved** — null in F10 |
| metadata | json nullable | sanitized, size-capped |
| error_code | string nullable | sanitized; never a stack trace or secret |
| created_at | timestamp nullable, **indexed** | append-only (no `updated_at`) |

Composite index: `(entity_type, entity_id)`.

## Indexes
`admin_id`, `action`, `permission_slug`, `route_name`, `result`, `correlation_id`, `created_at`,
and the composite `(entity_type, entity_id)` — sized for the common queries: "what did admin X do",
"all denied/would_deny", "history for entity Y", "actions on route Z over time".

## MariaDB JSON behavior
The target DB is **MariaDB 11.4**. `$table->json()` produces a `LONGTEXT` column (MariaDB's `JSON`
is an alias for `LONGTEXT` with a `JSON_VALID` check). The `AdminAuditLog` model casts
`before_values` / `after_values` / `metadata` to `array`, so reads/writes are normal arrays. No
separate longtext fallback column is required.

## Append-only
There is no `updated_at` and the model never updates rows — audit records are write-once. Edits or
deletions, if ever needed for privacy/retention, happen via the prune task below, not in-app updates.

## Rollback
`php artisan migrate:rollback` runs `down()` → `Schema::dropIfExists('admin_audit_logs')`. The table
is standalone (no FKs in or out), so dropping it is isolated and safe.

## Applying to the test DB (`marketcapx_test` — allowed)
```bash
# disposable copy only — never paytabz_DB
APP_ENV=testing php -d extension=gmp artisan migrate --path=database/migrations/2026_01_01_000004_create_admin_audit_logs.php
```
Audit tests create rows under `DatabaseTransactions` (rolled back) — nothing persists.

## Applying to a real DB (owner-gated — do NOT run without approval)
Running this migration against dev/staging/prod (esp. `paytabz_DB`) requires: explicit owner
approval, a fresh verified backup, and a maintenance window. The migration ships in
`database/migrations/` but its real-DB run is owner-gated, exactly like the RBAC migration.

## Retention / pruning (future ops task — NOT in F10)
The table is append-only and will grow. A future scheduled prune (e.g. delete rows older than N days,
or archive) should be added as an ops task with its own approval. F10 does not add a scheduler.

## Status after F10a
Migration added and finalized; **not run** against any DB in F10a (lint/static inspection only).
Model + logger service (F10b), then middleware wiring (F10c/d) follow.
