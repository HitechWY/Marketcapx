# Admin Audit-Log Action Coverage (F10e)

What the F10 audit foundation captures **now** (middleware-level, no controller changes) vs what is
**deferred** to the audited mutation-endpoint phases (F14+). Companions: `ADMIN-AUDIT-LOG-SCHEMA.md`,
`ADMIN-AUDIT-LOG-READINESS.md`, `ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md`.

## Implemented now (F10c/F10d) — middleware-level

| Source | `action` | `result` | When |
|---|---|---|---|
| `PermissionMiddleware` (F10c) | `rbac.permission.allowed` | allowed | admin has the permission (incl. super-admin / legacy null role) on a gated route |
| `PermissionMiddleware` (F10c) | `rbac.permission.would_deny` | would_deny | gated route, admin lacks permission, `rbac.enforce=false` (allowed through) |
| `PermissionMiddleware` (F10c) | `rbac.permission.denied` | denied | gated route, admin lacks permission, `rbac.enforce=true` (403) |
| `AdminAuditLog` middleware (F10d) | `admin.request.mutation` | succeeded | admin POST/PUT/PATCH/DELETE, response < 400 |
| `AdminAuditLog` middleware (F10d) | `admin.request.failed` | failed | admin POST/PUT/PATCH/DELETE, response ≥ 400 |

Each row captures: admin_id, admin_username, method, path, route_name, ip, user_agent (truncated),
correlation_id (if header present), http_status, and (best-effort) entity_type/entity_id. Metadata is
sanitized; request bodies are never read.

**Privileged GET coverage:** impersonation (`admin.*.login`) and KYC document view
(`admin.*.kyc.details`) are GET routes, so the `AdminAuditLog` middleware (verb-scoped) does not log
them — but they are **gated**, so `PermissionMiddleware` records an `rbac.permission.allowed` row for
each (permission_slug `admin.*.login` / `admin.*.kyc.review`). This closes the F8c "GET impersonation
unlogged" gap without changing behavior.

## Entity inference map (non-invasive)

`AdminAuditLogger::inferEntity()` derives, from **route metadata only** (no DB lookup, no body):
- **entity_type** = the segment after `admin.` in the route name. Examples:
  `admin.users.status` → `users`; `admin.agents.kyc.approve` → `agents`;
  `admin.merchants.add.sub.balance` → `merchants`; `admin.deposit.approve` → `deposit`;
  `admin.withdraw.data.reject` → `withdraw`; `admin.ticket.delete` → `ticket`;
  `admin.gateway.automatic.update` → `gateway`; `admin.setting.notification.*` → `setting`;
  `admin.cron.store` → `cron`; `admin.plugins.upload` → `plugins`.
- **entity_id** = the numeric `{id}` route parameter when present (e.g. `/users/status/7` → `7`);
  null otherwise (routes whose target id is in the body — e.g. `deposit/reject`, `withdraw/approve`
  — yield `entity_id = null` at the middleware level; the precise id is captured later by the
  per-action hooks).

Inference is best-effort and intentionally conservative: when the type or id is not unambiguous from
the route, the field is left **null** rather than guessed.

## Deferred to mutation-endpoint extraction (F14+) — DESIGN ONLY

These semantic events require controller/service hooks and are **not** implemented in F10 (doing so
would change admin mutation flow, which F10 forbids):

`user.status.updated`, `user.balance.adjusted`, `kyc.approved`, `kyc.rejected`, `deposit.approved`,
`deposit.rejected`, `withdrawal.approved`, `withdrawal.rejected`, `support.reply.created`,
`support.ticket.closed`, `gateway.updated`, `settings.updated`, `notification.template.updated`,
`system.action.executed`.

When implemented (per phase), each will call `AdminAuditLogger::logMutation()` (already defined,
reserved) with the precise entity, semantic action, and sanitized before/after snapshots.

## Why before/after values are deferred

- **Require controller/service hooks.** Accurate prior-state must be read inside the action, before
  the write — the middleware (which runs after `$next`) cannot see it. F10 changes no controllers.
- **Must sit inside/adjacent to the transaction boundary.** For money/status actions the snapshot
  must be consistent with the committed change; that means writing the audit row within the same
  `DB::transaction` as the mutation (F17 will also decide fail-closed vs fail-open there).
- **Must avoid false records.** Logging before the action commits could record a change that later
  rolls back; the per-action hook must record only on the committed path.
- **Must avoid logging secrets.** before/after for gateway/settings/KYC contains secrets/PII; each
  hook must field-allow-list and reuse `sanitizeMetadata` — designed per endpoint, not generically.

## Status

F10e is docs-only: it finalizes the inference map (already implemented in F10b/d) and records the
deferred semantic-event design. No controllers or business hooks were added.
