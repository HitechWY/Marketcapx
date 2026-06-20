# Real-DB RBAC + Audit Schema Apply Runbook (F11)

**Status:** docs-only runbook. **Nothing here has been executed.** No migration has been run
against any real DB, no DB has been mutated, `config('rbac.enforce')` remains **`false`**
(allow-by-default), and admin mutation behavior is unchanged. Every real-DB command below is
**reference only** and **owner-gated** — do not run any of it against `paytabz_DB` (or any
dev/staging/prod DB) without the explicit owner approval gates in §9. The only freely-runnable
commands are the test-DB rehearsal in §5 against the disposable `marketcapx_test`.

Companions: `RBAC-SCHEMA.md`, `RBAC-SEEDING.md`, `RBAC-DENY-BY-DEFAULT-READINESS.md` (F9),
`ADMIN-AUDIT-LOG-SCHEMA.md`, `ADMIN-AUDIT-LOG-FOUNDATION.md`,
`ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`, `F10-ADMIN-AUDIT-LOG-FOUNDATION-REPORT.md`.

---

## Purpose

F9 (RBAC enforcement foundation) and F10 (admin audit-log foundation) shipped two additive
schema migrations that, to date, have been applied **only** to the disposable test DB
`marketcapx_test`. The remaining gap is operational: there is no owner-approved, step-by-step
procedure for applying these migrations to a real environment with a backup, maintenance
window, rollback path, and verification. This runbook closes that gap. It **prepares**; it does
not apply.

The two migrations in scope (and **only** these two):

- `database/migrations/2026_01_01_000003_create_rbac_roles_permissions.php` (RBAC, F9a)
- `database/migrations/2026_01_01_000004_create_admin_audit_logs.php` (audit, F10a)

---

## 1. Baseline status

| Item | Value | How confirmed |
|---|---|---|
| HEAD | `bd0854c` | `git rev-parse HEAD` |
| origin/master | `bd0854c` (== HEAD) | `git rev-parse origin/master` |
| Working tree | clean | `git status --porcelain` empty |
| Branch (planning) | `master` | `git branch --show-current` |
| Route count | **798** | `APP_ENV=testing php -d extension=gmp artisan route:list` |
| Test baseline | 165 passed / 1749 assertions / 1 skipped | F10 report + suite |
| `rbac.enforce` | **`false`** (default) | `config/rbac.php` (`RBAC_ENFORCE`, default false) |
| F9/F10 companion docs | all 7 present | `core/docs/` |

The locked commit for any apply is `bd0854c` (or its named successor, recorded at apply time).

---

## 2. Migration inspection summary

### RBAC — `2026_01_01_000003_create_rbac_roles_permissions.php`
- `up()` is additive: creates `roles`, `permissions`, `permission_role`; adds a **nullable,
  indexed** `admins.role_id` (guarded by `Schema::hasColumn`).
- Pivot FKs are internal and safe: `permission_role.role_id → roles.id` and
  `permission_role.permission_id → permissions.id`, both `cascadeOnDelete`; both sides are
  created in the same migration.
- `admins.role_id` is **FK-less by design** (the `admins` table ships in the SQL dump; an FK
  would couple to its exact engine/collation). Referential integrity is enforced at the app
  layer via `Admin::role()`. Nullable ⇒ existing admins keep legacy full access.
- No JSON columns.
- `down()` drops `admins.role_id` (guarded), then `permission_role`, `permissions`, `roles`.
  **This `admins.role_id` drop is the only schema-removing operation in either migration** —
  treat rollback as owner-gated (§7).

### Audit — `2026_01_01_000004_create_admin_audit_logs.php`
- `up()` is purely additive: `CREATE TABLE admin_audit_logs` only, with an early `return` if the
  table already exists (re-run safe). No existing table touched, **no foreign keys**.
- 8 indexes: `admin_id`, `action`, `permission_slug`, `route_name`, `result`,
  `correlation_id`, `created_at`, and composite `(entity_type, entity_id)`.
- 3 JSON columns: `before_values`, `after_values`, `metadata`. On MariaDB 11.4 `json()` maps to
  `LONGTEXT` (JSON is an alias); the `AdminAuditLog` model casts them to `array`. `before/after`
  remain null until F14+.
