# Money-Flow Audit (Phase 13)

Trace of the wallet-mutating flows for atomicity (transaction wrapping) and
idempotency (no double credit/debit under replay/concurrency). One minimal,
well-understood fix was applied (§2). The broader set is documented with a
prioritized recommendation rather than blind-changed, because applying the
transaction/lock pattern to every flow safely needs a seeded test DB to verify
(per the Phase 13 "prefer reports/plans if risky without tests" guidance).

The canonical safe pattern already established in this repo (Phase 3/4):

```php
DB::transaction(function () use (...) {
    $row = Model::where('id', $id)->lockForUpdate()->first();
    if (!$row || $row->status != EXPECTED) return;   // idempotent no-op
    // ... mutate status + wallet + ledger ...
});
// side effects (notify/admin notification) only after commit
```

## 1. Already hardened (verified)

- **Inbound deposit credit** — `PaymentController::userDataUpdate()`
  (`app/Http/Controllers/Gateway/PaymentController.php:145`). Locked transaction,
  re-checks `status` under the lock, credits via the central `WalletService`,
  fires notifications only after commit. **Correct.**

## 2. Fixed in this pass

- **Admin withdrawal rejection refund** —
  `app/Http/Controllers/Admin/WithdrawalController.php` `reject()`.
  The status flip, wallet refund (`balance += amount`), and ledger insert were
  not wrapped in a transaction and the status read was unlocked. Cross-request
  idempotency existed (`where status = PENDING` + `firstOrFail`), but two
  **concurrent** rejects could both pass the unlocked read and double-refund.
  Fix: wrap in `DB::transaction`, re-fetch the withdrawal with `lockForUpdate()`
  and re-check `PENDING` under the lock, lock the wallet row, and move the
  notification after commit. Refund amount and ledger fields unchanged.
  `php -l` clean.
- **Admin withdrawal approval** — `approve()` mutates **no** balance (the debit
  happens at withdrawal-request time); it only flips status + notifies, and is
  idempotent via the `where status = PENDING` + `firstOrFail` guard. A race would
  at worst duplicate a notification. Left unchanged (low risk, no money effect).

## 3. Flows to review next (NOT changed — need a test DB to verify safely)

These controllers mutate `wallet->balance` directly and should be confirmed to
use the §0 transaction/lock pattern. They are user-facing balance moves where a
concurrency race or missing transaction could corrupt balances. Prioritized:

| Priority | Flow / controller | Concern to confirm |
| -------- | ----------------- | ------------------ |
| High | `User/MakePaymentController`, `Api/User/MakePaymentController` | debit payer + credit payee must be one atomic, locked transaction |
| High | `User/MoneyOutController`, `Api/User/MoneyOutController`, `Agent/MoneyInController`, `Api/Agent/MoneyInController` | cash in/out balance moves; lock + idempotency |
| High | `User/EscrowController`, `Admin/EscrowController`, `User/MilestoneController` | escrow hold/release across parties; partial-failure atomicity |
| Medium | `User/MoneyExchangeController`, `Api/User/MoneyExchangeController` | two-wallet exchange must be atomic |
| Medium | `User/VoucherController`, `Api/User/VoucherController` | voucher redeem must be single-use (idempotent) under concurrency |
| Medium | `User/InvoiceController`, `GetPaymentController` | invoice settlement balance move |
| Low | `Admin/ManageGuardController` | admin balance adjustment; lock the wallet row |

Recommended approach for each: apply the §0 pattern (transaction + `lockForUpdate`
on the wallet row(s) and the state row, re-check state under the lock, side
effects after commit). For multi-party moves (payments, escrow, exchange) lock
**all** involved wallet rows in a consistent order to avoid deadlocks, and assert
the debit side has sufficient balance inside the locked transaction. Validate
each against a seeded disposable test DB (see `tests/README.md`) before shipping;
do not change them blind.

## 4. Constraints honored

- No DB migrations/seeders run; `paytabz_DB` untouched.
- No real gateway/payment endpoints called.
- Only one flow changed, following an existing in-repo pattern; the rest is a
  report. No broad rewrites.

See `docs/GATEWAY-IPN-AUDIT.md` and `docs/HARDENING.md` for related findings.
