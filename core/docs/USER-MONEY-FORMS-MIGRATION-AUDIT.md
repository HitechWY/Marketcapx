# User Money-Forms Migration Audit (F2e — docs only)

Audit of every authenticated-user money-movement flow, to plan a future (post-F2) Inertia
migration. **No code was changed in F2e.** These forms remain Blade and frozen until each has
contract + atomicity tests.

## Cross-cutting findings (apply to ALL flows below)

1. **Two-step "confirm" pattern.** Most flows are: GET form → POST `*Confirm`/`*Money` (validate +
   compute charge, store a pending `UserAction` with details) → user **authorization** (OTP/2FA/email)
   → GET/POST `*Done`/confirm method that performs the actual wallet mutation. The migration must
   preserve BOTH steps and the authorization hop.
2. **Wallet mutation is direct & not transaction-wrapped.** Confirm methods do
   `$wallet->balance -= …` / `+= …` + create `Transaction` rows **without `DB::beginTransaction`**
   (verified: transfer/exchange/payment/money-out/voucher/escrow). This is the known atomicity risk
   ([[audit-known-risks]]); a frontend migration must NOT touch it, and a backend hardening phase
   (work/escrow-atomicity etc.) should precede or stay independent. **Do not refactor in the FE phase.**
3. **CSRF.** All money POSTs use standard web CSRF **except `user/deposit`** (in the
   `preventRequestForgery` except-list, `bootstrap/app.php`). Deposit migration must keep that exemption
   and the gateway redirect contract.
4. **Recipient lookup.** transfer/payment/money-out resolve recipient by `username` OR `email`
   (`/user/exist`, `/merchant/exist`, `/agent/exist` AJAX existence checks) — preserve those endpoints
   and field names.
5. **Gating.** Each flow is wrapped by `module:<slug>` and/or `kyc` middleware — preserve exactly.
6. **Migration approach (recommended):** **native HTML POST** (like F1c contact / F2c profile) to the
   unchanged handlers, OR Inertia `useForm` only if proven contract-identical incl. the OTP/authorization
   hop. Money amounts/charges must remain **server-computed** (never client-calculated).

## Per-flow audit

