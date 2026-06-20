# Admin Panel — Read-Only Inertia Migration Plan (F5)

**Status:** docs-only. **No admin UI was migrated this sprint.** This is the forward plan +
classification. The companion freeze list is `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`.

Source of truth: `routes/admin.php` (admin guard group). Classification legend:
- 🟢 **read-only safe** — GET, display only, no secrets, no mutation. Candidate for Inertia.
- 🟡 **caution** — read-only but exposes sensitive PII/credentials, or sits next to a mutation; migrate only with careful prop curation.
- 🔴 **frozen** — mutation, money, KYC approval, secrets, or dangerous system op (see freeze list).
- ⚪ **out of scope** — binary/file/JSON/AJAX endpoints, admin auth (see F4d).

## Recommended sequencing (future phases, not this sprint)
The admin panel shares the same MD3 component kit already built for user/merchant/agent
(`TransactionTable`, `Pagination`, `PanelTransactions`, filter bars, `Card`). A read-only admin
migration should reuse them behind a new `AdminLayout` + guard-aware shared props (extend
`HandleInertiaRequests` to resolve the `admin` guard — currently it returns null for admin).

1. **A1 — Dashboard & charts** (🟢): `admin.dashboard` + chart JSON endpoints kept as JSON, consumed by Vue chart components.
2. **A2 — Read-only lists** (🟢): deposit/withdraw/transaction **list** pages, profit/revenue logs, report (login/notification history), subscriber index, support ticket lists, system info. Pure paginated tables + filters — lowest risk, highest reuse.
3. **A3 — User/agent/merchant manager lists** (🟢 list / 🟡 detail): the list/segment pages are safe; the **detail** and **kyc-data** pages are 🟡 (balances, KYC PII, merchant API keys) and need server-side curation before exposure.
4. **A4 — Escrow read-only** (🟢 lists / 🔴 actions): escrow list/detail/milestone views are read-only; `action` + `message.reply` are frozen.
5. **Settings & secret-bearing pages**: **not** part of any read-only migration — see freeze list.

## Page classification by area

### Dashboard / reports — 🟢
`admin.dashboard`; `trx.detail.graph`; `chart.deposit.withdraw`; `chart.transaction`;
`chart.charge.commission`; `chart.registration` (chart endpoints return JSON — keep as JSON).
`report.transaction`; `report.login.history`; `report.notification.history`; `report.email.details`;
`admin.notifications` (list). 🟡 `report.login.ip.history` (security data).

### User / Agent / Merchant managers — 🟢 lists, 🟡 detail
🟢 `*.all/active/banned/email.verified/email.unverified/mobile.verified/mobile.unverified/
kyc.unverified/kyc.pending/with.balance/list/segment.count/notification.log`.
🟡 `*.detail/{id}` (balances), `*.kyc.details/{id}` (KYC PII).
🔴 mutations: `kyc.approve`, `kyc.reject`, `update`, `add.sub.balance`, `status`, `login/{id}`
(impersonation), `notification.single/all` send → freeze list.

### Revenue / Profit — 🟢
`profit.all/only/commission/search` (read-only). ⚪ `profit.export.csv` (file download).

### Escrow — 🟢 read-only views
`escrow.index/accepted/not.accepted/completed/disputed/canceled/details/milestone`;
⚪ `escrow.message.get` (AJAX JSON). 🔴 `escrow.action`, `escrow.message.reply` (dispute/money).

### Deposits — 🟢 lists
`deposit.list/pending/rejected/approved/successful/initiated/details`. 🔴 `deposit.approve`,
`deposit.reject` (money).

### Withdrawals — 🟢 lists
`withdraw.data.pending/approved/rejected/all/details`. 🔴 `withdraw.data.approve`,
`withdraw.data.reject` (money); `withdraw.method.*` create/edit/update/status (payout config).

### Support tickets — 🟢 lists/view
`ticket.index/pending/closed/answered/view`. ⚪ `ticket.download`. 🔴 `ticket.reply/close/delete`.

### Subscribers — 🟢 index
`subscriber.index`. 🔴 `subscriber.send.email`, `subscriber.remove`.

### System information — 🟢 (read-only) / 🔴 (ops)
🟢 `system.info`, `system.server.info`. 🔴 `system.optimize`, `system.optimize.clear`,
`system.update*` (dangerous system operations).

### Settings, gateways, notifications, currency, charges, language, modules, KYC settings, cron, extensions/plugins, frontend/page-builder, SEO — 🔴 / 🟡
These are configuration + **secret-bearing** + code/CMS-mutation surfaces. **All frozen** —
see `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`. Even their GET pages routinely render secrets
(SMTP/SMS/push credentials, gateway API keys, social client secrets) and must not be serialized
into Inertia props.

## Out of scope this sprint (and reasons)
- Admin auth: login stays Blade (F4d, captcha). Admin password recovery deferred with it.
- File/CSV/binary downloads and AJAX JSON endpoints: not page migrations.
- Guard-aware shared props for the `admin` guard: must be added (carefully, secret-free) before any admin Inertia page.

## Hard rule for any future admin migration
Never place secrets into props: SMTP/mail password, SMS/push API keys, gateway secret keys,
social client secrets, merchant `secret_api_key`, KYC documents. Curate server-side; expose only
display-safe fields. KYC approval, balance adjustment, deposit/withdraw approval, and all settings
writes remain Blade until a dedicated, audited admin phase.
