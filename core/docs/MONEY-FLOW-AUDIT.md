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
| Transfer money (web + API) | `User/`+`Api/User/UserOperationController::transferMoneyDone()` | Deferred wave: locked tx, both wallets id-ascending, balance re-check, consume action (web delete / API used=1). |
| Withdrawal submit (web + API) | `Traits/WithdrawProcess::withdrawSubmitDone()`, `Api/Withdraw::withdrawSubmitDone()` | Deferred wave: lock withdrawal (status==0) + wallet, re-check balance, flip status + consume action under lock (kills concurrent double-debit). |
| Invoice payment (web) | `User/InvoiceController::invoicePaymentConfirmDone()` | Deferred wave: lock invoice (pay_status==0) + both wallets, debit/credit, flip pay_status, consume action; receiver-wallet null guard added. |
| Hosted API payment (web) | `GetPaymentController::verifyPaymentConfirm()` | Deferred wave: lock apiPayment (status==0) + both wallets, set status BEFORE crediting, IPN curl/notify moved after commit. |
| Escrow milestone pay | `User/MilestoneController::payMilestoneDone()` | Deferred wave: lock milestone (unfunded) + wallet + escrow row, re-check balance, debit + fund + bump escrow paid_amount, consume action. |
| Escrow dispatch (release to seller) | `User/EscrowController::dispatchEscrow()` | `work/escrow-atomicity`: wrap status flip + seller credit + seller-charge + ledger in one tx; lock escrow + re-check `ESCROW_ACCEPTED` under lock (concurrent/duplicate dispatch = idempotent no-op); lock seller wallet; notify after commit. |
| Escrow dispute resolution (admin) | `Admin/EscrowController::action()` | `work/escrow-atomicity`: wrap status change + buyer/seller payouts + ledger in one tx; lock escrow + re-check `ESCROW_DISPUTED` under lock (no double-pay); lock both wallets id-ascending; fixed the `dispute_charge` precedence bug (now `paid_amount - (buyer + seller)`, records-only — payouts unchanged). |

All preserve every amount/charge/commission/remark/notification/redirect; only
transaction + lock + single-use scaffolding was added, with notifications moved
after commit. All `php -l` clean; the route/limiter/boot test suite stays green
(23 passed, 2 skipped). **Money math paths are not yet exercised by tests** (no
model factories; schema is dump-based) — see §4.

## 2. Deferred — remaining lower-risk follow-ups

The single-pattern deferred flows (transfer, withdrawal-submit, invoice,
hosted API payment, milestone) were hardened in the `work/deferred-money-flows`
wave, and the two **money-moving escrow paths** (dispatch + admin dispute
resolution) were hardened + test-covered in the `work/escrow-atomicity` wave —
all now in §1. What remains is escrow's **status-only** transitions, which move
no money:

| Flow | Controller / method | Note |
| ---- | ------------------- | ---- |
| Escrow accept / cancel / dispute | `User/EscrowController::accept()/cancel()/dispute()` | Status-only transitions (no balance move). Lower risk; the status flip could still be re-checked under a lock to avoid conflicting concurrent transitions. |

Two pre-existing escrow correctness items found during the atomicity pass, left
untouched (out of scope — they move no money and changing them alters
notification/UX behavior):

- `User/EscrowController::accept()` uses `if ($escrow->buyer_id = auth()->id())`
  (assignment `=`, not `==`) — always takes the "buyer" branch when picking the
  notification recipient. Notification-only; no balance impact.
- `Admin/EscrowController::action()` references `$buyerWallet`/`$sellerWallet` in
  the notify shortcodes; if only one side is paid (one amount is `0`) the other
  was previously undefined. The `work/escrow-atomicity` change null-guards the
  post-balance shortcodes, but a one-sided resolve path still warrants a product
  review of the intended UX.

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
balance, plus the deferred wave — transfer (web+API), withdrawal-submit
(web+API), invoice payment, hosted API payment, milestone pay — each asserts
double-submit → exactly one effect — plus a `WalletAtomicity` rollback test.
The `work/escrow-atomicity` wave added `EscrowDispatchFlowTest` (single dispatch
credits the seller once + replay no-ops) and `EscrowDisputeResolveFlowTest`
(single admin resolve pays both sides once, records `dispute_charge =
paid_amount - (buyer + seller)`, replay no-ops), with `makeEscrow()` /
`makeConversation()` fixtures. Suite: **44 passed, 1 skipped**
(`BlockchainIpnTest`, which needs an external-API mock). All money-moving flows
are now atomic + idempotent and test-covered; only escrow's status-only
transitions (§2) remain as a lower-risk follow-up.

## 5. Constraints honored
No migrations/seeders on `paytabz_DB`; no real gateway/payment calls; no broad
rewrites (only transaction/lock/consume scaffolding added, behavior preserved).

See `docs/GATEWAY-IPN-AUDIT.md`, `docs/DEPENDENCY-LOCK-RECOVERY.md`, and
`docs/HARDENING.md` for related findings.
