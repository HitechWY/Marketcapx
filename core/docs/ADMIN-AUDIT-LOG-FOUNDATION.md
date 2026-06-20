# Admin Audit-Log Foundation (F10)

The DB-backed admin audit-log foundation. Built allow-by-default, middleware-level, with **zero**
admin-mutation / money / gateway / KYC / escrow behavior change. Companions:
`ADMIN-AUDIT-LOG-SCHEMA.md` (F10a), `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md` (F10e),
`ADMIN-AUDIT-LOG-READINESS.md` (F8c gap this closes), `RBAC-*` docs.

## What F10 captures now

A structured, queryable `admin_audit_logs` table, written by `App\Services\AdminAuditLogger` from two
middleware layers (file logs retained as fallback):

- **RBAC decisions** (`PermissionMiddleware`, F10c): `rbac.permission.allowed` / `would_deny` /
  `denied`, with `permission_slug`, route, admin, ip, http_status (403 on denied).
- **Admin state-changing requests** (`AdminAuditLog` middleware, F10d):
  `admin.request.mutation` / `admin.request.failed` for POST/PUT/PATCH/DELETE, with response status
  and route-inferred `entity_type` / `entity_id`.
- **Privileged GET actions** (impersonation, KYC view) via their gated `rbac.permission.allowed` rows.

Every row: who (admin_id/username), what (action/permission/route/method/path), where (ip,
user_agent), outcome (result/http_status), best-effort entity, sanitized metadata, correlation_id.
**Never** stored: passwords, tokens, API keys, gateway credentials, callback secrets, KYC paths,
card data, private paths, or request bodies (redacted by `sanitizeMetadata`; bodies never read).

## What is deferred (design-only — F14+)

Semantic per-action events (`user.status.updated`, `kyc.approved`, `deposit.approved`,
`withdrawal.rejected`, `gateway.updated`, …) and **before/after value capture** require controller/
service hooks inside the transaction boundary — deferred to the audited mutation-endpoint phases.
`AdminAuditLogger::logMutation()` is reserved for them. See `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`.

## How this supports RBAC deny-by-default review

The would-deny review in `RBAC-DENY-BY-DEFAULT-READINESS.md` is now a DB query instead of a log grep:

```sql
-- permissions real admins exercise but their role lacks (grant or confirm-deny before enforcing)
SELECT admin_id, permission_slug, COUNT(*) c
FROM admin_audit_logs
WHERE action = 'rbac.permission.would_deny'
GROUP BY admin_id, permission_slug
ORDER BY c DESC;
```

Zero unexpected `would_deny` rows over a representative period = a key readiness signal for flipping
`rbac.enforce=true` (still owner-gated).

## Querying the trail (examples)

```sql
-- everything an admin did
SELECT * FROM admin_audit_logs WHERE admin_id = ? ORDER BY id DESC;
-- all denied attempts
SELECT * FROM admin_audit_logs WHERE result = 'denied' ORDER BY id DESC;
-- history for one entity
SELECT * FROM admin_audit_logs WHERE entity_type = 'users' AND entity_id = ? ORDER BY id DESC;
```

## Real DB migration — owner-gated

The `admin_audit_logs` migration ships in `database/migrations/` but has been run **only** against the
disposable `marketcapx_test`. Running it on dev/staging/prod (esp. `paytabz_DB`) requires explicit
owner approval, a fresh backup, and a maintenance window (see `ADMIN-AUDIT-LOG-SCHEMA.md`).

## Retention / pruning — future ops task

The table is append-only and grows with admin activity. A scheduled prune/archive (e.g. delete rows
older than N days) should be added as a separately-approved ops task; F10 adds no scheduler.

## Prerequisites this unblocks

With RBAC (F9) + this audit foundation (F10) in place, the remaining prerequisites before migrating
admin mutation UI to Inertia are: (1) owner-gated real-DB schema apply + role assignment, (2) the
deny-by-default readiness review/flip, then (3) per-endpoint audited mutation extraction (F14+) that
adds semantic events + before/after. Until then nothing is unfrozen and `rbac.enforce` stays false.
