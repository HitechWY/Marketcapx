# Admin Audit-Log Readiness (F8c)

**Status:** read-only audit. Assesses whether sensitive admin mutations are auditable enough for
fintech-grade operation. No behavior changed. Companions: `RBAC-PERMISSION-AUDIT.md` (F8a),
`ADMIN-MUTATION-CONTRACT-AUDIT.md` (F8b), `HARDENING.md`, `RBAC-DESIGN.md`.

## What exists today

### `AdminAuditLog` middleware — `app/Http/Middleware/AdminAuditLog.php`
Applied to the **whole** admin group (`bootstrap/app.php` line 45). For **POST/PUT/PATCH/DELETE**
only, it writes one line to a dedicated daily channel `storage/logs/admin-audit.log` (30-day
retention) with: `admin_id`, `username`, `method`, `path`, `route` (name), `ip`, `status` (HTTP
code), `at` (timestamp). It **never logs the request body** (so secrets/tokens are not captured) and
swallows all logging errors so auditing can't break an action. Its own docblock calls it a
*"non-repudiation stop-gap until a proper DB-backed audit log + RBAC are implemented."*

### Other logs / models
- **Support messages** — `Message` / `SupportMessage` models carry `admin_id`, i.e. ticket replies
  **do** attribute the acting admin (the only place admin attribution exists in business data).
- **`UserLogin`** — logs *user* logins (type/timestamp); does **not** record admin impersonation
  (`login/{id}`).
- **`NotificationLog`** — records notifications sent (recipient/type/time); no admin-sent context.
- **`Transaction` / `ChargeLog`** — financial ledger + fee log; **no `admin_id`** (confirmed: grep
  of `database/migrations/` + `app/Models/` finds `admin_id` only on Message/SupportMessage).
- **`CronJobLog`, `UpdateLog`** — minimal/metadata only.

### Storage / retention / masking
- DB-backed audit table: **none** (`database/migrations/` has no audit/activity table).
- Retention: admin-audit file = **30 days** (middleware); Laravel daily logs = **14 days**
  (`config/logging.php` line 72, `LOG_DAILY_DAYS`).
- Masking: request body is never logged (good); but business models store sensitive fields in clear
  (KYC PII, withdraw_information) — those are not in the audit log, only in domain tables.
- No third-party audit package (`spatie/laravel-activitylog` not installed).

## Gaps vs fintech-grade requirements

1. **No target entity captured.** The middleware logs `route`+`path` but not *which* deposit/user/
   withdrawal/KYC was acted on. You cannot answer "who approved deposit #1234?" from the log.
2. **No before/after values.** No record of prior vs new status/balance/`kv`.
3. **No user-agent** (only IP).
4. **GET-based privileged actions are unlogged.** `AdminAuditLog` only logs POST/PUT/PATCH/DELETE, so
   **impersonation `login/{id}`**, cron `schedule/pause/{id}`, and system `optimize`/`optimize-clear`
   (all GET — see F8b) leave **no audit trail at all**.
5. **No admin attribution in money ledgers.** `Transaction` rows (balance add/sub, deposit credit,
   withdraw refund) have no `admin_id`; the only durable trace of an admin money action is a ledger
   row that doesn't say which admin caused it.
6. **File-based, not queryable.** Logs aren't in the DB, so no retention policy enforcement,
   tamper-evidence, search, or compliance export.

## Per-category readiness

Legend: ✅ yes · ⚠️ partial · ❌ no. "Audited" = a durable record beyond the metadata line.

| Mutation category | Audited? | Who (admin) | Target entity | Before/after | Payload masked | Notes |
|---|---|---|---|---|---|---|
| User/merchant/agent status (ban) | ⚠️ metadata only | ✅ (id+name) | ❌ | ❌ | ✅ (body not logged) | `ban_reason` on row, but no audit entity/diff |
| KYC approve/reject | ⚠️ metadata only | ✅ | ❌ | ❌ | ✅ | reject reason on row only |
| Account edit / KYC wipe | ⚠️ metadata only | ✅ | ❌ | ❌ | ✅ | deletes KYC files unlogged |
| **Impersonation (login-as)** | ❌ **none** | ❌ | ❌ | ❌ | n/a | **GET → not logged at all** |
| Manual balance add/sub | ⚠️ metadata + ledger | ✅ (metadata) / ❌ (ledger) | ❌ | ⚠️ (`post_balance` only) | ✅ | ledger row has no `admin_id` |
| Deposit approve/reject | ⚠️ metadata + ledger | ✅ / ❌ | ❌ | ⚠️ | ✅ | credit ledger lacks `admin_id` |
| Withdraw approve/reject | ⚠️ metadata + ledger | ✅ / ❌ | ❌ | ⚠️ | ✅ | refund ledger lacks `admin_id` |
| Escrow action | ⚠️ metadata only | ✅ | ❌ | ❌ | ✅ | frozen; fund movement unattributed |
| Support ticket reply/close | ✅ (reply) / ⚠️ | ✅ (`admin_id` on message) | ✅ (ticket) | ⚠️ | ✅ | reply is the best-audited action |
| Ticket delete | ⚠️ metadata only | ✅ | ❌ | ❌ | ✅ | hard delete, content not preserved |
| Gateway/method/currency/charges | ⚠️ metadata only | ✅ | ❌ | ❌ | ✅ (body not logged) | secret changes unattributed at field level |
| System/notification settings | ⚠️ metadata only | ✅ | ❌ | ❌ | ✅ | secret rotations unattributed |
| Cron (store/update/delete/pause) | ⚠️/❌ | ✅ (POST) / ❌ (pause GET) | ❌ | ❌ | ✅ | `schedule/pause` GET unlogged |
| Plugins/extensions/system-update | ⚠️ metadata only | ✅ | ❌ | ❌ | ✅ | code-change actions, metadata only |

## Required conclusion

For **every** high-risk mutation category, the current trail is at best the `AdminAuditLog` metadata
line (who/when/IP/route/status) — and **for GET-based privileged actions (impersonation, cron pause,
system optimize), nothing at all.** No category captures the **target entity id**, **before/after
values**, or **admin attribution inside the money ledger**. This is **not fintech-grade**.

### Required audit fields before any mutation migrates
A DB-backed `admin_audit_logs` record per sensitive mutation with: `admin_id`, `admin_username`,
`ip`, `user_agent`, `action_type` (e.g. APPROVE/REJECT/BAN/ADJUST_BALANCE/IMPERSONATE), `entity_type`,
`entity_id`, `before` + `after` (JSON, with sensitive values masked), `reason/notes`, `request_id`/
`idempotency_key`, `result`/`status`, `created_at`. Coverage must include **GET-based** privileged
actions (impersonation especially). Pair with RBAC so allowed **and** denied attempts are recorded.

## Tests

The existing `tests/Feature/AdminAuditLogTest.php` already asserts (route-inspection only) that the
admin group carries `AdminAuditLog`. No additional non-mutating assertion adds value here without
dispatching requests (the verb-filtering and field capture are internal middleware behavior that a
route-collection test cannot observe), so **F8c adds no new test** — the gap is a *design* gap to be
closed by the audit-log foundation in F8d, where new behavior (and its tests) will live.
