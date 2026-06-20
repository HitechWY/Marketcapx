# RBAC Deny-by-Default Readiness (F9f)

**Status:** docs-only checklist. **Deny-by-default is NOT enabled.** The shipped default is
`config('rbac.enforce') = false` (allow-by-default). Flipping it to `true` is an **owner-gated**
operational change — do not enable it until every item below is satisfied. Companions:
`RBAC-SCHEMA.md`, `RBAC-SEEDING.md`, `RBAC-PERMISSION-AUDIT.md`, `RBAC-DESIGN.md`.

## What F9 delivered (foundation, allow-by-default)

- Schema: `roles`, `permissions`, `permission_role`, nullable `admins.role_id` (F9a migration —
  run only on `marketcapx_test`; real-DB run owner-gated).
- Models + catalog: `Role`, `Permission`, `Admin::role()/hasPermission()`, `PermissionCatalog`
  (65 slugs).
- Seeder/command: `RbacSeeder` / `php artisan rbac:install [--admin=<id>]` (idempotent).
- Middleware: `permission:<slug>` (allow-by-default; logs would-deny via the admin-audit channel;
  403 only when `rbac.enforce=true`).
- High-risk admin routes gated with `permission:<slug>` (F9e) — **behaviour unchanged** while
  allow-by-default, because every existing admin has a null `role_id` (legacy full access).

## Pre-flight checklist before enabling `rbac.enforce=true`

1. **Schema applied to the target DB** (owner-gated): the F9a migration has been run on the real DB
   in a maintenance window with a fresh, verified backup (`RBAC-SCHEMA.md`).
2. **Permissions + super-admin seeded**: `php artisan rbac:install` has run on the target DB
   (`RBAC-SEEDING.md`).
3. **Every admin has a non-null role**: no admin may rely on the legacy `role_id = null` path once
   enforcing — a null role would short-circuit to *allow* and defeat enforcement, while an
   incompletely-roled admin could be locked out of needed actions. Assign each admin an appropriate
   role (super-admin or a scoped role); verify `SELECT id, role_id FROM admins WHERE role_id IS NULL`
   returns **zero rows**.
4. **Super-admin verified**: at least one trusted admin is confirmed on the `super-admin` role and
   can reach every area (so nobody is locked out of administration).
5. **Route coverage reviewed**: confirm the high-risk routes carry the intended slugs
   (`RbacRouteCoverageTest`) and decide whether any currently-ungated read routes also need slugs.
6. **Would-deny log reviewed**: with allow-by-default running in production for a representative
   period, review `storage/logs/admin-audit.log` for `admin.permission` `"would_deny"` entries.
   Each one is an action a real admin performs but their role lacks — grant the missing permission
   (or confirm it should be denied) **before** flipping. Zero unexpected would-denies = ready.
7. **Non-super roles validated** on the test DB: a scoped role can perform its permitted actions and
   is correctly 403'd on others (extend the F9 enforce=true tests as needed).
8. **Rollback plan**: enabling is a single config flip; rollback is setting `rbac.enforce=false`
   (and `config:clear`/`config:cache` if config is cached). Keep a backup of the DB from step 1.
9. **Maintenance window + owner approval**: schedule the flip, announce it, and obtain explicit
   owner sign-off.

## How the flip works (when approved — NOT done here)

`config('rbac.enforce')` reads `RBAC_ENFORCE` (default false). To enable on an approved environment:
set `RBAC_ENFORCE=true` in that environment, then `php artisan config:clear` (or `config:cache`).
Rollback = set it back to `false` and clear config. This repo ships it **false** and F9 does not
change that default.

## Out of scope (separate, separately-approved phases)

- Role-management **UI** (F9 manages roles via seeder/command only).
- The DB-backed `admin_audit_logs` foundation (F8d design) — RBAC currently logs decisions to the
  file channel; swapping the sink to a DB table is a later phase.
- Migrating admin mutation **UI** to Inertia (gated behind RBAC + audit-log foundations per
  `ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md`).
