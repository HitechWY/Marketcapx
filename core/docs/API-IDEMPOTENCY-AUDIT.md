# API Idempotency & Replay-Safety Audit (B4)

Scope: API/hosted payment, deposit and money flows that are reachable and
testable locally (no live gateway). Companion to `MONEY-FLOW-AUDIT.md` and
`GATEWAY-IPN-AUDIT.md`.

## Already safe (verified, no change needed)

These were hardened in earlier waves and re-confirmed during this audit:

- **API make-payment** (`Api/User/MakePaymentController::paymentDone`): one-shot
  `UserAction` (API `used=1`) consumed inside the transaction — a replay is a
  no-op. Covered by `ApiMakePaymentFlowTest`.
- **Deposit credit** (`Gateway/.../userDataUpdate`): wrapped in
  `DB::transaction` + `lockForUpdate`, idempotent on the deposit row status.
  Covered by `DepositIdempotencyTest`.
- **Hosted payment settlement** (`GetPaymentController::verifyPaymentConfirm`):
  locks the `api_payments` row with a `status == 0` re-check inside the tx, flips
  status BEFORE crediting, and returns an idempotent success on a concurrent
  confirm. Outbound IPN curl + notify run after commit. (See `MONEY-FLOW-AUDIT`.)
- **Transfer / money-in / money-out / voucher / invoice / milestone / withdrawal
  submit** (web + API): all use the canonical one-shot UserAction + atomic
  pattern; double-submit tests exist under `tests/Feature/MoneyFlow/`.

## Fixed in B4 — hosted-checkout replay guards

`ApiPaymentProcess::getPaymentInfo()` returns an **error array**
(`['error' => 'true', 'message' => 'Invalid transaction request']`) when the
session payment is missing or already settled/cancelled (`status` 1 or 2), and
the model otherwise. Several callers dereferenced the result as an object:

| Endpoint | Pre-B4 behaviour on a settled/expired replay | After B4 |
|----------|----------------------------------------------|----------|
| `checkEmail` (POST/JSON) | **Fatal** `Error: Attempt to assign property "ver_code" on array` | returns the JSON error shape |
| `verifyPaymentConfirm` (POST/JSON) | read property on array (null) → misleading "verification code mismatch" | returns explicit "Invalid transaction request" |
| `sendVerifyCode` (GET/view) | **Fatal** assign-on-array at `save()` | `abort(404)` (payment no longer valid) |

Fix: an `instanceof ApiPayment` guard at each call site, returning the existing
error shape (JSON endpoints) or a 404 (the view endpoint). The success path,
amounts, charges, response shapes and settlement atomicity are unchanged.

Tests (`HostedPaymentReplayTest`, `marketcapx_test`): a replay/settled hosted
payment returns a clean error from `checkEmail` and `verifyPaymentConfirm` with
no verification code issued — no fatal, no misleading message.

## Documented residual (not changed)

- `initiatePaymentAuthView` and `verifyPayment` (pure GET view methods) pass the
  `getPaymentInfo()` result straight to a Blade view. On a replayed/expired
  session they render with an error array rather than a model — a **display**
  concern (not a money or fatal-replay bug). Left as-is to keep this branch
  narrow; a future slice can redirect/abort these consistently with `sendVerifyCode`.
- The wider IPN/HMAC signature paths are covered in `GATEWAY-IPN-AUDIT.md`;
  anything needing live/sandbox gateway credentials is out of local scope.
