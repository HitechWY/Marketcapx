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
- **User → merchant payment** — `User/MakePaymentController::paymentDone()`.
  The debit (user) + credit (merchant) + up-to-three ledger rows ran with **no
  transaction** (a mid-flow failure could debit the payer without paying the
  merchant) and on **unlocked** wallet reads, and the one-shot `UserAction`
  intent was **never consumed** — a refreshed/replayed done-route request
  re-charged the payer (worse when no OTP is configured, since `paymentConfirm`
  redirects straight to the done route). Fix: wrap in `DB::transaction`, lock
  both wallet rows in id-ascending order, re-check the payer balance under the
  lock, and `delete()` the `UserAction` inside the transaction so the flow runs
  exactly once. Every amount/charge/ledger field is unchanged; notifications
  moved after commit. `php -l` clean.
  Residual: enforcing OTP `used_at` at the done route is a separate pre-existing
  concern, now moot for replay because the intent row is deleted on success.
  **Untested** (no test DB) — validate before relying on it in production.

## 3. Flows to review next (NOT changed — need a test DB to verify safely)

These controllers mutate `wallet->balance` directly and should be confirmed to
use the §0 transaction/lock pattern. They are user-facing balance moves where a
concurrency race or missing transaction could corrupt balances. Prioritized:

| Priority | Flow / controller | Concern to confirm |
| -------- | ----------------- | ------------------ |
| Done | `User/MakePaymentController` | FIXED in §2 — atomic, locked, single-use. |
| High | `Api/User/MakePaymentController` | API twin of the fixed flow; apply the same transaction/lock/consume pattern |
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
