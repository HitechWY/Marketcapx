# RBAC Seeding (F9c)

How to seed RBAC roles/permissions. Idempotent; safe to re-run. Companion: `RBAC-SCHEMA.md`,
`RBAC-DESIGN.md`.

## What gets seeded

`Database\Seeders\RbacSeeder` (and the `rbac:install` command that wraps it):
- Upserts **every** permission in `App\Support\PermissionCatalog` (by slug).
- Upserts the **`super-admin`** role.
- Grants **all** permissions to super-admin (`syncWithoutDetaching` — no duplicates, no removals).

It **never** creates admin/user/merchant/agent accounts, and it **never** assigns a role to an admin
on its own.

## Commands

```bash
# seed permissions + super-admin role (idempotent)
php artisan rbac:install

# additionally assign an EXISTING admin to super-admin (explicit opt-in)
php artisan rbac:install --admin=<existingAdminId>
```

`--admin` fails (exit 1) if the id doesn't exist and creates nothing.

## Test DB (`marketcapx_test`) — allowed

The F9c tests run the seeder/command against the disposable `marketcapx_test` (RBAC tables added by
the F9a migration) inside `DatabaseTransactions`, so every row is rolled back and nothing persists.
No manual step is needed for tests.

## Real DB (dev/staging/prod, incl. `paytabz_DB`) — owner-gated

Do **not** run `rbac:install` (or any seeder/migration) against a real DB without:
1. Explicit owner approval.
2. A fresh, verified backup.
3. A maintenance window.

Recommended real-DB order (when approved):
1. Apply the RBAC migration (`RBAC-SCHEMA.md`).
2. `php artisan rbac:install` (seed permissions + super-admin).
3. `php artisan rbac:install --admin=<id>` for each admin who should be super-admin — **before** any
   consideration of deny-by-default.
4. Leave `config('rbac.enforce')` = **false** (allow-by-default). Enabling deny-by-default is a
   separate, separately-approved step (see `RBAC-DENY-BY-DEFAULT-READINESS.md`).

## Idempotency / safety

- Permissions keyed by unique `slug` (updateOrCreate); role keyed by unique `slug` (firstOrCreate);
  pivot has a unique (role_id, permission_id) constraint. Re-running changes nothing.
- Seeding does not enable enforcement and does not alter any admin/money/gateway behavior.
