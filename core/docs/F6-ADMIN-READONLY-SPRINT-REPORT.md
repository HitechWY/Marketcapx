# F6 — Admin Read-Only Inertia Sprint — Report

**Outcome:** the first safe admin read-only Inertia surface (dashboard, lists, reports) shipped and
merged to `master` (pushed to `origin` = HitechWY/Marketcapx). No admin business logic, mutation,
approval, secret, or setting was changed; admin login + all sensitive actions stay Blade.

## Baseline → final
- Start: `master == origin/master == 1578ab3`, route 798, suite 100/623/1.
- Final (through F6c): `master == origin/master == 88223df`; +F6d docs on top. Working tree clean.
- Route count: **798** throughout (controllers re-pointed in place; no routes added/removed).
- Tests: 100 → **112** (+12), suite green, 1 pre-existing skip.
- Build ✓ (chart.js/vue-chartjs added; bundle >500 kB triggers a non-fatal chunk-size warning),
  vue-tsc ✓, composer validate ✓.

## Phases / commits
| Phase | Type | feat | merge / commit |
|------|------|------|------|
| F6a dashboard + foundation | code | `26f9664` | merge `71dc0e2` |
| F6b read-only lists | code | — | `72e6147` (committed directly on master — see note; branch ref pushed) |
| F6c reports/logs | code | `76aa752` | merge `88223df` |
| F6d docs freeze update | docs | — | (this branch) |

> **Process note (F6b):** the F6b phase branch was not created before implementation, so its work
> landed as a single plain commit `72e6147` on master (then pushed). The code, gates, and content
> are correct; a branch ref `frontend/inertia-admin-readonly-lists` was created at `72e6147` and
> pushed for traceability. No history was rewritten.

## Migrated to Inertia (read-only)
- **Dashboard** `admin.dashboard` — metric/summary cards, login doughnuts, 4 time-series charts
  (chart.js) fed by the unchanged `admin.chart.*` JSON endpoints.
- **Lists** — Users/Agents/Merchants (`Admin/GuardList`, all 10 scope variants), Deposits,
  Withdrawals, Support tickets.
- **Reports** — Transaction report, Login history (+ by-IP).

## Intentionally still Blade
Admin login (captcha); all detail/KYC/ticket-reply pages; approve/reject/status/balance/KYC
mutations; gateway/payment-method/system/notification/cron/role settings; `notificationHistory`
(raw message body + recipient) and `emailDetails` (raw email HTML). See
`ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`.

## Architecture
- `HandleInertiaRequests` admin branch (gated on `auth('admin')`) → secret-free `auth.user` +
  server-resolved `adminNav()`. Shared backend `userGuard()` left unchanged.
- `AdminLayout` reuses guard-agnostic `PanelShell`/topbar/sidebar. New `Components/Admin/*`
  (MetricCard, Chart, Table, FilterBar, StatusBadge) + `BuildsAdminReadonly` trait (status→chip,
  currency/userType options).
- Rows explicitly field-mapped (no password/remember_token/secret_api_key/balance/kyc_data/
  gateway-`detail`/`withdraw_information`); status as tone chips (no raw admin badge HTML).
  Filters/pagination/query preserved (`appends(request()->all())`).

## Safety confirmations
- No payment/wallet/KYC/gateway/IPN/checkout/escrow/admin-action logic changed.
- No secrets in props; no Ziggy/client `route()` (URLs server-resolved); admin nav exposes only
  migrated read-only routes + profile/logout.
- No schema/migrations; tests use `makeAdmin()` + `DatabaseTransactions` (rollback; no persistent rows).
- `core/.env` never read/edited/staged; no secret files committed.
- All Blade admin views retained as one-line rollback.

## Tests added
`AdminDashboardTest` (2), `AdminReadonlyListsTest` (6), `AdminReportsTest` (4) — DatabaseTransactions,
disable Laramin Utility/GoToCore + AdminAuditLog.

## Remaining risks / next
- chart.js enlarges the bundle (chunk-size warning) — consider code-splitting/manualChunks later.
- Detail pages remain Blade → mixed Inertia-list / Blade-detail navigation (full reload on "Details").
- Next: admin detail pages would require moving their inline mutations to dedicated audited
  endpoints first; notification/email viewers need a sanitization strategy. Admin mutation/settings
  migration remains a separate, audited effort.
