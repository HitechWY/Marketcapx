# F22 — Merchant API / API-Key / Callback Security Map (docs-only, read-only)

**Status:** docs-only, read-only. **No merchant-API, API-key, callback, or payment behavior is changed,
added, or executed.** F22 maps the merchant hosted-payment surface — how a merchant is identified, how the
outbound callback (IPN) is signed, idempotency/replay posture, error-leakage, throttling, and secret
handling — so an owner can prioritise a future **owner-gated** hardening pass. No external endpoint was
called; no secret or signature logic was written or exposed. `rbac.enforce` stays `false`.

Companions: `API-IDEMPOTENCY-AUDIT.md` (hosted-checkout replay guards), `F21-GATEWAY-IPN-WEBHOOK-SECURITY-MAP.md`
(inbound gateway IPN), `MERCHANT-SENSITIVE-FORMS-MIGRATION-AUDIT.md` (secret_api_key must never reach Inertia
props), `MONEY-FLOW-AUDIT.md`, `F27-PRODUCTION-READINESS-RUNBOOK.md`.

> **Hard rule (unchanged):** the merchant payment API is a live money path. Nothing here is edited without
> tests on `marketcapx_test` and owner sign-off; SSRF/throttle/signature changes can break legitimate
> merchant integrations if done blind.

---

## 1. Surface (verified read-only)

**Hosted payment flow** (`routes/web.php`, live `GetPaymentController` / test `TestPaymentController`,
shared `Traits/ApiPaymentProcess`):
1. Merchant POSTs `/payment/initiate` with `public_key` + `identifier`, `amount`, `currency`, `ipn_url`,
   `cancel_url`, `success_url`, customer name/email. Merchant is resolved by
   `checkMerchant($public_key) = Merchant::where('public_api_key', …)->first()` — **public key only**.
2. App returns a session-encrypted checkout URL; merchant redirects the payer.
3. Payer verifies via emailed code; `/confirm/payment` settles inside a `DB::transaction` (`api_payments`
   row `status==0` re-checked under `lockForUpdate`, payer/merchant wallets locked in id order).
4. After commit, the app POSTs a **signed** callback to the merchant's `ipn_url`.

There is also a token-authenticated **merchant REST API** (`app/Http/Controllers/Api/Merchant/*`:
auth/login/register, MerchantController, WithdrawController) — separate from the payment-initiate surface
above and out of this map's money-callback focus; its auth tokens are not analysed here.

## 2. Strengths (verified — keep / regression-watch)

| Area | Finding | Evidence |
|---|---|---|
| Callback authenticity | Outbound IPN carries `signature = strtoupper(hash_hmac('sha256', amount.identifier, secret_api_key))` so the merchant can verify it with their secret | `GetPaymentController.php:307-311` (test path identical) |
| Settlement idempotency | `status==0` re-check + `lockForUpdate` inside the tx; concurrent/replayed confirm returns idempotent success | `GetPaymentController.php:~226,297-305`; `API-IDEMPOTENCY-AUDIT.md` |
| Replay hygiene (B4) | `checkEmail` / `verifyPaymentConfirm` / `sendVerifyCode` guard `instanceof ApiPayment` so a settled/expired session returns a clean error, not a fatal | `API-IDEMPOTENCY-AUDIT.md §B4` |
| Error handling | Stale/invalid session returns a fixed `{error, message:'Invalid transaction request'}`; no `$e->getMessage()`/stack trace to the caller | `ApiPaymentProcess.php:121-128` |
| Secret not serialized | `secret_api_key` in Merchant `$hidden` (with `password`, `remember_token`); never emitted to JSON/Inertia | `Merchant.php:29-31`; `MERCHANT-SENSITIVE-FORMS-MIGRATION-AUDIT.md` |
| Callback timing | Status flipped to `1` **inside** the tx; outbound curl + notify run **after** commit | `GetPaymentController.php:305-326` |

## 3. Risks (NOT changed — owner-gated)

