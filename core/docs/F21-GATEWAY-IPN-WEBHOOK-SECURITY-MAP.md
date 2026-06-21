# F21 — Gateway / IPN / Webhook Security Map (docs-only, read-only)

**Status:** docs-only consolidation. **No gateway, IPN, webhook, or callback code is changed, added, or
executed.** F21 unifies the source-verified findings from `GATEWAY-IPN-AUDIT.md` (Phase 12) and
`GATEWAY-RESIDUALS-AUDIT.md` (B6) into one risk map with status + severity, so an owner can prioritise a
future **credentialed** hardening pass. No external/gateway endpoint was called; no signature/HMAC logic
was written; no secret or sample IPN payload was created or exposed. `rbac.enforce` stays `false`.

Companions: `GATEWAY-IPN-AUDIT.md`, `GATEWAY-RESIDUALS-AUDIT.md`, `MONEY-FLOW-AUDIT.md`,
`API-IDEMPOTENCY-AUDIT.md`, `HARDENING.md`, `F27-PRODUCTION-READINESS-RUNBOOK.md`.

> **Hard rule (unchanged):** inbound money paths are **not** edited without that gateway's signing scheme
> + a sandbox callback. A blind HMAC change can fail-closed on a money-in path (reject all legitimate
> IPNs). Every remediation below is **owner-gated / credentials-required**.

---

## 1. The reference "good" pattern

A strong handler does, in order: **verify authenticity → check `status == PAYMENT_INITIATE` (or query-
level `where('status', 0)`) → `PaymentController::userDataUpdate()`**, persisting the gateway `detail`
**only after** verification. Idempotency is enforced twice: the per-handler status guard *and* the
Phase-3 DB-level lock inside `userDataUpdate()` (re-checks under `lockForUpdate`). Constants:
`PAYMENT_INITIATE=0`, `PAYMENT_SUCCESS=1`, `PAYMENT_PENDING=2`.

Handlers verified to follow it: **Blockchain** (configured secret + `hash_equals`, fail-closed when
unset), **BTCPay** (`Webhook::isIncomingWebhookRequestValid`), **StripeV3 / StripeJs** (Stripe SDK
`Webhook::constructEvent`), **CoinbaseCommerce** (HMAC-SHA256 over raw body vs `X-Cc-Webhook-Signature`),
**NowPaymentsHosted / NowPaymentsCheckout** (HMAC-SHA512 over sorted body + query-level idempotency).

## 2. Already fixed (source-verified, on master — regression watch only)

| Item | Gateway / file | Nature | Severity (was) |
|---|---|---|---|
| `=` → `==` in credit/idempotency clause | Skrill `ProcessController.php:55` | IPNs never credited (fail-closed/broken, not false-credit) | bug |
| `&&` → `||` in credential reject | Cashmaal `ProcessController.php:47` | public `web_id` let a forged `ipn_key` pass | HIGH (closed) |
| Hardcoded `"MySecret"` removed | Blockchain | now configured secret + `hash_equals`, fail-closed | HIGH (closed) |
| `detail`-write moved after verify | Aamarpay, Instamojo, Paypal, Paystack, Razorpay, Flutterwave | stop recording unverified payloads | hygiene |

(See `GATEWAY-IPN-AUDIT.md §1` for the exact gates each now sits behind.)

## 3. Residual risks (NOT fixed — owner-gated / credentials-required)

### R1 — CoinPayments / CoinPaymentsFiat inbound IPN has no HMAC verification — **HIGH**
`Gateway/Coinpayments/ProcessController::ipn()` and `Gateway/CoinpaymentsFiat/ProcessController::ipn()`
authenticate only by `gateway_parameter.merchant_id == $request->merchant` (+ currency/amount/status
guards). CoinPayments signs every IPN with an **HMAC header** from the account IPN secret; the codebase
computes HMAC only for **outbound** calls (`CoinPaymentHosted.php:154`) and never verifies the **inbound**
header. `merchant_id` is **not secret**, so a crafted POST matching an in-flight deposit's
trx/currency/amount could drive `userDataUpdate()` and credit without a real payment.
**Remediation (credentialed):** read the `HMAC` header, `hash_hmac('sha512', <raw POST body>,
$ipn_secret)`, compare with `hash_equals`, fail closed — validated against a real/sandbox IPN first
(raw-body vs ordered-field format must be confirmed).

