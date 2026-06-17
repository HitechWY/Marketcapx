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

## Deliberately NOT included
- A UNIQUE index on `transactions.trx`: transfers/payments create multiple rows
  sharing one `trx` (e.g. sender '-' and receiver '+'), so a plain unique would
  break legitimate data. Idempotency at the DB layer needs a carefully scoped
  key (see phase3 / wallet-ledger design) and dedicated analysis first.
- Foreign keys: require orphan cleanup on a copy before they can be added.
