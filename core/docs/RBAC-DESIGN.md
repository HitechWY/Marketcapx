# Admin RBAC — Design (not yet implemented)

Design only. No schema change has been run. Today the admin panel is **flat**:
any authenticated admin can hit every admin route. State-changing admin requests
are already recorded by `App\Http\Middleware\AdminAuditLog` (metadata-only,
applied to the whole admin group in `bootstrap/app.php`), but there is no
role/permission enforcement. This doc proposes a minimal, additive RBAC.

## Goals
- Least-privilege admin accounts (e.g. support vs finance vs super-admin).
- Enforce permissions on sensitive mutations (balance adjust, withdrawal
  approve/reject, gateway edit, settings, KYC).
- Additive and backward-compatible: existing single super-admin keeps full access.

## Proposed schema (migration drafts — do NOT run on `paytabz_DB`)
- `roles` (`id`, `name`, `slug` unique, `timestamps`).
- `permissions` (`id`, `name`, `slug` unique, `group`, `timestamps`).
- `permission_role` pivot (`role_id`, `permission_id`, unique pair).
- `admins.role_id` nullable FK → `roles.id` (nullable so existing admins are
  unaffected; null = legacy full-access during rollout, then backfill to a
  `super-admin` role and drop the null path).

Seed a `super-admin` role with all permissions and assign existing admin(s) to
it before enforcing, so nobody is locked out.

## Enforcement
- A `permission:<slug>` route middleware that checks
  `auth('admin')->user()->role?->permissions->contains('slug', $slug)` (super-admin
  short-circuits to allow). Applied per-route or per-group on the sensitive
  admin routes enumerated in `routes/admin.php`.
- Pair with the existing `AdminAuditLog` so every attempt (allowed or denied) is
  traceable.

## Permission groups (initial)
`finance` (balance add/sub, withdrawal approve/reject, deposit approve),
`gateways` (automatic/manual gateway CRUD), `settings` (general/system/logo/
maintenance), `users` (status, KYC approve/reject), `content` (pages/blog/etc.).

## Rollout (safe order)
1. Land migration drafts (reviewed, not run) under `database/migrations-drafts/`.
2. In a dev/test DB only: run them, seed `super-admin`, backfill `admins.role_id`.
3. Add the `permission` middleware (allow-by-default until roles are seeded).
4. Apply middleware to sensitive routes; verify on the disposable test DB.
5. Flip to deny-by-default once all admins have roles.

## Why deferred
Implementing this touches the live `admins` table schema and a broad set of
routes; it must be validated against a seeded test DB (see `tests/README.md`),
not applied to `paytabz_DB`. The metadata audit log is the current stop-gap.

See `docs/HARDENING.md` (admin audit log) and `docs/SETUP.md`.