### R2 — Raw exception messages returned to the user — **MEDIUM**
~10 gateways surface `$e->getMessage()` (or equivalent) into the user-facing payment response/notification
instead of logging server-side + showing a generic message. Gateway SDK exceptions can embed request
context, endpoint URLs, or provider-side detail.

| Gateway | Location |
|---|---|
| Coinpayments | `ProcessController.php:28` |
| Coingate | `ProcessController.php:42` |
| Mollie | `ProcessController.php:36` |
| Paytm | `ProcessController.php:33` |
| Razorpay | `ProcessController.php:39` |
| StripeV3 | `ProcessController.php:42` |
| BTCPay | `ProcessController.php:38` (+ `:56` into an AdminNotification title) |
| Stripe | `ProcessController.php:107,110` |
| StripeJs | `ProcessController.php:66,78` |
| Bkash | `ProcessController.php:239` (re-throws `new \Exception($e->getMessage())`) |

**Remediation (credentialed):** per row, `Log::error($e)` server-side + return a fixed
"Payment could not be processed, please try again"; verify each happy path with sandbox creds (these sit
in live init/return flows).

### R3 — Weak/legacy signature schemes — **MEDIUM (design)**
MD5-based authenticity (Skrill, PerfectMoney, SslCommerz, TwoCheckout) where the provider offers a
stronger scheme. Constrained by what each gateway supports; not a unilateral change. (TwoCheckout's
idempotency is sound via query-level `where('status',0)` — its earlier "missing guard" flag was a false
positive.)

### R4 — Redirect-param-only trust — **MEDIUM (design)**
Gateways that rely on returned status fields rather than signature/HMAC or a server-to-server re-query
(e.g. Aamarpay loads a signature key but does not use it). Adding a real check needs each gateway's
signing docs + sandbox.

## 4. Idempotency / replay posture (summary)

- **Primary guard:** per-handler `status == PAYMENT_INITIATE` (or `where('status', 0)`) — a replayed
  callback after the status flips finds no eligible row and cannot double-credit.
- **Backstop:** Phase-3 DB locking in `PaymentController::userDataUpdate()` (`lockForUpdate` + re-check).
- **Caveat:** the per-gateway signature matrix is **source-derived, not callback-verified**; R3 confirms
  the matrix end-to-end with sandbox callbacks is itself a remediation item.

## 5. Severity-ranked backlog (for a FUTURE credentialed pass — not done here)

1. **R1 — CoinPayments/CoinPaymentsFiat inbound HMAC (HIGH).** Highest priority: a non-secret
   `merchant_id` gate on a money-in path.
2. **R2 — Generic gateway error messages (MEDIUM).** Cross-cutting info-leak hygiene across ~10 files.
3. **R4 / R3 — Redirect-param trust & MD5→stronger (MEDIUM/design).** Per-gateway, docs + sandbox bound.
4. **Callback-verified signature matrix** confirming §1/§4 end-to-end.

## 6. Caution on automated scans

A structured first pass produced **false positives** (CoinbaseCommerce "persists before verify",
NowPaymentsHosted "no idempotency") that direct source reading disproved. Confirm every gateway claim
against source + a sandbox before acting — query-level status filters and SDK calls are easily misread.

## 7. Status

F21 is docs-only: it consolidates the gateway/IPN/webhook security posture (good pattern, fixed items,
residual HIGH/MEDIUM risks, replay posture) and ranks a credentialed remediation backlog. No gateway/IPN/
webhook/payment behavior changed; no secrets/HMAC written; no routes, no migration, no real DB;
`rbac.enforce` stays `false`.
