# Support-Ticket Reply — Semantic Admin-Audit (F14b)

**Status:** F14b adds the second semantic admin-mutation event — `support.ticket.replied` — around
admin ticket reply. **Behavior is unchanged**: validation, ticket/message saves, attachment upload,
email/SMS via `notify()`, and the redirect/flash are all as before; only an audit row is added,
**after** the reply completes, capturing **metadata only**. No reply behavior change, no shared-trait
edit, no routes added (route count **798**), no Inertia/Vue/Blade change, no migration, no real DB,
`rbac.enforce` still **false**. Companions: `F14-SUPPORT-TICKET-AUDIT.md` (close),
`ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md`, `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`,
`ADMIN-AUDIT-LOG-FOUNDATION.md`.

## What F14b shipped

- **Event:** `support.ticket.replied` (house style `<domain>.<entity>.<past-verb>`), via the
  already-wired `AdminAuditLogger::logMutation()` (`result='succeeded'`).
- **Where:** an override of `replyTicket(Request $request, $id)` in
  `app/Http/Controllers/Admin/SupportTicketController.php`. The admin controller already `use`s the
  **shared** `SupportTicketManager` trait; the override extends the existing alias block
  (`replyTicket as protected runReplyTicket`) and:
  - captures `before = ['status' => $ticket?->status]` and the ticket's current max `SupportMessage` id;
  - calls `runReplyTicket($request, $id)` — the unchanged trait reply (validation, saves, attachments,
    email/SMS, redirect/flash);
  - **only if a new admin message was actually created** (id greater than the captured max, with
    `admin_id` = the acting admin) writes the audit row; returns the trait response verbatim.
  - **The shared trait is not edited**, so user/agent/merchant reply flows are unaffected.

## Why non-transactional (and how it differs from F14 close)

F14's close override wrapped the mutation in `DB::transaction`. **Reply does not**, deliberately:
reply performs **post-save side effects** — attachment upload to disk and `notify()` email/SMS — that
run *after* the message/status saves and have no transaction today. Wrapping reply in a transaction
would **change failure behavior** (a thrown email/attachment error would roll back a message that
currently persists). So the reply audit is written **after** the reply completes and is **not** part
of a transaction.

`AdminAuditLogger::logMutation()` remains **fail-open** (unchanged): a failed audit write is caught
and file-logged, and the reply is unaffected (it already completed). F14b therefore makes **no**
atomicity guarantee for the reply audit; it guarantees only that:
- audit logging never alters or breaks the reply;
- a web **validation failure** throws before any write and bypasses the log → **no** audit row;
- a **missing ticket** aborts 404 before any write → **no** audit row;
- the row is written **only if a new admin message exists** (covers the attachment-failure path where
  the message was created but the trait returned an error redirect).

## What the audit row captures (and excludes)

| Field | Value |
|---|---|
| `action` | `support.ticket.replied` |
| `result` | `succeeded` |
| `entity_type` / `entity_id` | `ticket` / the ticket id |
| `before_values` | `{ status: <prior> }` |
| `after_values` | `{ status: 1 (TICKET_ANSWER) }` |
| `metadata` | `{ ticket: <ref>, message_id, message_length, attachment_count }` |
| admin + `method`/`path`/`ip`/`route_name`/`correlation_id` | from the admin + Request |

**Never captured:** the message body or HTML, attachment names/paths/contents, private paths, user
PII beyond the ticket reference, tokens/secrets. `message_length` is a character count (not the body);
`attachment_count` is a row count (not names). `sanitizeMetadata` still redacts SENSITIVE_KEYS and
caps size.

## Relationship to the F10 generic row

The F10 `AdminAuditLog` middleware still logs its generic `admin.request.mutation` row for the reply
POST. The F14b semantic row is **complementary** (adds the named action + before/after + safe
metadata), not a duplicate. Tests assert specifically on `action='support.ticket.replied'`.

## RBAC

`admin.ticket.reply` already carries `permission:admin.support.reply` (F9). **No middleware change.**
`rbac.enforce` stays **false** (allow-by-default); reply behavior unchanged. No deny-by-default.

## Deferred (next phases)

- Ticket message **delete** (`support.ticket.message.deleted`): destructive hard-delete; needs
  content-capture-before-delete; deferred.
- Then F15 user/merchant/agent status → F16 KYC approve/reject → F17 deposit/withdraw → gateway/settings.

## Status after F14b

Reply semantic audit wired; full suite green (**188 tests / 1857 assertions / 1 skipped**, up from
183 / 1832 / 1). Route count **798** unchanged. `rbac.enforce` remains **false**. No behavior change to
any ticket flow, shared trait untouched, no routes/migrations/real-DB, no email/notification change,
no money/KYC/gateway touched.
