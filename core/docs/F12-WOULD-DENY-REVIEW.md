# RBAC Would-Deny Review & Audit Query (F12)

**Status:** review tooling + docs. **Deny-by-default is NOT enabled** (`config('rbac.enforce')`
is still `false`, allow-by-default). F12 adds a **read-only** way to review the `would_deny`
evidence the middleware already records, so an owner can later decide — with data — whether to
grant missing permissions before any deny-by-default flip. F12 changes **no** admin behavior,
adds **no** routes (route count stays 798), runs **no** migrations, and touches **no** real DB.
Companions: `RBAC-DENY-BY-DEFAULT-READINESS.md`, `ADMIN-AUDIT-LOG-FOUNDATION.md`,
`ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`, `ADMIN-AUDIT-LOG-SCHEMA.md`, `RBAC-SEEDING.md`,
`F11-REAL-DB-SCHEMA-APPLY-RUNBOOK.md`.

## What F12 delivered

- **Read-only command** `php artisan rbac:would-deny-report` — summarises RBAC permission-decision
  rows from `admin_audit_logs` (default `result=would_deny`). `app/Console/Commands/RbacWouldDenyReport.php`.
- **Read-only query scopes** on `App\Models\AdminAuditLog` (`rbacDecision`, `result`, `wouldDeny`,
  `forAdmin`, `permission`, `routeName`, `since`) — SELECT-only; do not change how rows are written.
- **Tests** `tests/Feature/RbacWouldDenyReportTest.php` (`marketcapx_test`, `DatabaseTransactions`).
- This doc: the review procedure + the safe query catalogue.

## The command

```
php artisan rbac:would-deny-report
    [--days=7] [--admin=<id>] [--permission=<slug>] [--route=<name>]
    [--result=would_deny]            # would_deny | allowed | denied (RBAC decisions only)
    [--group-by=permission]          # permission | admin | route | result
    [--format=table]                 # table | json
    [--limit=100]
```

It prints a summary (window, matching rows, distinct admins/permissions/routes), readiness
**warnings**, and a grouped findings table (or JSON). It is **read-only** — SELECTs only, never
writes. Sensitive columns (`ip`, `user_agent`, `metadata`, `before_values`, `after_values`) are
**never selected or printed** in either format; only `permission_slug` / `admin_id` /
`admin_username` / `route_name` / `result` and aggregate counts appear.

It reports RBAC **decision** rows only (`action LIKE 'rbac.permission.%'`). Mutation rows
(`admin.request.*`, results `succeeded|failed`) are a separate concern and out of scope here.

## Would-deny review procedure (for when real data exists — owner-gated)

Real-DB review is owner-gated (see `F11-REAL-DB-SCHEMA-APPLY-RUNBOOK.md`); the steps below are
the same whether run on `marketcapx_test` or, later and with approval, a real DB.

1. **Run with allow-by-default for a representative period** so real admins exercise the panel and
   `would_deny` rows accumulate (zero behavior change — requests still pass).
2. **Group by permission** (`--group-by=permission`): the top slugs are the permissions roled
   admins most often lack. Each is either a grant to add or a deny to confirm.
3. **Group by admin** (`--group-by=admin`): find under-privileged admins; decide per admin whether
   to widen their role.
4. **Group by route** (`--group-by=route`): a route generating unexpected would-denies can signal a
   permission→route **mapping mistake** (wrong slug on the route) rather than a real access gap.
5. **Heed the legacy warning.** Admins with `role_id IS NULL` keep legacy **full access** and
   therefore **never** produce `would_deny`. The command prints how many remain. **Until every
   admin has a role, the report under-counts gaps** — assign roles (`rbac:install --admin=<id>` for
   super-admins, or scoped roles) first. This is also readiness item 3 in
   `RBAC-DENY-BY-DEFAULT-READINESS.md`.
6. **Heed unmapped-slug warnings.** Any `permission_slug` not in `PermissionCatalog::slugs()`
   indicates drift between routes and the catalog — fix before trusting the data.
7. **Flag high-risk attempts.** Pay special attention to `would_deny` on finance/gateway/kyc slugs
   by admins who should not have them — these are the rows that most justify keeping deny-by-default
   off until resolved.
8. **Converge to readiness.** When grouped `would_deny` shows only expected gaps (all granted or
   intentionally denied) and no null-role admins remain, that is the green signal feeding the
   deny-by-default checklist. F12 does **not** flip it.

## Safe query catalogue (design reference)

The command covers the common cuts; these are the underlying queries (run only on
`marketcapx_test`, or via the command). All filter to RBAC decision rows.

- **Top would-deny permissions:** `--group-by=permission` →
  `... WHERE action LIKE 'rbac.permission.%' AND result='would_deny' GROUP BY permission_slug ORDER BY COUNT(*) DESC`.
- **Would-deny by admin:** `--group-by=admin` (groups `admin_id, admin_username`).
- **Would-deny by route:** `--group-by=route`.
- **Allowed by permission:** `--result=allowed --group-by=permission` (note: includes legacy
  null-role allows, which are indistinguishable from real grants in the row).
- **Denied (test enforce=true):** `--result=denied` (only populated where `rbac.enforce=true` ran,
  e.g. in tests).
- **Over time:** filter the window with `--days` and re-run; per-day bucketing is a future add.
- **Unmapped slugs / null-entity / failed rows / metadata redaction:** asserted in
  `RbacWouldDenyReportTest`; never executed against a real DB.

## Why no dashboard/UI in F12

Deferred deliberately: the real audit schema is not yet applied (no production data to show), an
admin page would add a route (breaking the 798 invariant), and exposing per-admin activity needs
explicit owner decisions on permission visibility and sensitive-data handling. A read-only,
permission-protected report **page** is a candidate for a later, owner-approved phase (F12b/F13)
with its route-count impact documented.

## Relationship to deny-by-default

F12 **prepares the evidence**; it never enables enforcement. Deny-by-default may be considered only
after: real schema applied (F11 runbook) · RBAC seeded · **every admin has a role** (no null
`role_id`) · would-deny reviewed with this command · no unexpected high-risk would-denies · explicit
owner approval. The flip itself is the single config change described in
`RBAC-DENY-BY-DEFAULT-READINESS.md`.

## Status after F12

Command + scopes + tests added; full suite green (176 tests / 1783 assertions / 1 skipped, up from
165 / 1749 / 1). Route count **798** unchanged. `rbac.enforce` remains **false**. No migration run,
no real DB touched, no admin behavior changed, no routes added. Next: F12b/F13 (owner-gated
read-only admin report page) per `F11-REAL-DB-SCHEMA-APPLY-RUNBOOK.md` §11.