- `down()` is `Schema::dropIfExists('admin_audit_logs')` — isolated (no FKs in or out).

**Verdict:** both migrations are additive on `up()` and reversible; compatible with the
SQL-dump-managed schema (`paytabz_DB.sql`, which already ships the `admins` table). No
destructive `up()` operations.

---

## 3. Real-DB apply risk assessment

- **Schema drift** — `marketcapx_test` may differ from the real DB (columns, engine, collation).
  Rehearse on a fresh copy of the real schema before trusting timings/results.
- **`migrations` table state** — the real DB was built from a SQL dump, not Laravel migrations.
  The `migrations` table may be absent or out of sync, so a bare `php artisan migrate` could try
  to run **unrelated** pending migrations. *Mitigation:* always use explicit `--path` per file,
  and pre-check `migrations` table contents.
- **Missing/renamed `admins` table or column differences** — the RBAC migration's `hasColumn`
  guard assumes an `admins` table exists; verify before apply.
- **FK/index creation failure or partial apply** — a failure mid-`up()` can leave a subset of
  tables. *Mitigation:* backup first; rollback/restore plan ready (§7).
- **Long-running index creation / lock risk** — `admin_audit_logs` is empty at creation so index
  build is cheap; the `admins.role_id` add is a small ALTER but still locks `admins` briefly.
  Apply in a maintenance window.
- **Rollback after role assignment** — `down()` drops `admins.role_id`; rolling back after roles
  are assigned discards those assignments. Owner-gated.
- **Backup missing/unverified** — never apply without a fresh, **restore-tested** backup.
- **Seed/backfill mistakes** — running `rbac:install` is idempotent, but `--admin=<id>` targets a
  specific admin; a wrong id grants super-admin to the wrong account.
- **Accidental super-admin assignment** — only pass `--admin` with an owner-confirmed id.
- **Accidental `rbac.enforce=true`** — flipping enforcement changes admin behavior to
  deny-by-default and can lock admins out. Out of scope here; keep it `false`.
- **Audit table growth + privacy/retention** — `admin_audit_logs` is append-only and grows; it
  stores `ip`, `user_agent`, and sanitized `metadata` (potential PII). Retention/pruning is a
  separate owner-approved ops task (see `ADMIN-AUDIT-LOG-SCHEMA.md`).
- **Re-run safety** — audit `up()` early-returns if the table exists; RBAC seeding uses
  `updateOrCreate`/`firstOrCreate`/`syncWithoutDetaching` (idempotent).

---

## 4. Pre-apply checklist (required before ANY real-DB action)

- [ ] Explicit **owner approval** to apply (recorded).
- [ ] **Full DB backup** taken and its integrity verified.
- [ ] **Restore tested** (backup restored to a scratch DB successfully).
- [ ] **Maintenance window** scheduled and announced.
- [ ] **Commit hash locked** (`bd0854c` or its recorded successor); deployment is on that commit.
- [ ] **Clean working tree** on the target host.
- [ ] **No pending migrations beyond** the two in scope (verified via `migrate:status` / the
      `migrations` table).
- [ ] **`.env` reviewed by the owner only** — never printed, never echoed into logs/tickets.
- [ ] **DB name verified by the operator** (confirm the connection targets the intended real DB,
      not a copy and not the wrong environment).
- [ ] **`rbac.enforce=false`** confirmed in the target environment.
- [ ] **`php artisan down` decision documented** (whether to use maintenance mode) — documented
      here, **not executed** as part of F11.
- [ ] **Rollback plan ready** (§7) and the operator has read it.
- [ ] **Post-apply verification commands ready** (§8).

---

## 5. Test-DB rehearsal plan (`marketcapx_test` ONLY — safe to run)

All commands use `APP_ENV=testing` and target the disposable `marketcapx_test`. Nothing here
touches a real DB. Run from `core/`.