### R1 — Callback/redirect URLs not SSRF-guarded — **MEDIUM/HIGH**
`ipn_url`, `cancel_url`, `success_url` are validated only as `required|url`
(`ApiPaymentProcess.php:27-29`). On settlement the server makes a synchronous **outbound POST** to the
merchant-supplied `ipn_url` (`CurlRequest::curlPostContent`, `GetPaymentController.php:309`). With no
private/loopback/link-local block-list, a merchant-controlled (or attacker-supplied) URL can target
internal hosts (`127.0.0.0/8`, `10/8`, `172.16/12`, `192.168/16`, `169.254/16`, cloud metadata
`169.254.169.254`). **Remediation (gated):** resolve + reject private/reserved IP ranges before the curl;
optionally an allow-list / egress proxy. Verify against real merchant URLs first.

### R2 — No rate limiting on payment-initiate / confirm — **MEDIUM**
The payment routes carry no `throttle` middleware (the named limiters in `AppServiceProvider` cover
login/otp/registration/password-reset only). Consequences: `/payment/initiate` spam inflates
`api_payments` (DB-growth DoS); `/confirm/payment` verification-code attempts are not attempt-capped, and
the code is 6 digits (~1-in-1e6) — the DB lock serialises confirms but does not cap guesses.
**Remediation (gated):** add per-IP/per-merchant `throttle` to initiate/confirm/check-mail and a per-payment
verification-attempt cap. Tune limits to real merchant volumes.

### R3 — Signed payload covers only `amount.identifier` — **MEDIUM (design)**
The HMAC input is `amount . identifier`; `status`, `payment_trx`, `currency`, and `payment_timestamp` are
sent in `data` but are **not** in the signed material. A merchant validating only the signature gets
integrity on amount+identifier, not on the other fields. **Remediation (gated):** extend the signed
content (or sign the canonical JSON body) and update the public API doc + merchant guidance together so
existing integrations don't break.

### R4 — Shared HMAC secret stored reversibly at rest — **MEDIUM (inherent)**
`secret_api_key` must be reversible (it is the HMAC signing key), so it cannot be one-way hashed like a
password; it is stored as-is. It is already excluded from serialization (`$hidden`), so the residual is
**DB-at-rest exposure** + the absence of a rotation/revocation flow (only `generateApiKey()` was found).
**Remediation (gated):** at-rest encryption of the secret column and a documented rotate/revoke
lifecycle. Not a code change to make blind.

### R5 — Public doc shows `==` signature comparison — **LOW (guidance)**
The merchant-facing example (`api_documentation.blade.php`) compares the received signature with `==`
rather than a constant-time compare. This is merchant-side guidance, not server code, but the doc should
recommend `hash_equals` to merchants. **Remediation (gated):** docs/template wording only.

## 4. Could-not-verify (flagged)

- Whether IPN delivery **retries** on failure (no retry logic observed) — outbound curl is fire-and-after-
  commit; a failed merchant endpoint may miss the callback.
- Whether **deprecated keys are revoked/rotated** (generation found; no deletion/rotation path observed).
- The token-auth scheme of the separate `Api/Merchant/*` REST controllers (out of scope here).

## 5. Severity-ranked backlog (FUTURE owner-gated pass — not done here)

1. **R1 SSRF guard on `ipn_url`/redirects (MEDIUM/HIGH).**
2. **R2 throttle initiate/confirm + verification-attempt cap (MEDIUM).**
3. **R3 widen HMAC-signed content + doc update (MEDIUM/design).**
4. **R4 secret-at-rest encryption + rotation lifecycle (MEDIUM/inherent).**
5. **R5 recommend `hash_equals` in merchant docs (LOW).**

## 6. Status

F22 is docs-only: it maps the merchant hosted-payment surface, records strengths (HMAC-signed callback,
idempotent settlement, generic errors, non-serialized secret) and ranks risks (SSRF on callback URLs, no
throttle, narrow signature scope, reversible secret at rest, doc `==` guidance) for a future owner-gated
pass. No merchant-API/payment/callback behavior changed; no secrets written/exposed; no routes, no
migration, no real DB; `rbac.enforce` stays `false`.
