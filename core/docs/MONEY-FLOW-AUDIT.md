# Money-Flow Audit

Trace of the wallet-mutating flows for **atomicity** (transaction wrapping) and
**idempotency** (no double credit/debit under replay/concurrency). Phase 13
fixed the first few; the overnight pass (`work/overnight-hardening`) hardened the
remaining high-traffic user flows. The complex multi-party state machines are
documented with the exact pattern but deliberately **not** rewritten blind (no
fixture-based tests yet — per "prefer reports/plans if risky without tests").

The canonical safe pattern established in this repo (Phase 3/4):

```php
DB::transaction(function () use (...) {
    // lock the state row (deposit / voucher / UserAction) and re-check under the lock
    $row = Model::where('id', $id)->lockForUpdate()->first();
    if (!$row /* or already-consumed/wrong-status */) return;   // idempotent no-op
    // lock EVERY involved wallet row in a consistent (id-ascending) order
    // re-check the payer balance under the lock
    // mutate balances + insert ledger rows + consume the one-shot token
});
// side effects (notify / admin notification) only AFTER commit, reading captured balances
```

## 1. Hardened (verified, committed)

| Flow | Controller / method | Notes |
| ---- | ------------------- | ----- |
| Inbound deposit credit | `Gateway/PaymentController::userDataUpdate()` | Phase 3/4: locked tx, status re-check, WalletService credit. |
| Admin withdrawal reject refund | `Admin/WithdrawalController::reject()` | Phase 13: locked tx + status re-check + wallet lock. |
| Admin withdrawal approve | `Admin/WithdrawalController::approve()` | No balance move (debit at request time); status-guarded. Unchanged. |
| User→merchant payment (web) | `User/MakePaymentController::paymentDone()` | Phase 13B: locked tx, both wallets, delete UserAction (one-shot). |
| User→merchant payment (API) | `Api/User/MakePaymentController::paymentDone()` | Phase 5: locked tx, both wallets, used=1 inside tx. |
| Money exchange (web + API) | `User/`+`Api/User/MoneyExchangeController::exchangeConfirm()` | Phase 5: locked tx, both wallets, source re-check; web null-deref fix. |
| Money out (web + API) | `User/`+`Api/User/MoneyOutController::moneyOutDone()` | Phase 5: locked tx, user+agent wallets, commission, consume action. |
| Money in (web + API) | `Agent/`+`Api/Agent/MoneyInController::moneyInDone()` | Phase 5: locked tx, agent+user wallets, commission, consume action; web user-wallet null guard. |
| Voucher create (web + API) | `User/`+`Api/User/VoucherController::userVoucherCreateDone()` | Phase 5: locked tx, wallet lock, consume action (no double-mint). |
| Voucher redeem (web + API) | `User/`+`Api/User/VoucherController::userVoucherRedeemConfirm()` | Phase 5: lock voucher + mark-used-under-lock (kills double-redeem race); web creates wallet if missing. |
| Admin add/subtract balance | `Admin/ManageGuardController::addSubBalance()` | Phase 5: locked tx, wallet lock, sufficiency re-check. |

All preserve every amount/charge/commission/remark/notification/redirect; only
transaction + lock + single-use scaffolding was added, with notifications moved
after commit. All `php -l` clean; the route/limiter/boot test suite stays green
(23 passed, 2 skipped). **Money math paths are not yet exercised by tests** (no
model factories; schema is dump-based) — see §4.

## 2. Deferred — documented, NOT rewritten (apply the §0 pattern with tests)

These were intentionally left for a test-backed pass because they are
multi-step / multi-state and a blind rewrite risks a behavior regression:

| Flow | Controller / method | Why deferred / what to do |
| ---- | ------------------- | ------------------------- |
| Escrow | `User/EscrowController` (`dispatchEscrow`, `accept`, `cancel`, `dispute`), `Admin/EscrowController` | Multi-party escrow state machine with dispute/release branches; each balance move must be wrapped + locked and the escrow status transition re-checked under the lock. Highest value, highest regression risk without tests. |
| Milestone | `User/MilestoneController::payMilestoneDone()` | Milestone pay/release tied to escrow state; same pattern, lock the milestone + wallet rows. |
| Invoice payment | `User/InvoiceController::invoicePaymentConfirmDone()` | Two-party settlement; wrap debit+credit+ledger, lock both wallets, guard against paying an already-paid invoice (status under lock). |
| API gateway payment | `GetPaymentController::verifyPaymentConfirm()` | Payer+merchant move; set the apiPayment status inside the locked tx before/with crediting (currently set after). |
| Transfer money | `User/UserOperationController::transferMoney*` / `Api/.../UserOperationController` | User→user transfer; lock both wallets, consume the action. |
| Withdrawal submit (debit) | `Traits/WithdrawProcess::withdrawSubmitDone()`, `Api/Withdraw` | Debits at submit time; wrap the debit + withdrawal-record insert and consume the action under a lock. |

For each: apply the §0 pattern — transaction + `lockForUpdate` on every wallet
and the state row, re-check state/balance under the lock, consume the one-shot
token inside the tx, side effects after commit. Lock multiple wallets in
id-ascending order to avoid deadlocks. Validate against the disposable
`marketcapx_test` DB (see `tests/README.md`) before shipping.

## 3. Notes / observations (not changed)

- **Money exchange `to_wallet` is not scoped to the user** (`Wallet::find($to_wallet_id)`
  rather than `checkWallet(...)`). Not a profitable attack (you spend your own
  funds), but a user could credit another account's wallet. Left as-is to avoid
  changing authorization behavior blind; flag for product review.
- Several web "done" routes are GET and rely on the now-consumed one-shot
  `UserAction` for replay safety; enforcing OTP `used_at` at the done route is a
  separate pre-existing hardening item.

## 4. Test coverage (added)

The money-math is now exercised by DB-backed tests (`Feature/MoneyFlow/` +
`Feature/DepositIdempotencyTest`) using `Tests\Concerns\CreatesMoneyFixtures`
(hand-built fixtures, since the app has no factories) and `DatabaseTransactions`
against the disposable `marketcapx_test` DB. Coverage: deposit, web+API payment,
money-out, money-in, exchange (overdraft-safety), withdrawal reject, admin
balance — each asserts double-submit → exactly one effect — plus a
`WalletAtomicity` rollback test. Suite: **35 passed, 1 skipped**
(`BlockchainIpnTest`, which needs an external-API mock). The §2 deferred flows
still need their own tests as they are implemented.

## 5. Constraints honored
No migrations/seeders on `paytabz_DB`; no real gateway/payment calls; no broad
rewrites (only transaction/lock/consume scaffolding added, behavior preserved).

See `docs/GATEWAY-IPN-AUDIT.md`, `docs/DEPENDENCY-LOCK-RECOVERY.md`, and
`docs/HARDENING.md` for related findings.
