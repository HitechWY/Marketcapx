# Admin Audit-Log Action Coverage (F10e → F24)

What the admin audit log captures: the **F10 foundation** (middleware-level, no controller changes),
the **semantic mutation events shipped in F14–F16** (controller-level, before/after snapshots), and
what is **still deferred** to later mutation-endpoint phases. Companions: `ADMIN-AUDIT-LOG-SCHEMA.md`,
`ADMIN-AUDIT-LOG-READINESS.md`, `ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md`, `F14-SUPPORT-TICKET-AUDIT.md`,
`F14b-SUPPORT-TICKET-REPLY-AUDIT.md`, `F15-ACCOUNT-STATUS-AUDIT.md`, `F16-KYC-DECISION-AUDIT.md`.

> **F24 (docs-only) update:** F14, F14b, F15, and F16 are now **shipped** — the semantic events below
> moved out of the "deferred / design-only" list into "Implemented". The shipped event **names** differ
> from the F10e design placeholders (e.g. design `kyc.approved` → shipped `account.kyc.approved`); this
> doc now records the **actual** names as written by the controllers. No code changed in F24.

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

## Implemented (F14–F16) — semantic mutation events (controller-level)

Each event below is written by a controller hook via `AdminAuditLogger::logMutation()` with the precise
entity, a sanitized before/after snapshot, and `result='succeeded'`. These are **complementary** to the
F10d `admin.request.mutation` row for the same request (the semantic row adds the named action +
before/after; it is not a duplicate). All are **status/structure-only** — never request bodies, reply
text, KYC reason text, KYC document names/paths, balances, tokens, or PII. `rbac.enforce` stays `false`.

| Phase | `action` (as shipped) | Controller @ method | entity_type | before/after | Excludes |
|---|---|---|---|---|---|
| F14 | `support.ticket.closed` | `SupportTicketController@closeTicket` | `ticket` | ticket status | message body |
| F14b | `support.ticket.replied` | `SupportTicketController@replyTicket` | `ticket` | ticket status | reply body, attachment names |
| F15 | `account.status.changed` | `ManageGuardController@status` | `users`/`agents`/`merchants` | account status | ban/unban reason text, PII |
| F16 | `account.kyc.approved` | `ManageGuardController@kycApprove` | `users`/`agents`/`merchants` | `kv` | reason text, KYC docs/paths, `kyc_data`, PII |
| F16 | `account.kyc.rejected` | `ManageGuardController@kycReject` | `users`/`agents`/`merchants` | `kv` | reason text, KYC docs/paths, `kyc_data`, PII |

Negative paths write **no** semantic row: validation failure (e.g. KYC reject / ban missing `reason`)
throws before the write; a missing record 404s before any write. Each event has a dedicated feature test
(`SupportTicketCloseAuditTest`, `SupportTicketReplyAuditTest`, `AccountStatusAuditTest`,
`KycDecisionAuditTest`). `logMutation` is **fail-open**: a failed audit write is caught and file-logged,
and the business mutation still commits — auditing never breaks an admin action.

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

## Still deferred to mutation-endpoint extraction — DESIGN ONLY

These semantic events still require controller/service hooks and are **not** implemented yet. The
money-flow events in particular are **owner-gated** (no payment/wallet/gateway behavior change in this
docs/audit track); see `F19-MONEY-FLOW-ADMIN-ATTRIBUTION-MAP.md` for the attribution gap analysis.

| Proposed `action` | Endpoint area | Notes |
|---|---|---|
| `account.balance.adjusted` | `ManageGuardController@addSubBalance` | money flow — owner-gated; ledger row has no `admin_id` today |
| `deposit.approved` / `deposit.rejected` | `DepositController@approve/reject` | money flow — owner-gated |
| `withdrawal.approved` / `withdrawal.rejected` | `WithdrawalController@approve/reject` | money flow — owner-gated |
| `escrow.resolved` | `EscrowController@action` | money flow — owner-gated |
| `gateway.updated` | gateway update endpoints | secrets/PII in before/after — needs field allow-list |
| `settings.updated` | settings endpoints | needs field allow-list |
| `notification.template.updated` | notification template endpoints | body/template content excluded |
| `account.profile.updated` (F17) | `ManageGuardController@update` | changed field-**names** only, never PII values |
| `notify.sent` (F18) | admin notify single/all | channel + recipient_count + scope-name only |

When implemented (per phase), each will call `AdminAuditLogger::logMutation()` with the precise entity,
semantic action, and a sanitized, field-allow-listed before/after snapshot — never secrets/PII.

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
