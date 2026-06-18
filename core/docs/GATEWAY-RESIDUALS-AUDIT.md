# Gateway Residuals Audit (B6)

Read-only audit of the payment-gateway controllers for residual issues after the
earlier IPN-hardening waves. **Docs-only** by design: every remaining item either
needs a live/sandbox credential to verify or is an HMAC/signature change that must
not be made blind (per the sprint hard-stop rules). Companion to
`GATEWAY-IPN-AUDIT.md`.

## Confirmed still-present (prior fixes, regression check)

- **Blockchain IPN** (`Gateway/Blockchain/ProcessController`): the old hardcoded
  `"MySecret"` is gone — it reads the configured `gateway_parameter.secret`,
  compares with `hash_equals`, and fails closed when unset. ✔
- **Skrill** `ipn()` `=`→`==`, **Cashmaal** `&&`→`||` secret check, and the
  `$deposit->detail`-after-verify moves (Aamarpay/Instamojo/Paypal/Paystack/
  Razorpay/Flutterwave) from the deferred wave are all present on master. ✔

## Residual findings (NOT fixed here — see remediation list)

### 1. CoinPayments / CoinPaymentsFiat inbound IPN has no HMAC verification — HIGH
`Gateway/Coinpayments/ProcessController::ipn()` and
`Gateway/CoinpaymentsFiat/ProcessController::ipn()` authenticate an incoming IPN
only by matching `gateway_parameter.merchant_id == $request->merchant` (plus
currency/amount/status guards). CoinPayments signs every IPN with an **HMAC
header** computed from the account's IPN secret — but this codebase computes HMAC
only for **outbound** API calls (`CoinPaymentHosted.php:154`, `HMAC:` request
header) and never verifies the **inbound** header. The merchant id is not secret,
so a crafted POST that matches an in-flight deposit's trx/currency/amount could
drive `userDataUpdate()` and credit a deposit without a real payment.

Why not fixed here: verifying the IPN HMAC requires the per-account IPN secret and
a real/sandbox CoinPayments callback to confirm the exact signed-payload format
(raw POST body vs ordered fields). Implementing it blind risks rejecting all
legitimate IPNs (fail-closed on a money-in path). → creds-required remediation.

### 2. Raw exception messages returned to the user — MEDIUM
These return `$e->getMessage()` (or equivalent) straight into the user-facing
payment response/notification rather than logging it server-side and showing a
generic message:

| Gateway | Location |
|---------|----------|
| Coinpayments | `ProcessController.php:28` (`$send['message']`) |
| Coingate | `ProcessController.php:42` |
| Mollie | `ProcessController.php:36` |
| Paytm | `ProcessController.php:33` |
| Razorpay | `ProcessController.php:39` |
| StripeV3 | `ProcessController.php:42` |
| BTCPay | `ProcessController.php:38` (+ `:56` into an AdminNotification title) |
| Stripe | `ProcessController.php:107,110` (`$notify[]`) |
| StripeJs | `ProcessController.php:66,78` |
| Bkash | `ProcessController.php:239` (re-throws `new \Exception($e->getMessage())`) |

Risk: gateway SDK exceptions can embed request context, endpoint URLs, or
provider-side detail that should not reach an end user on the checkout page.

Why not fixed here: these are all error paths inside live gateway init/IPN flows
that cannot be exercised locally without provider credentials, and the sprint rule
is "do not change live gateway behaviour without tests." A blind rewrite across 10
files in money flows is exactly the kind of change to defer to a credentialed pass.

## Remediation list (requires sandbox/live credentials or a credentialed test env)

1. **CoinPayments/CoinPaymentsFiat inbound IPN HMAC** — read the `HMAC` request
   header, `hash_hmac('sha512', <raw POST body>, $ipn_secret)`, compare with
   `hash_equals`, fail closed. Verify against a real sandbox IPN before enabling.
2. **Generic gateway error messages** — for each row above: `Log::error($e)` (or an
   admin notification) server-side, return a fixed "Payment could not be processed,
   please try again" to the user. Verify each gateway's happy path still works with
   sandbox creds, since these sit in the init/return flow.
3. Re-confirm per-gateway IPN signature coverage end-to-end with sandbox callbacks
   (the matrix in `GATEWAY-IPN-AUDIT.md` is source-derived, not callback-verified).

## Not changed / not done
- No gateway secret, IPN secret, or signature logic was added, faked, or altered.
- No HMAC/signature code was written (would be unverifiable without creds).
- No exception-handling behaviour was changed (all live-flow, untestable locally).
- No sample IPN payloads or gateway credentials were created or exposed.
