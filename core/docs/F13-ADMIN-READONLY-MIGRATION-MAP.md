# Admin Read-Only Inertia Migration Map & Freeze List (F13)

**Status:** F13 migrated **one** remaining genuinely-read-only admin Blade page — the per-user
**notification log** — to Inertia, curated to metadata only (the raw message body is never sent as
a prop). No admin behavior changed, no mutation migrated, no routes added (route count **798**), no
migrations, no real DB, `rbac.enforce` still **false**. This doc is the source-of-truth map of what
is Inertia vs Blade across the admin panel and why the remaining Blade pages stay frozen.
Companions: `ADMIN-READONLY-INERTIA-MIGRATION-PLAN.md` (F5), `F6-ADMIN-READONLY-SPRINT-REPORT.md`,
`INERTIA-PROPS-SECURITY-AUDIT.md` (F7), `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`,
`ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md` (F8), `F12-WOULD-DENY-REVIEW.md`.

## What F13 shipped

- `ManageGuardController@notificationLog($id)` now returns `inertia('Admin/NotificationLog', …)`
  instead of `view('admin.reports.notification_history', …)`. This single guard-parameterized
  method serves all three routes: `admin.users.notification.log`, `admin.agents.notification.log`,
  `admin.merchants.notification.log` (`GET /admin/{guard}/notification-log/{id}`).
- New read-only page `resources/js/Pages/Admin/NotificationLog.vue` + types
  (`AdminNotificationLogRow`, `AdminGuardSummary`) in `resources/js/types/admin.ts`.
- Prop contract (metadata only, via `mapNotificationRow()` + `->through()`):
  `{ id, subject, channel, type, sentTo, sentFrom, read, date, dateHuman }`.
  **Excluded:** `message` (raw HTML body — XSS/freeze risk), `image`/raw file paths, and all
  account secrets. The `user` header prop is curated to `{ id, username, fullname, userType }`.
- The shared Blade view `resources/views/admin/reports/notification_history.blade.php` is **kept**
  (still used by the frozen global report route — see below) and referenced by a one-line rollback
  comment in the controller.
- Tests: `InertiaMigrationSmokeTest` (guard + mount) and `InertiaPropExposureTest` (asserts
  `message`/secrets absent, raw body not present in the response).

## Migration map — admin panel

### Inertia, read-only (done)
- `admin.dashboard` (F6)
- `admin.{users|agents|merchants}.*` list pages — all/active/banned/etc. (F6)
- `admin.deposit.*` / `admin.withdraw.data.*` **list** pages (F6)
- `admin.report.transaction`, `admin.report.login.history`, `admin.report.login.ip.history` (F6)
- `admin.ticket.index` / ticket **list** pages (F6)
- **`admin.{users|agents|merchants}.notification.log`** — per-user notification log (**F13, new**)

### Blade — frozen (NOT migrated), with reason
| Page (route) | Reason it stays Blade |
|---|---|
| `admin.{guard}.detail` | Mixed read+write (balance adjust / ban / KYC links) **and** shows wallet **balances** (balances are Blade-only per freeze rule). |
| `admin.deposit.details` / `admin.withdraw.data.details` | Approve/reject mutations on page + bank/gateway PII in the detail JSON. |
| `admin.ticket.view` | Reply / close / delete forms are the page's primary purpose. |
| `admin.escrow.details` / `admin.escrow.milestone` | Money-domain (escrow); details also carries reply/action mutations. Deferred. |
| `admin.report.notification.history` / `admin.report.email.details` | Render **raw HTML** email/notification bodies (XSS). The global report keeps using the shared Blade view. |
| `admin.{guard}.kyc.details` | KYC document viewer + approve/reject; private files via signed routes only. |
| `admin.gateway.*.edit`, `admin.setting.*`, notification settings | **Secrets/API keys** (gateway params, SMTP/SMS/push creds, socialite secrets) — never to the client. |
| `admin.{guard}.login` (impersonation) | GET side-effect; must become an audited POST before any migration. |
| `admin.notifications`, `admin.profile`, `admin.password`, `admin.cron.*` | Forms / side-effect GETs / high-blast-radius ops. |

## Data that must never appear in admin read-only props

Raw `message`/email HTML bodies · wallet `balance` · `password`/`remember_token`/`tsc`
(2FA)/`secret_api_key`/`public_api_key` · raw `kyc_data` and KYC/private file paths (use signed
`admin.download.attachment` routes only) · gateway/SMTP/SMS/push/socialite credentials ·
withdrawal/deposit destination credentials. (Enforced by `InertiaPropExposureTest`.)

## RBAC / audit relationship

`rbac.enforce` stays **false** (allow-by-default). The notification-log GET is read-only and
ungated — consistent with the other migrated read-only report pages — so no new permission gate and
no new audit hook are introduced (the audit foundation targets state-changing requests, which this
is not). No deny-by-default.

## Deferred to F14+ (audited safe-mutation-endpoint phases)

All detail/mutation pages above require the F8d safe-mutation-endpoint contract (dedicated POST,
FormRequest validation, RBAC permission, before/after audit, atomicity/idempotency) and — for the
real environment — the F11 schema apply + F12 would-deny review first. Order: F14 support-ticket →
F15 user/merchant/agent status → F16 KYC approve/reject → F17 deposit/withdraw → gateway/settings
last. Impersonation (`admin.{guard}.login`) must be converted GET→POST with audit before migration.

## Status after F13

One read-only page migrated; full suite green (**178 tests / 1813 assertions / 1 skipped**, up from
176 / 1783 / 1). Route count **798** unchanged. `rbac.enforce` remains **false**. No migration run,
no real DB touched, no admin behavior changed, no routes added, no secrets/raw HTML in props.