| Flow | Route(s) (name) | Method | Controller@method | Blade view | Inputs (validated) | Mutation point | Risk |
|---|---|---|---|---|---|---|---|
| **Deposit** | `user.deposit.index/insert/confirm/manual.*` | GET/POST | `Gateway\PaymentController@deposit/depositInsert/depositConfirm/manual*` | `templates/basic/user/payment/*`, `deposit_history` (done F2b) | `amount` (req,numeric,gt:0), `method_code` (req), `currency` (req) [+ manual fields] | gateway success/IPN credits wallet (`depositConfirm`→gateway ProcessController) | **High** (gateways, IPN, CSRF-exempt) |
| **Withdraw** | `user.withdraw`, `.money`, `.preview`, `.submit`, `.methods`, `.method.add/edit/update` | GET/POST | `User\UserWithdrawController@withdraw/withdrawMoney/withdrawPreview/withdrawSubmit/...` | `templates/basic/user/withdraw/{withdraw_money,preview,methods,add_method,edit_method}` | `amount`, `method_code`/method id, dynamic `withdraw_information` fields | `withdrawSubmit` debits wallet + creates Withdrawal | **High** (KYC-gated, dynamic method fields, payout) |
| **Transfer** | `user.transfer` (+ `.done`, `check.exist`) | GET/POST | `User\UserOperationController@transfer/transferMoney/transferMoneyDone` | `templates/basic/user/transfer*` | `user` (recipient), `amount` (req,gt:0), wallet/currency | `transferMoneyDone`: sender `balance -=`, receiver `balance +=` (no tx wrap) | **High** |
| **Money Out** | `user.money.out` (+ `.done`, `agent.check.exist`) | GET/POST | `User\MoneyOutController@moneyOut/moneyOutConfirm/moneyOutDone` | `templates/basic/user/money_out*` | `agent`, `amount` (req,gt:0) | `moneyOutDone`: user `-=`, agent `+=` (+commission) | **High** |
| **Make Payment** | `user.payment` (+ `.done`, `merchant.check.exist`) | GET/POST | `User\MakePaymentController@paymentFrom/paymentConfirm/paymentDone` | `templates/basic/user/make_payment*` | `merchant`, `amount` (req,gt:0) | `paymentDone`: user `-=`, merchant `+=` (+merchant charge) | **High** |
| **Exchange** | `user.exchange.money` | GET/POST | `User\MoneyExchangeController@exchangeForm/exchangeConfirm` | `templates/basic/user/exchange*` | `amount` (req,gt:0), from/to wallet | `exchangeConfirm`: fromW `-=`, toW `+=` | **High** |
| **Request Money** | `user.request.money`, `.requests`, `.history`, `.accept`, `.reject` | GET/POST | `User\UserOperationController@requestMoney/confirmRequest/requestAccept/requestReject` | `templates/basic/user/request*` | recipient, `amount`; accept triggers a transfer | accept path mutates wallets | **High** |
| **Invoice** | `user.invoice.*` (create/update/publish/discard/pay) | GET/POST | `User\InvoiceController@*` | `templates/basic/user/invoice*` | invoice items/amount/currency (validated) | payment path mutates wallet | **High** |
| **Voucher** | `user.voucher.create/redeem/list/log` | GET/POST | `User\VoucherController@userVoucherCreate/RedeemConfirm/...` | `templates/basic/user/voucher*` | `amount` (create), `code` (redeem) | create: `balance -=`; redeem: `balance +=` | **High** |
| **Escrow** | `user.escrow.*` (steps 1-3, accept/dispute/dispatch/cancel, milestone pay) | GET/POST | `User\EscrowController@*`, `MilestoneController@*` | `templates/basic/user/escrow*` | step fields, `amount` (numeric,gt:0), item updates | dispatch/accept: locked wallet `±` (see work/escrow-atomicity) | **Very High** (multi-step, disputes, atomicity work in flight) |
| **KYC submit** | `user.kyc.submit` | POST | `User\UserController@kycSubmit` | `templates/basic/user/kyc/form` | dynamic Form fields + **file uploads** (FormProcessor) | writes KYC data + files | **High** (uploads, not money but mutation) |

## Required contract tests BEFORE migrating any flow
- **Field/contract test:** rendered form posts identical action/method/field names/hidden fields/`_token`
  (+ deposit CSRF-exempt) to the unchanged handler.
- **Amount-parity test:** server-computed `charge`/`final_amount`/post-balance identical to the Blade
  baseline for fixed inputs (no client-side money math).
- **Two-step/authorization test:** the `*Confirm` → authorization → `*Done` sequence still works
  (OTP/2FA hop intact).
- **Atomicity test (pre-req, backend):** confirm-method wallet mutation under failure does not
  half-apply — should be addressed by a backend hardening phase, NOT the FE migration.
- **Idempotency test:** double-submit / replay of the confirm step does not double-mutate (note: deposit
  has `work/api-idempotency-safety`; user web flows need equivalent coverage before migration).

## Recommended migration phasing (future, post-F2)
- **F3a (lower risk first):** Voucher, Invoice, Request Money (list/accept) — once contract + amount-parity
  tests exist.
- **F3b:** Transfer, Money Out, Make Payment, Exchange — peer-to-peer wallet mutations; require
  atomicity/idempotency coverage first.
- **F3c (highest risk, last):** Deposit (gateways + IPN + CSRF-exempt), Withdraw (payouts + dynamic method
  fields), Escrow (multi-step + disputes + atomicity work).
- Each: **native HTML POST**, server-computed amounts, preserve `module:*`/`kyc` gates, keep Blade
  fallback + one-line rollback, full Playwright payment contract tests as the merge gate.

## F2e attestation
Docs-only. No controller/route/form/DB change. No payment/wallet/KYC/gateway/IPN logic touched.