```bash
# (optional) reload the disposable test DB from the dump first, if a clean slate is wanted.
# This drops/recreates marketcapx_test ONLY — never paytabz_DB. Operator supplies credentials.
#   mysql -u <user> -p marketcapx_test < ../paytabz_DB.sql

# 1. Apply ONLY the two in-scope migrations, by explicit path (no other pending migration runs):
APP_ENV=testing php -d extension=gmp artisan migrate \
  --path=database/migrations/2026_01_01_000003_create_rbac_roles_permissions.php
APP_ENV=testing php -d extension=gmp artisan migrate \
  --path=database/migrations/2026_01_01_000004_create_admin_audit_logs.php

# 2. Seed RBAC (idempotent): 65 permissions + super-admin role. NO --admin on rehearsal.
APP_ENV=testing php -d extension=gmp artisan rbac:install
```

Verify (read-only checks against `marketcapx_test`):

- Tables exist: `roles`, `permissions`, `permission_role`, `admin_audit_logs`, and column
  `admins.role_id`.
- Indexes present per §2 (e.g. `SHOW INDEX FROM admin_audit_logs;`).
- Seed counts: **65** permissions, **1** `super-admin` role, **65** `permission_role` rows
  (super-admin gets all permissions).
- Audit write path works: an `admin_audit_logs` row is created by the logger during the RBAC/audit
  feature tests (rows are created under `DatabaseTransactions` and rolled back — nothing persists).

Run the suites:

```bash
# RBAC + audit tests, then the full suite:
APP_ENV=testing php -d extension=gmp vendor/phpunit/phpunit/phpunit
```

- Confirm no persistent unwanted rows remain after the suite (transactional rollback).
- Confirm route count is **798**:

```bash
APP_ENV=testing php -d extension=gmp artisan route:list   # expect 798 routes
```

---

## 6. Real-DB apply runbook (DOCUMENTED — do NOT execute without §9 gates)

Execute only after §4 is fully satisfied. Every step touching the real DB is **OWNER APPROVAL
REQUIRED**. Run from `core/` on the locked commit.

1. **Backup the DB.** Take a fresh dump of the real DB. *(OWNER APPROVAL REQUIRED)*
2. **Verify the backup.** Confirm the dump restores cleanly to a scratch DB.
3. **(Optional) Maintenance mode.** If the owner approves, `php artisan down` (documented here;
   decision per §4). *(OWNER APPROVAL REQUIRED)*
4. **Pre-check migration state.** Inspect the `migrations` table / `migrate:status` to confirm the
   two in-scope migrations are *not* already recorded and that no unrelated pending migration would
   be swept in. (Explicit `--path` in step 5 is the guard.)
5. **Run the two migrations by explicit path** *(OWNER APPROVAL REQUIRED)*:
   ```bash
   php artisan migrate --force \
     --path=database/migrations/2026_01_01_000003_create_rbac_roles_permissions.php
   php artisan migrate --force \
     --path=database/migrations/2026_01_01_000004_create_admin_audit_logs.php
   ```
6. **Seed RBAC** *(OWNER APPROVAL REQUIRED, only if approved)*:
   ```bash
   php artisan rbac:install
   ```
7. **Assign super-admin** to an **explicit, owner-confirmed** admin id *(OWNER APPROVAL REQUIRED,
   only if approved)*:
   ```bash
   php artisan rbac:install --admin=<OWNER_CONFIRMED_ADMIN_ID>
   ```
8. **Verify `rbac.enforce=false`** in the target environment (must remain allow-by-default).
9. **Verify tables and indexes** exist per §2.
10. **Verify admin login** works and the **admin dashboard** loads.
11. **Confirm no mutation behavior changed** (admin create/update/delete flows behave exactly as
    before — allow-by-default means `role_id=null` admins keep full access).
12. **(Optional) One safe audit event.** If the owner approves a specific safe admin
    POST/PATCH/DELETE test action, perform it and confirm a corresponding `admin_audit_logs` row
    is written. *(OWNER APPROVAL REQUIRED)*
13. **Bring the app back** with `php artisan up` if maintenance mode was used.

---

## 7. Rollback plan

