# Account Status (ban/unban) — Semantic Admin-Audit (F15)

**Status:** F15 adds the semantic admin-mutation event `account.status.changed` around the admin
**ban/unban** toggle for users, agents, and merchants. **Behavior is unchanged**: validation, the
`status`/`ban_reason` field changes, the redirect/flash, and the 404 path are all as before; only a
**status-only** audit row is added (inside the existing save's transaction). No KYC/balance/verification
change, no new routes (route count **798**), no Inertia/Vue/Blade change, no migration, no real DB,
`rbac.enforce` still **false**. Companions: `F14-SUPPORT-TICKET-AUDIT.md`,
`F14b-SUPPORT-TICKET-REPLY-AUDIT.md`, `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`, `ADMIN-AUDIT-LOG-FOUNDATION.md`.

## What F15 shipped

- **Event:** `account.status.changed` — one guard-neutral action; the guard is carried in
  `entity_type` (`users` | `agents` | `merchants`) and `metadata.guard`. Written via
  `AdminAuditLogger::logMutation()` (`result='succeeded'`).
- **Where:** `app/Http/Controllers/Admin/ManageGuardController.php` `status($request, $id)` — the
  single guard-parameterized ban/unban method shared by all three guards (the `guardType` route param
  selects the model). It is a controller method (no shared trait) and no user-side panel uses it, so
  the edit affects **only** admin status changes.
- **Change shape (minimal):** capture `before = ['status' => (int) $user->status]` before the
  existing if/else; keep the ban/unban field logic + notify message **exactly as is**; move the
  existing `$user->save()` into a `DB::transaction` that also writes the audit row; return the same
  `back()->withNotify($notify)`.

## What the audit row captures (and excludes)

| Field | Value |
|---|---|
| `action` | `account.status.changed` |
| `result` | `succeeded` |
| `entity_type` / `entity_id` | guard (`users`/`agents`/`merchants`) / the account id |
| `before_values` | `{ status: <prior int> }` |
| `after_values` | `{ status: <new int> }` (`USER_ACTIVE=1` / `USER_BAN=0`) |
| `metadata` | `{ guard, banned: <bool> }` |
| admin + `method`/`path`/`ip`/`route_name`/`correlation_id` | from the admin + Request |

**Never captured:** the `ban_reason` free-text, username/email/any PII, wallet balances, tokens,
secrets, KYC data/paths. `sanitizeMetadata` still redacts SENSITIVE_KEYS and caps size (values here
are ints/bools/guard label).

## Transaction & fail-open

`status()` has **no post-save side effects** (no email/SMS, no session/file work), so F15 wraps the
existing `save()` **and** the audit write in one `DB::transaction` — on the success path they commit
together (the F8d intent). This is behavior-preserving: the only DB write in the method is that
`save()`, now inside the transaction. `AdminAuditLogger::logMutation()` remains **fail-open**
(unchanged): a failed audit write is caught/file-logged and never breaks the status change. A ban
**validation failure** (`reason` required) throws **before** the transaction → **no** audit row; a
**missing id** 404s before any write → **no** audit row.

## Behavior preserved (ban/unban semantics)

- **Ban** (from `USER_ACTIVE`): requires a validated `reason`; sets `status=USER_BAN`,
  `ban_reason=<reason>`; success notify.
- **Unban** (from `USER_BAN`): no validation; sets `status=USER_ACTIVE`, `ban_reason=null`; success
  notify.
- The `CheckStatus` middleware still enforces a ban on the account's **next** request (no force-logout);
  F15 adds no session/login coupling.

## Relationship to the F10 generic row

The F10 `AdminAuditLog` middleware still logs its generic `admin.request.mutation` row for the status
POST (entity inferred as the guard + id, **no** before/after, **no** request body). The F15 semantic
row is **complementary** (adds the named action + before/after status + safe flags), not a duplicate.
Tests assert specifically on `action='account.status.changed'`.

## RBAC

Each status route already carries `permission:admin.{guard}.status.update` (F9). **No middleware
change**, no new slug. `rbac.enforce` stays **false** (allow-by-default); status behavior unchanged.
No deny-by-default.

## Deferred (next phases)

- **F16 — KYC approve/reject** (`*.kyc.approved` / `*.kyc.rejected`): has a `notify()` side effect →
  non-transactional like reply; capture status flags only, never KYC documents/paths.
- Profile `update()` (ev/sv/ts verification toggles) and `add.sub.balance` (money) → separate later,
  owner-gated phases. Then F17 deposit/withdraw → gateway/settings last.

## Status after F15

Account-status semantic audit wired for all three guards; full suite green (**194 tests / 1887
assertions / 1 skipped**, up from 188 / 1857 / 1). Route count **798** unchanged. `rbac.enforce`
remains **false**. No behavior change to status, no `ban_reason`/PII in the audit, no
routes/migrations/real-DB, no money/KYC/gateway touched.
