# Backend / Financial Hardening Gap Audit (Phase 7)

**Date:** 2026-06-18 · **Baseline audited:** `master` @ `8d74c3f` ("merge: land Phase 5
public conversion polish") · **Type:** read-only, evidence-anchored. No code or DB was
changed to produce this audit; `paytabz_DB` was not touched and no secrets/PII are printed.

## 1. Executive summary

The money layer is **largely hardened**: 14 of 15 wallet-mutating flows are atomic +
idempotent and DB-test-covered (**PHPUnit 42 passed / 1 skipped**), gateway IPNs were
previously audited (two real bugs fixed — see `GATEWAY-IPN-AUDIT.md`), and rate-limiting +
an admin audit log are live. Remaining risk is **concentrated, not systemic** — no rebuild
is warranted.

**14 findings.** The one money domain never hardened is **escrow**; the other items are a
single gateway signature gap, a KYC-document exposure, the (intentional) flat-admin model,
an authorization-scope gap on money-exchange, a boot/DX coupling, and several Low/Info
hardening-hygiene items.

**Top priorities:** escrow atomicity (F1/F2) → Coinpayments IPN signature (F3) → KYC
document exposure (F4) → money-exchange wallet scoping (F6).

## 2. Risk register

Severity = Impact × Likelihood × Exploitability, fintech-weighted (Critical / High / Medium
/ Low / Informational). Verdict = Open / Partially-hardened / Hardened.

| # | Finding | Evidence (file : method : line) | Verdict | Risk | Branch |
|---|---------|--------------------------------|---------|------|--------|
| F1 | Escrow release moves funds with no transaction/lock → concurrent double-credit | `User/EscrowController::dispatchEscrow` L505,518–519,538 | Open | High→Crit | `work/escrow-atomicity` |
| F2 | Admin dispute resolution credits buyer+seller with no transaction/lock → double-payout | `Admin/EscrowController::action` L156,178–179,205–206 | Open | High | `work/escrow-atomicity` |
| F3 | Coinpayments IPN trusts non-secret `merchant_id`, no HMAC → forgeable deposit credit | `Gateway/Coinpayments/ProcessController::ipn` L76 | Open | High | `work/gateway-residuals` |
| F4 | KYC ID docs in web-served `assets/verify` with predictable `uniqid().time()` filenames | `Constants/FileInfo` + `Lib/FileManager::getFileName` L230 | Open | High* | `work/kyc-pii-hardening` |
| F5 | Flat admin — any admin reaches any action; audited but not enforced | `Middleware/RedirectIfNotAdmin` L18; `Models/Admin`; draft `…000003` | Open (by-design) | High (priv) | `work/admin-rbac-enforcement` |
| F6 | Money-exchange `to_wallet` not user-scoped (push funds to any wallet) | `User/MoneyExchangeController::exchangeConfirm` L52 + API twin | Partial (atomic, unscoped) | Medium | `work/exchange-wallet-scope` |
| F7 | Boot-time `ModuleSetting::get()` couples framework boot to DB → `artisan test` fails | `Providers/AppServiceProvider::boot` L77 | Open | Medium | `work/boot-test-isolation` |
| F8 | Dispute charge mis-computed (operator precedence) | `Admin/EscrowController::action` L163 | Open | Low (correctness) | `work/escrow-atomicity` |
| F9 | MD5 signature schemes / unused sig key (Skrill, PerfectMoney, SslCommerz, TwoCheckout, Aamarpay) | per `GATEWAY-IPN-AUDIT.md §4.2–4.3` | Open (sandbox) | Low | `work/gateway-residuals` |
| F10 | IPN routes public + unthrottled | `routes/ipn.php` | Open | Low | `work/api-idempotency` |
| F11 | API `depositInsert` has no idempotency key (credit still IPN-gated) | `Api/PaymentController::depositInsert` | Open | Low | `work/api-idempotency` |
| F12 | Gateway create-methods echo `$e->getMessage()` to client | Coingate / Mollie / BTCPay | Open | Low/Info | `work/gateway-residuals` |
| F13 | `kyc_data` stored plaintext | `Models/User` (`kyc_data` cast object) | Open | Info | `work/kyc-pii-hardening` |
| F14 | KYC approve/reject direct `save()` (idempotent, no money) | `Admin/ManageGuardController::kycApprove/Reject` L170–198 | Open | Info | `work/kyc-pii-hardening` |

\* F4 severity assumes the production Apache/cPanel layer ships no deny rule over
`assets/verify`; the repo ships none. Confirm before finalizing.

## 3. Evidence (per finding)

- **F1/F2/F8 — Escrow (primary gap).** `dispatchEscrow` reads `Escrow::…->accepted()->findOrFail`
  *outside* any lock, then `$wallet->balance += $amount; save()` and a second
  `-= seller_charge; save()` with no `DB::transaction` / `lockForUpdate` / re-check. Admin
  `action` credits buyer (L178–179) and seller (L205–206) the same way after a `disputed()`
  read (L156). Two concurrent/duplicate submits both pass the unlocked status check →
  double-credit / double-payout; a mid-sequence failure leaves a partial (status flipped,
  one ledger row written). Matches the documented deferral in `MONEY-FLOW-AUDIT.md §2`.
  F8: L163 `paid_amount - buyer_amount + seller_amount` parses as
  `(paid_amount - buyer) + seller` and mis-records `dispute_charge`.
- **F3 — Coinpayments.** The shared credit sink (`Gateway/PaymentController::userDataUpdate`)
  is locked + status-guarded, but this handler's authenticity gate (L76) is
  `merchant_id` (semi-public) + amount + `status == PAYMENT_INITIATE`, with no HMAC (zero
  `hash_hmac` in the file). An attacker who knows the public merchant id can self-confirm an
  unpaid pending deposit. Aligns with `GATEWAY-IPN-AUDIT.md §4.2`.
- **F4 — KYC exposure.** Web-root = project root; `assets/verify` is publicly served;
  `FileManager::getFileName()` returns `uniqid().time().ext` (the `time()` part is fully
  predictable), enabling enumeration of uploaded ID documents.
- **F5 — RBAC.** `RedirectIfNotAdmin` checks only authentication; `AdminAuditLog` records
  metadata only (never the request body — good) but enforces nothing. RBAC schema +
  middleware are designed (`RBAC-DESIGN.md`) and drafted (`migrations-drafts/…000003`) but
  not run/enforced; the current flat state is intentional.
- **F6 — Exchange scope.** Atomicity is already fixed (tx + both-wallet lock, L100–160);
  only `to_wallet` lacks the `checkWallet(...)` user-scope that `from_wallet` has (L52,
  `Wallet::find($to_wallet_id)`). The caller spends their own funds, so it is an
  authorization/integrity gap (credit an arbitrary wallet), not a theft vector.
- **F7 — Boot.** `AppServiceProvider::boot` L77 calls `ModuleSetting::get()` unconditionally,
  so framework boot needs a reachable DB; `php artisan test` (console env → prod `.env` DB)
  fails, hence the documented `APP_ENV=testing php …/phpunit` workaround. The view-composer
  `.count()` queries only fire when their admin views render.

## 4. Reconciliation appendix (confirmed-correct — do NOT re-open)

- **Hardened + tested (14 flows)** per `MONEY-FLOW-AUDIT.md §1`: deposit credit, transfer
  (web+API), withdrawal submit/approve/reject, user→merchant payment (web+API), money-in/out
  (web+API), voucher create/redeem (web+API), admin add/subtract balance, invoice payment,
  hosted-API payment, milestone pay, money-exchange atomicity. Pattern: `DB::transaction` +
  `lockForUpdate` (id-ascending) + status/balance re-check under lock + one-shot consume +
  side-effects after commit.
- **Documented gateway false positives** per `GATEWAY-IPN-AUDIT.md §2–3`: TwoCheckout
  (query-level `where('status',0)` idempotency), CoinbaseCommerce (HMAC-SHA256),
  NowPaymentsHosted (HMAC-SHA512 + query idempotency), Stripe SDK variants
  (`Webhook::constructEvent`). A broad gateway-by-gateway scan misreads these — confirm each
  claim against source before acting.

## 5. Remediation roadmap (future phases — each test-first vs `marketcapx_test`, one branch each)

1. `work/escrow-atomicity` (F1/F2/F8) — **highest.** Multi-party escrow fixtures +
   double-dispatch/double-resolve tests, then apply the canonical tx/lock/re-check pattern;
   fix the L163 precedence.
2. `work/gateway-residuals` (F3 first → F9/F12) — needs a gateway sandbox; no blind changes.
3. `work/kyc-pii-hardening` (F4/F13/F14) — move/guard `assets/verify` out of web reach,
   high-entropy filenames, at-rest review.
4. `work/exchange-wallet-scope` (F6) — scope `to_wallet` per product rules + IDOR test.
5. `work/admin-rbac-enforcement` (F5) — run drafts on test DB → allow-by-default middleware
   → per-route → flip deny-by-default.
6. `work/boot-test-isolation` (F7) and `work/api-idempotency` (F10/F11) — robustness/DX.

## 6. Confirmation — audit was read-only

This audit performed inspection only: git baseline checks, codebase exploration, and
targeted source reads. No files were edited, no commits/migrations were run, no server was
started, no DB was mutated, and no secrets or PII were printed. `origin/master` remained at
`8d74c3f` throughout. (This document is the audit's only artifact.)

See `MONEY-FLOW-AUDIT.md`, `GATEWAY-IPN-AUDIT.md`, `RBAC-DESIGN.md`, and `HARDENING.md` for
the prior hardening waves this audit builds on.
