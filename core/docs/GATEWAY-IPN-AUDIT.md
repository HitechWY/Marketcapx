# Gateway IPN / Callback Verification Audit (Phase 12)

Audit of how each payment gateway's inbound IPN/callback handler verifies
authenticity before crediting a deposit. No external/gateway endpoints were
called. Two source-verified bugs were fixed in this pass (see §1); the rest is
recorded as findings/recommendations to be confirmed per-controller by a human
before any change, because an automated first pass produced several false
positives (see §3).

Constant reference: `Status::PAYMENT_INITIATE = 0`, `PAYMENT_SUCCESS = 1`,
`PAYMENT_PENDING = 2` (`app/Constants/Status.php`).

## 1. Fixed in this pass (source-verified, minimal, fail-closed)

### Skrill — `ipn()` idempotency/verify clause was an assignment
`app/Http/Controllers/Gateway/Skrill/ProcessController.php:55`

The final clause of the credit condition used `=` (assignment) instead of `==`:

```php
... && $deposit->status = Status::PAYMENT_INITIATE)   // before
... && $deposit->status == Status::PAYMENT_INITIATE)  // after
```

Because `PAYMENT_INITIATE === 0`, the assignment expression evaluated to the
**falsy** `0`, so the whole `&&` chain was always false and **Skrill IPNs never
credited** (fail-closed/broken — not an exploitable false-credit). The fix
restores correct behavior: credit once, only after the md5 signature, Skrill
`status == 2`, and `pay_to_email` checks pass, guarded by the
`status == PAYMENT_INITIATE` idempotency check used by every sibling gateway.
Risk of the fix: none for legitimate flows (a first IPN has status
`PAYMENT_INITIATE`); replays are correctly skipped.

### Cashmaal — `ipn()` credential check could be bypassed via public `web_id`
`app/Http/Controllers/Gateway/Cashmaal/ProcessController.php:47`

The reject condition used `&&`, so it only rejected when **both** the secret
`ipn_key` **and** the `web_id` were wrong:

```php
if ($request->ipn_key != $IPN_key && $web_id != $request->web_id) {   // before
if ($request->ipn_key != $IPN_key || $web_id != $request->web_id) {   // after
```

`web_id` is emitted publicly in the redirect form (`process()`, line 28), so an
attacker who supplies a matching `web_id` and a forged `ipn_key` passed the
check. Switching to `||` makes the request fail-closed unless **both** match.
Follow-up: re-test against Cashmaal's sandbox to confirm their IPN posts
`ipn_key` (it is a documented field); the change is in the safe (stricter)
direction regardless.

Both files pass `php -l`. No behavior outside the IPN authenticity gate changed.

### TwoCheckout — re-reviewed (Phase 6), no change needed
A later scan flagged `TwoCheckout/ProcessController::ipn()` for a "missing
idempotency guard." Re-reading the source: it fetches the deposit with
`Deposit::where('status', 0)...` (status 0 = `PAYMENT_INITIATE`), so the query
itself is the idempotency guard — a replayed callback after the status changes
finds no row and cannot double-credit. Authenticity is an md5 hash over the
configured `secret_key` (not hardcoded), and `userDataUpdate()` re-checks under a
lock (Phase 3). Adding an explicit `&& status == PAYMENT_INITIATE` would be
redundant. **No code change** — the flag was a false positive, like the
CoinbaseCommerce/NowPaymentsHosted flags below.

## 2. The "good" verification pattern (reference)

Strong handlers in this codebase follow: **verify authenticity → check
`status == PAYMENT_INITIATE` → `PaymentController::userDataUpdate()`**, e.g.:

- **Blockchain** (Phase 2 fix) — configured-secret check + address/amount re-verify, persists detail only after.
- **BTCPay** — `Webhook::isIncomingWebhookRequestValid()` before crediting.
- **StripeV3 / StripeJs** — Stripe SDK signature verification (`Webhook::constructEvent`).
- **CoinbaseCommerce** — HMAC-SHA256 over the raw body vs `X-Cc-Webhook-Signature`, then `status==PAYMENT_INITIATE` (ProcessController.php:73-76). *Correct.*
- **NowPaymentsHosted / NowPaymentsCheckout** — HMAC-SHA512 over the sorted body, and idempotent via `Deposit::where('status', 0)->where('trx', ...)` so a replay finds no row (NowPaymentsHosted ProcessController.php:64-70). *Correct.*

## 3. Note on automated-pass false positives

A structured first pass flagged CoinbaseCommerce ("persists before verify") and
NowPaymentsHosted ("no idempotency check") as risky. Direct source reading shows
both are actually correct (see §2). Treat any broad gateway-by-gateway risk
table with caution and confirm each claim against the source before acting —
several gateways verify in ways (query-level status filters, SDK calls) that a
pattern scan misreads.

## 4. Recommendations NOT actioned here (need gateway knowledge / a test DB)

These are real design considerations but carry integration-breakage risk and
cannot be validated without a seeded test DB or gateway sandbox, so they are
documented rather than blind-changed (per Phase 13 guidance):

1. **Persist gateway `detail` only after verification.** Some redirect/return
   handlers write `$deposit->detail` before the authenticity check completes.
   Persisting unverified detail is low-impact (no credit happens without the
   status guard) but should be moved after verification for hygiene. Confirm
   per controller — do not assume from a scan.
2. **Prefer signature/HMAC or server-to-server re-query over trusting redirect
   params** for gateways that currently rely only on returned status fields
   (e.g. Aamarpay loads a signature key but does not use it). Adding a real
   check requires knowing each gateway's signing scheme; do it with their docs
   and a sandbox, not blind.
3. **Replace MD5-based signature checks** (Skrill, PerfectMoney, SslCommerz,
   TwoCheckout) where the gateway offers a stronger scheme. Constrained by what
   each gateway supports; not a unilateral change.
4. **Confirm every credit path is idempotent** via the `status == PAYMENT_INITIATE`
   (or query-level `where('status', 0)`) guard. The DB-level locking added in
   Phase 3 (`PaymentController::userDataUpdate`) is the backstop; the per-handler
   guard should still be present.

See `docs/HARDENING.md` and `docs/DEPENDENCY-LOCK-RECOVERY.md` for related notes.
