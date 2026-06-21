# Support-Ticket Close — Semantic Admin-Audit (F14)

**Status:** F14 wires the **first** semantic admin-mutation audit event — `support.ticket.closed` —
around admin ticket close. **Behavior is unchanged**: close still sets `status → TICKET_CLOSE`,
redirects, and flashes the same notify; only an audit row is added (inside the same transaction).
No reply/delete change, no shared-trait edit, no routes added (route count **798**), no Inertia/Vue
change, no migration, no real DB, `rbac.enforce` still **false**. Companions:
`ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md` (F8d contract), `ADMIN-MUTATION-CONTRACT-AUDIT.md`,
`ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`, `ADMIN-AUDIT-LOG-FOUNDATION.md`, `RBAC-DENY-BY-DEFAULT-READINESS.md`.

## What F14 shipped

- **Event:** `support.ticket.closed` (house style `<domain>.<entity>.<past-verb>`; it is the name
  pre-registered for F14 in `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`). Written via the now-wired
  `AdminAuditLogger::logMutation()` (`result='succeeded'`).
- **Where:** an override of `closeTicket($id)` in `app/Http/Controllers/Admin/SupportTicketController.php`.
  The admin controller already `use`s the **shared** `SupportTicketManager` trait; the override aliases
  the trait method (`closeTicket as protected runCloseTicket`) and wraps it:
  - load the ticket, capture `before = ['status' => $ticket?->status]`;
  - in `DB::transaction(...)`: run `runCloseTicket($id)` (the unchanged trait close — mutation +
    redirect/flash), then `logMutation('support.ticket.closed', $admin, 'ticket', $ticket->id,
    $before, ['status' => TICKET_CLOSE], ['ticket' => $ticket->ticket], request())` (guarded by
    `if ($ticket)`); return the trait's response verbatim.
  - **The shared trait is not edited**, so user/agent/merchant close flows are unaffected (the class
    method shadows the trait method only for the admin controller).
- **Service tweak (additive, backward-compatible):** `AdminAuditLogger::logMutation()` gained an
  optional trailing `?Request $request = null`. When passed (HTTP context) the row also carries
  `method/path/ip/route_name/correlation_id`; with the default `null` it stays a CLI-style row, so all
  prior callers/tests are unaffected.

## What the audit row captures (and excludes)

| Field | Value |
|---|---|
| `action` | `support.ticket.closed` |
| `result` | `succeeded` |
| `entity_type` / `entity_id` | `ticket` / the ticket id |
| `before_values` | `{ status: <prior: 0/1/2> }` |
| `after_values` | `{ status: 3 (TICKET_CLOSE) }` |
| `metadata` | `{ ticket: <reference number> }` |
| `admin_id` / `admin_username` / `method` / `path` / `ip` / `route_name` / `correlation_id` | from the admin + Request |

**Never captured:** message body, attachment names/paths, user PII beyond the ticket reference.
`sanitizeMetadata` still redacts SENSITIVE_KEYS and caps size. Status values are the integer
constants from `app/Constants/Status.php` (OPEN 0 / ANSWER 1 / REPLY 2 / CLOSE 3).

## Relationship to the F10 generic row

The F10 `AdminAuditLog` middleware still logs its generic `admin.request.mutation` row (HTTP context,
no before/after) for the same close request. The F14 semantic row is **complementary** — it adds the
named action and the before/after status — not a duplicate. Tests assert specifically on
`action='support.ticket.closed'`.

## Atomicity & safety

The `logMutation` call is made **inside** the same `DB::transaction` as the status save. So **when the
audit write succeeds it participates in that transaction** and is committed together with the status
change (the F8d intent). However, `logMutation` remains **fail-open** — a policy F14 does **not**
change: if its own DB write fails, the exception is caught and the failure is recorded via the existing
logger fallback (file channel), and the ticket close still succeeds with its behavior unchanged.
F14 therefore does **not** guarantee that an audit row always persists alongside every close; it
guarantees only that (a) audit logging never breaks the close, and (b) on the success path the audit
row is written within the close's transaction. A trait `abort(404)` for a missing ticket throws inside
the transaction → rollback → **no** audit row and the same 404 as before.

## RBAC

`admin.ticket.close` already carries `permission:admin.support.close` (F9). **No middleware change.**
`rbac.enforce` stays **false** (allow-by-default); close behavior is unchanged. No deny-by-default.

## Deferred (next phases)

- **F14b — reply** (`support.ticket.replied`): metadata only (message length, attachment count, status
  change) — **never** the body text or attachment paths; note the email/SMS side-effect. Same
  override-not-trait approach.
- **delete** (`support.ticket.message.deleted`): destructive hard-delete; needs content-capture-before-
  delete; deferred.
- Then F15 user/merchant/agent status → F16 KYC approve/reject → F17 deposit/withdraw → gateway/settings.

## Status after F14

Close semantic audit wired; full suite green (**183 tests / 1832 assertions / 1 skipped**, up from
178 / 1813 / 1). Route count **798** unchanged. `rbac.enforce` remains **false**. No behavior change to
any ticket flow, shared trait untouched, no routes/migrations/real-DB, no money/KYC/gateway touched.
