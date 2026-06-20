# F2b — User Read-Only Lists (Plan)

**Branch:** `frontend/inertia-user-readonly-lists` (from master @ 8637e89)

## Scope
Migrate read-only user list pages to Inertia/Vue. **No money-movement create/submit forms.**
- `user.transactions` (`/transactions`) — `UserController@transactions` (filters + pagination)
- `user.wallets` (`/wallets`) — `UserController@wallets`
- `user.deposit.history` (`/deposit/history`) — `UserController@depositHistory` (pagination + search)
- `user.withdraw.history` (`/withdraw/history`) — `UserWithdrawController@withdrawLog` (pagination + search; **only this read-only method**, no money methods touched)

## Routes / contracts preserved
No route added/removed/renamed (count stays 798). All query params preserved: transactions
`search,type,operation,time,currency`; deposit/withdraw `search`; pagination `page`. Server-side
calcs unchanged (paginators passed via `->through()` to shape display fields only).

## Controller methods (convert read-only only; Blade `view()` kept as one-line rollback)
- `transactions()` → `inertia('User/Transactions', { transactions(paginator), filters{current}, currencies })`
- `wallets()` → `inertia('User/Wallets', { wallets })`
- `depositHistory()` → `inertia('User/DepositHistory', { deposits(paginator), search })`
- `withdrawLog()` → `inertia('User/WithdrawHistory', { withdraws(paginator), search })`

## Vue files
- `Pages/User/{Transactions,Wallets,DepositHistory,WithdrawHistory}.vue`
- `Components/User/{FilterBar,WalletList}.vue`; reuse `TransactionTable` (F2a), `Pagination` (F1).
- Extend `types/user.ts`.

## Tests (tests/Feature/UserReadonlyListsTest.php — DatabaseTransactions, rollback)
- guest → 302 login for each route.
- authed (`makeUser` + actingAs, gates disabled in-test) → Inertia component per page, has paginator props.
- transactions: filters/query preserved (e.g. `?type=plus_trx` reflected in props), only current user's data.
- no money-form fields on these pages.

## Gates / auto-merge / rollback / stop
Standard sprint gates. Auto-merge if all green, only read-only pages changed, no money contract change,
route count 798, Ziggy clean, no persistent DB mutation, visual QA OK. Rollback: restore commented
`view()` per method. Stop if any page needs a money form or backend-logic change.
