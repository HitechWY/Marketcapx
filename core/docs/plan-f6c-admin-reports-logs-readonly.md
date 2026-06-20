# F6c — Admin Reports / Logs Read-Only (Plan, as shipped)

Migrate read-only admin report pages to Inertia.

## Migrated
- Transaction report (`ReportController@transaction`) → `Admin/Transactions.vue`. Filters: search,
  user_type, currency, trx_type, remark, date. Columns: User, Type, TRX, Transacted, Amount(+/-),
  Post Balance, Details.
- Login history (`@loginHistory`) + login-by-IP (`@loginIpHistory`) → `Admin/LoginHistory.vue`
  (shared; filter bar only on the main history route; IP-lookup link on the by-IP route).

## Deferred (stay Blade) — documented
- `notificationHistory` — detail modal exposes raw notification message bodies + recipient
  (`sent_to`); sensitive payloads.
- `emailDetails` — renders raw email HTML (arbitrary content). Frozen.
- Cron/system log pages — system-action adjacent.

## Rules / gates
Rows explicitly mapped (no secrets). Filters/pagination/query preserved (`appends(request()->all())`;
`user_type` echo lowercased). Blade report views kept as one-line rollback. Test `AdminReportsTest`
(4). Route count 798.
