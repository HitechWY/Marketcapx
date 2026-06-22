# Admin Notification Send — Semantic Admin-Audit (F18)

**Status:** F18 adds one semantic admin-mutation event — `account.notification.sent` — around the admin
"send notification" actions (single + bulk) for users, agents, and merchants. **Behavior is unchanged**:
validation, the all-channels-disabled guard, the default-template guard, the `findOrFail`, the
empty-segment guard, the actual `notify()` transport, the batch/session progression, and the
redirect/flash are all as before; only a **category-only** audit row is added on the success path. The
audit records the **guard, send mode, requested channel, recipient count, scope name, and an
image-attached boolean** (plus batch markers for bulk) — **never** the subject, message/body, HTML,
template body, recipient PII, image filename/path, or recipient IDs. No new routes (route count **798**),
no controller change, no migration, no real-DB mutation, `rbac.enforce` still **false**,
`notify.dry_run` still **false** by default. Companions: `F17-ACCOUNT-PROFILE-UPDATE-AUDIT.md`,
`F16-KYC-DECISION-AUDIT.md`, `F15-ACCOUNT-STATUS-AUDIT.md`, `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`.

## Scope

- **Event:** `account.notification.sent` (house style `account.<entity>.<verb>`; the guard is carried in
  `entity_type` + `metadata.guard`, mirroring F15/F16/F17). Written via `AdminAuditLogger::logMutation()`
  (`result='succeeded'`).
- **Where:** `app/Lib/UserNotificationSender.php` — methods `notificationToSingle($request,$userId,$userType)`
  and `notificationToAll($request,$userType)`, the guard-parameterized helpers shared by **users, agents,
  and merchants** (`$userType` ∈ `User|Agent|Merchant`; the plural guard slug `users|agents|merchants` is
  derived as `strtolower($userType).'s'`). These are invoked by
  `ManageGuardController@sendNotificationSingle` / `@sendNotificationAll`, which are **unchanged** — they
  validate, apply the global notification-enabled guard, and delegate. Admin-only.
- **Routes (unchanged):** `POST admin/{guard}/notification/single/{id}` (`admin.{guard}.notification.single`)
  and `POST admin/{guard}/notification/all` (`admin.{guard}.notification.all.send`), each already carrying
  its F9 permission. No middleware change, no new slug, no deny-by-default.

## Placement (minimal)

The audit row is written **after** a successful `sendNotifications()` call and **before** the success
redirect (single) / before `manageSessionForNotification()` (bulk). It therefore runs **only** after every
existing guard has passed:

- single: after the default-template guard (`isTemplateEnabled`) and `model::findOrFail($userId)`;
- bulk: after the template guard, the selected-user guard (`handleSelectedUsers`), and the empty-segment
  guard (`$totalUserCount <= 0`).

There is **no `DB::transaction`** (unlike F16/F17): the send is a non-transactional outbound side effect, so
the audit is a single best-effort `logMutation()` after the send, not a co-commit. `notify()` and the
redirect/flash stay exactly as before.

## Payload contract — what the audit row captures

| Field | Value |
|---|---|
| `action` | `account.notification.sent` |
| `result` | `succeeded` |
| `entity_type` | guard — `users` / `agents` / `merchants` |
| `entity_id` | the account id (single) / **`null`** (bulk — there is no single target) |
| `before_values` | `[]` (empty) |
| `after_values` | `[]` (empty) |
| `metadata` (single) | `{ guard, mode:"single", channels_requested:[via], recipient_count:1, recipient_scope:"single", image_attached:bool }` |
| `metadata` (bulk) | single keys (with `mode:"bulk"`, `recipient_count`=current batch count, `recipient_scope`=segment name) **plus** `{ batch_start, batch_size, is_final_batch }` |
| admin + `method`/`path`/`ip`/`route_name`/`correlation_id` | from the admin (`auth('admin')->user()`) + Request |

- `channels_requested` — the requested channel as a one-element list `[$request->via]` (`email`/`sms`/`push`).
  A channel **name** only, never message content.
- `recipient_count` — single: always `1`; bulk: `$users->count()` for **the current batch only** (not a
  cumulative total, not a list of recipients).
- `recipient_scope` — single: the literal `"single"`; bulk: the segment **method name**
  (`$request->being_sent_to`, e.g. `selectedUsers`, `allUsers`, `topDepositedAgents`, `notLoginUsers`). A
  scope name, never recipient identities.
- `image_attached` — a **boolean** (`(bool) $imageUrl`) signalling whether a push image was attached. Never
  the filename, path, or uploaded-file path.