- **Artisan rollback by path** (reverse order — audit first, then RBAC):
  ```bash
  php artisan migrate:rollback --path=database/migrations/2026_01_01_000004_create_admin_audit_logs.php
  php artisan migrate:rollback --path=database/migrations/2026_01_01_000003_create_rbac_roles_permissions.php
  ```
  (Or roll back by `--batch`/`--step` if those map cleanly to this apply.) *(OWNER APPROVAL
  REQUIRED for any rollback on the real DB.)*
- **Manual SQL fallback** (only if artisan migration state is inconsistent): `DROP TABLE
  admin_audit_logs;`, `DROP TABLE permission_role; DROP TABLE permissions; DROP TABLE roles;`, and
  `ALTER TABLE admins DROP COLUMN role_id;` — documented, owner-gated, last resort.
- **Remove super-admin seed** on owner request: null the assigned `admins.role_id` and/or delete
  the seeded role/permission rows (cascade clears `permission_role`).
- **Confirm `rbac.enforce=false`** after any rollback.
- **Restore from the verified backup** if rollback fails.
- **Caution:** once real `admin_audit_logs` rows exist, they are an **audit record**. Do not drop
  the audit table casually — dropping it for a clean rollback destroys the trail. Treat audit-data
  retention as an owner/compliance decision, not an operator convenience.

---

## 8. Post-apply verification plan

- Route count is **798** (`artisan route:list`).
- Frontend build **not** required (no code changed); document as optional only if code ships.
- **Admin login** works.
- Admin **read-only pages** load.
- `permission` middleware **allow-by-default** confirmed: a missing permission logs `would_deny`
  and the **request still proceeds** (no 403 while `rbac.enforce=false`).
- For an **owner-approved** safe admin POST/PATCH/DELETE test, an `admin_audit_logs` row is
  created.
- **No payment/gateway/IPN/webhook/checkout/escrow behavior touched.**
- **Deny-by-default NOT enabled** (`rbac.enforce` still `false`).
- **No real mutation test** is performed unless the owner explicitly approves it.

---

## 9. Owner approval gates

Explicit, recorded owner approval is required — individually — for each of:

- Running either migration on the real DB.
- Running `rbac:install` on the real DB.
- Assigning a real admin to `super-admin` (`--admin=<id>`).
- Entering any maintenance mode (`php artisan down`).
- Performing any real audit-generating test action.
- Any rollback against the real DB.
- Enabling `rbac.enforce=true` (deny-by-default) — a separate phase, see
  `RBAC-DENY-BY-DEFAULT-READINESS.md`.
- Any production deploy.
- Any admin mutation-endpoint extraction (F14+).

---

## 10. Hard stop conditions

Stop immediately (do not proceed / do not improvise) if any of these hold:

- Working tree is dirty, or the deployed commit hash ≠ the locked value.
- Backup is missing, or restore was not tested.
- DB name is not operator-verified (or any doubt the connection targets the intended DB).
- Drift is detected between `marketcapx_test` and the real schema.
- The `migrations` table is unexpectedly out of sync (would sweep in unrelated migrations).
- Any migration errors mid-apply.
- `rbac.enforce` is observed as `true` at any point in this phase.
- Any payment / escrow / gateway / IPN / checkout behavior change is observed.
- Owner approval is absent for a gated step in §9.

---

## 11. Future phases (proposed order)

1. **F11a** *(this doc)* — docs-only real-DB apply runbook.
2. **F11b** — optional disposable test-DB rehearsal script/commands (non-prod), automating §5.
3. **F12** — would-deny review dashboard / query docs (built on the F10 audit table).
4. **F13** — low-risk admin **read-only** detail pages.
5. **F14** — support-ticket audited endpoint extraction (first `before/after` mutation hooks).
6. **F15** — user/merchant/agent status audited endpoints.
7. **F16** — KYC approve/reject audited endpoints.
8. **F17** — deposit/withdraw audited endpoints.
9. Gateway/settings audited endpoints **last** (highest blast radius).

---

## Status after F11a

Runbook authored. **Nothing executed**: no migration run against any DB in this phase, no DB
mutated, no seed run, no admin assigned, `.env` not read or edited, and `rbac.enforce` remains
`false`. Real-DB execution stays owner-gated behind §4/§9 and the hard stops in §10.
