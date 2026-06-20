# Migration DRAFTS — DO NOT RUN against the development DB

These are **draft** migrations for Phase 7 (database integrity). They live in
`migrations-drafts/` (NOT `migrations/`) so `php artisan migrate` will **not**
pick them up automatically.

## Rules
- Do NOT run these against the live `paytabz_DB`.
- The application schema currently lives in the SQL dump, not in migrations, so
  there is no full migration baseline yet. Adding constraints must be validated
  against a disposable COPY of the database first.

## Safe apply procedure (requires explicit approval + fresh backup)
1. Create a throwaway copy DB from the dump (e.g. `paytabz_DB_phase7`).
2. Pre-flight on the COPY before adding the wallets unique index:
   ```sql
   SELECT user_id, user_type, currency_id, COUNT(*) c
   FROM wallets GROUP BY user_id, user_type, currency_id HAVING c > 1;
   ```
   If any rows return, dedupe/merge first — the unique index will fail otherwise.
3. Move the desired draft(s) into `core/database/migrations/` and run
   `php artisan migrate` **against the COPY**.
4. Run the app regression suite against the COPY.
5. Only then schedule a maintenance-window apply to a real DB, with a fresh backup.

## Drafts
- `2026_01_01_000001_add_unique_and_index_to_wallets.php`
  - UNIQUE(user_id, user_type, currency_id) — prevents duplicate wallets / split balances.
- `2026_01_01_000002_add_indexes_to_financial_tables.php`
  - Non-unique performance indexes on transactions(user_id), transactions(wallet_id),
    transactions(trx), deposits(trx), withdrawals(user_id).
- `2026_01_01_000003_create_rbac_roles_permissions.php` (Phase 6 / RBAC)
  - **PROMOTED in F9a** to `database/migrations/2026_01_01_000003_create_rbac_roles_permissions.php`
    (with pivot FKs + an `admins.role_id` index added). No longer a draft. Still run only against
    the disposable `marketcapx_test`; real-DB apply is owner-gated. See `docs/RBAC-SCHEMA.md`.

## Deliberately NOT included
- A UNIQUE index on `transactions.trx`: transfers/payments create multiple rows
  sharing one `trx` (e.g. sender '-' and receiver '+'), so a plain unique would
  break legitimate data. Idempotency at the DB layer needs a carefully scoped
  key (see phase3 / wallet-ledger design) and dedicated analysis first.
- Foreign keys: require orphan cleanup on a copy before they can be added.

## Foreign-key strategy (Phase H notes — no FK migration shipped yet)
Adding FKs is constrained by the schema's **polymorphic ownership**: `wallets`,
`transactions`, `deposits`, `withdrawals`, etc. carry `user_id` + `user_type`
(`USER` | `AGENT` | `MERCHANT`), so `user_id` may reference `users`, `agents`, or
`merchants` depending on the row. A single `user_id -> users.id` FK would be wrong
and reject agent/merchant rows. Options (decide before drafting FK migrations):
1. Leave owner columns FK-less (enforce in app/WalletService) — lowest risk.
2. Split per-owner-type tables or add a polymorphic morph map — larger redesign.

**Safe, non-polymorphic FK candidates** (still need orphan-check on a COPY first):
- `wallets.currency_id`, `transactions.currency_id`, `deposits.currency_id`,
  `withdrawals.currency_id` -> `currencies.id`
- `gateway_currencies.method_id` -> `gateways.id`
- `transactions.wallet_id` -> `wallets.id`
Pre-flight each on a COPY: `SELECT ... WHERE child_id NOT IN (SELECT id FROM parent)`.
No FK migration is included until these checks + an ownership decision are made.
