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

All preserve every amount/charge/commission/remark/notification/redirect; only
transaction + lock + single-use scaffolding was added, with notifications moved
after commit. All `php -l` clean; the route/limiter/boot test suite stays green
(23 passed, 2 skipped). **Money math paths are not yet exercised by tests** (no
model factories; schema is dump-based) — see §4.

## 2. Deferred — documented, NOT rewritten (apply the §0 pattern with tests)

The single-pattern deferred flows (transfer, withdrawal-submit, invoice,
hosted API payment, milestone) were hardened + test-covered in the
`work/deferred-money-flows` wave and are now in §1. **Escrow** remains
documentation-only — it is a genuine multi-party state machine where a blind
rewrite risks changing payout/dispute behavior, and it needs purpose-built
multi-party fixtures before any code change.

| Flow | Controller / method | Concrete gaps (verified) / what to do |
| ---- | ------------------- | ------------------------------------- |
| Escrow dispatch (release to seller) | `User/EscrowController::dispatchEscrow()` (~L502–570) | Seller wallet credit + seller-charge deduction + ledger run with **no `DB::transaction`, no `lockForUpdate`**, guarded only by an `accepted()` status read outside any lock. Wrap the credit+charge+ledger, lock the escrow row and re-check status under the lock, lock the seller wallet. |
| Escrow dispute resolution (admin) | `Admin/EscrowController::action()` (~L147–252) | Credits **both** buyer and seller wallets based on the dispute outcome with no tx/lock and only a `disputed()` status pre-check. Highest-risk path: wrap both credits + ledger, lock both wallets id-ascending, re-check the escrow status under the lock so a double-resolve cannot double-pay. |
| Escrow accept / cancel / dispute | `User/EscrowController::accept()/cancel()/dispute()` | Status-only transitions (no balance move). Lower risk, but the status flip should still be re-checked under a lock to avoid conflicting concurrent transitions. |

For each: apply the §0 pattern — transaction + `lockForUpdate` on every wallet
and the escrow row, re-check state/balance under the lock, side effects after
commit, locking multiple wallets in id-ascending order. **Build multi-party
escrow fixtures first** (buyer + seller + escrow + milestones) and add
double-submit/double-resolve tests before touching the code. Validate against
the disposable `marketcapx_test` DB (see `tests/README.md`).

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
Suite: **42 passed, 1 skipped** (`BlockchainIpnTest`, which needs an
external-API mock). Only **escrow** (§2) remains without tests; it needs
multi-party fixtures before implementation.

## 5. Constraints honored
No migrations/seeders on `paytabz_DB`; no real gateway/payment calls; no broad
rewrites (only transaction/lock/consume scaffolding added, behavior preserved).

See `docs/GATEWAY-IPN-AUDIT.md`, `docs/DEPENDENCY-LOCK-RECOVERY.md`, and
`docs/HARDENING.md` for related findings.
