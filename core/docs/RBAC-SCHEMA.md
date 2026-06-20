# RBAC Schema (F9a)

The schema for the admin RBAC enforcement foundation. Promoted in F9a from
`database/migrations-drafts/` to `database/migrations/2026_01_01_000003_create_rbac_roles_permissions.php`.
Additive and backward-compatible. Companion: `RBAC-DESIGN.md` (rollout), `RBAC-PERMISSION-AUDIT.md`
(why — admin is flat today), `ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md` (F8d).

## Tables

- **`roles`** — `id`, `name`, `slug` (unique), `timestamps`.
- **`permissions`** — `id`, `name`, `slug` (unique), `group` (nullable: dashboard | users | kyc |
  finance | gateway | settings | support | content | system | reports | roles), `timestamps`.
- **`permission_role`** (pivot) — `role_id`, `permission_id`, unique pair (`permission_role_unique`),
  **FKs** to `roles.id` / `permissions.id` (`cascadeOnDelete`). Safe to FK — both are created here.
- **`admins.role_id`** — new **nullable**, **indexed** `unsignedBigInteger` (added via `Schema::table`
  guarded by `hasColumn`). `admins.id` is `bigint` so the type matches.

### Why `admins.role_id` is nullable
`null` = **legacy full access** (super-admin behavior). This keeps every existing admin working
unchanged the moment the migration lands — no lockout. Roles are assigned later (F9c seeder), and the
`permission` middleware only denies once an owner flips `rbac.enforce=true` (deny-by-default), which is
**not** part of F9. Nullable is the backward-compatibility hinge of the whole rollout.

### Why no FK on `admins.role_id`
The `admins` table ships in the SQL dump; an FK there would couple to its exact storage engine/
collation and risk import friction. Per `RBAC-DESIGN.md` it is deferred. Integrity for `role_id` is
enforced in the app layer (`Admin::role()` belongsTo). The pivot FKs (new tables) are kept.

## Rollback

`php artisan migrate:rollback` runs `down()`: drops `admins.role_id` (if present), then
`permission_role`, `permissions`, `roles` (FK-safe order). The change is also inert until roles are
seeded and `rbac.enforce=true`, so a rollback before enforcement has zero behavior impact.

## Applying to the test DB (`marketcapx_test` — allowed)

The app schema lives in the SQL dump, not migrations, so the test DB is seeded from the dump and the
RBAC migration is applied on top (see `tests/README.md`):

```bash
# one-time, against the disposable copy only — never paytabz_DB
APP_ENV=testing php -d extension=gmp artisan migrate --path=database/migrations/2026_01_01_000003_create_rbac_roles_permissions.php
```

RBAC feature tests use `DatabaseTransactions` (rollback per test); they create roles/permissions/
admins as fixtures and roll them back — nothing persists.

## Applying to a real DB (owner-gated — do NOT run without approval)

Running this migration (or any seeding) against dev/staging/prod, especially `paytabz_DB`, requires:
1. Explicit owner approval.
2. A fresh, verified backup.
3. A maintenance window.
4. Apply, then seed `super-admin` (F9c) and assign existing admin(s) **before** any consideration of
   deny-by-default.

Deny-by-default (`rbac.enforce=true`) is a separate, separately-approved step (see F9f readiness doc).

## Status after F9a
Migration promoted and finalized; **not run** against any DB in F9a (lint/static inspection only).
Models, catalog, seeder, and middleware follow in F9b–F9e.
