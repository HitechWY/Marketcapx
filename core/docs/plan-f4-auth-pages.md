# F4 — Auth Pages Migration (Plan)

Auth GET pages → Inertia with **native HTML POST** to UNCHANGED auth controllers. Captcha (`<x-captcha/>`)
rendered server-side and passed as HTML prop (v-html) like F1c contact. No guard/reset-token/captcha/
registration business logic changed. Blade fallback kept (one-line rollback).

## F4a `frontend/inertia-auth-contract-plan` (docs-only)
`AUTH-PAGES-MIGRATION-CONTRACTS.md` — per form: route/method/controller/view/inputs/hidden/captcha/CSRF/
validation/success+failure redirect/guard/reset-token/stop-conditions, for user+merchant+agent+admin auth.

## F4b `frontend/inertia-user-auth-pages`
user.login/register/password.request/password.code.verify/password.reset GET → Inertia.
Fields: login {username, password, captcha}; register {account type, firstname, lastname, email,
password, password_confirmation, agree?, captcha}; forgot {email, captcha}; reset {token, email/password}.
Native POST to user.login/register/password.email/verify.code/password.update. Auth pages use a minimal
**GuestLayout** (no user shell). Validation errors via Inertia shared errors.

## F4c `frontend/inertia-merchant-agent-auth-pages`
merchant.* + agent.* login/register/password GET → Inertia (same pattern, guest layout).

## F4d `frontend/inertia-admin-login-page`
Admin login GET → Inertia ONLY if contract is simple (username/password/captcha) and tests pass;
else docs-only audit. Admin uses a separate admin layout/asset set — assess at implementation.

## Tests: GET → Inertia component; native-form contract (validation-failure → errors, no persistent
DB row; captcha slot present-or-empty); guests reach pages (no auth required). No auth logic changed.

## Stop conditions: captcha cannot be preserved; reset-token/guard logic would change; registration
business rules would change; success path needs non-test DB mutation.