- `batch_start` / `batch_size` — the pagination `start` / `batch` integers from the request. `is_final_batch`
  — boolean (`($request->start - 1) + $users->count() >= $totalUserCount`) marking the last batch of a
  paginated bulk run.

## Explicit exclusions — what the audit row NEVER carries

No `subject`; no `message` / body; no rendered HTML; no notification **template body**; no raw
`NotificationLog` message; no image **filename** / **path** / uploaded-file path; no **recipient IDs** in
bulk metadata (`entity_id` is `null` and no id list is stored); no recipient **names / emails / mobiles /
usernames**; no FCM/device tokens; no provider/transport responses; no secrets / API keys; no
wallet / payment / gateway / IPN / webhook / API data; no `dry_run` flag. `before`/`after` are empty arrays;
`metadata` holds only the safe keys above. `AdminAuditLogger::sanitizeMetadata` still redacts
`SENSITIVE_KEYS` as a backstop.

## Fail-open logger caveat

`logMutation` is **fail-open** (by design, F10): if the audit write throws, it is caught and routed to a file
channel, and the admin notification action still completes — the send is never broken by an audit failure.
Because the send is **not** transactional and the audit runs after it, F18 **does not guarantee** that every
notification send has a durable DB audit row; it guarantees that a successful send *attempts* an audit write
on the normal path. Do not overclaim guaranteed audit persistence.

## Failure / guard paths write no audit row

- **Default template disabled** — `isTemplateEnabled($via)` is false → early `back()` before the send and
  the audit.
- **Validation failure** — `message` / `via` / `subject` (and bulk `start`/`batch`/`being_sent_to`/
  `cooling_time`) rules throw in the controller before the helper runs.
- **All channels disabled** — the controller's `!gs('en') && !gs('sn') && !gs('pn')` guard redirects before
  the helper runs.
- **Selected-user / empty-segment** — bulk: missing selected users, or `$totalUserCount <= 0`, early-returns
  before the send and the audit.
- **404** — single: `findOrFail($userId)` aborts before any write.

These are covered by tests asserting `count('account.notification.sent') === 0`.

## Relationship to the F10 generic row

The F10 `AdminAuditLog` middleware still logs its generic `admin.request.mutation` row for the notification
POST (entity inferred as guard + id, no before/after, no body). The F18 semantic row is **complementary**
(adds the named event + the channel/scope/count metadata), not a duplicate. Tests assert specifically on
`action='account.notification.sent'`.

## Tests

`tests/Feature/AdminNotifyDryRunControllerTest.php` (DB-backed on `marketcapx_test`, `DatabaseTransactions`
rolls back, every send path under the F18a `notify.dry_run=true` seam — no real email/SMS/push, no network):
6 tests / 60 assertions — single-user success + audit row with exact allow-list metadata and PII/content
sentinel-exclusion assertions; agent + merchant `entity_type` coverage; bulk success with one batch row
(`entity_id=null`, `recipient_count`=batch count, no recipient IDs); validation→no row;
all-channels-disabled→no row; segment-count read-only→no row. F18a `NotifyDryRunHarnessTest` (5/6) covers the
dry-run seam itself. Targeted regression re-run green: F17 account-profile (8/33), F16 KYC decision (6/29),
F15 account status (6/30); route-count pin `ZiggyExposureTest` (2/27, **798**). Local full suite after F18c:
**219 tests / 2015 assertions / 1 skipped — green**.

## Operational caveats

- **Outbound notification send is irreversible.** Once `notify()` dispatches email/SMS/push, it cannot be
  recalled; the audit row records that a send was attempted, not a reversible state change.
- **Audit is best-effort / fail-open** (above) — do not gate a release on every row persisting.
- **`notify.dry_run` is a test-only seam** (F18a) that skips the outbound transport while still exercising
  the success path; it **defaults to `false`** and is set `true` only inside tests. Production never runs the
  notify path with `dry_run`.
- **No production deploy or migration** was performed for F18; no real-DB mutation, no schema change.

## Status after F18

Admin notification-send semantic audit wired for all three guards (single + bulk). Route count **798**
unchanged. `rbac.enforce` remains **false** (allow-by-default; no deny-by-default); `notify.dry_run` default
**false**. No behavior change to the notification flow, no notification content / recipient PII / image
path / `dry_run` in the audit, no routes / migrations / real-DB mutation, no
money / wallet / gateway / IPN / webhook / API behavior touched. Full-suite total recorded in the F18c
session report.
