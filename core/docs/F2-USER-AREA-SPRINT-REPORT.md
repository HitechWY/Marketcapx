# F2 — Authenticated User Area Migration Sprint — Final Report

## Summary
Migrated the authenticated **user (web guard)** area to Inertia/Vue in staged, auto-merged phases,
read-only/low-risk first, money-movement frozen. Backend logic, routes, schema, and money-form
contracts unchanged. All Blade views retained as instant fallbacks.

## Baseline → final
- Start: `master 8637e89` (F2a), 798 routes, PHPUnit 59/258/1.
- End: `master <F2e merge>`, **798 routes (unchanged)**, **PHPUnit 76/389/1** (+17 tests).

## Phases (all merged to master)
| Phase | Branch | Merge | Scope |
|---|---|---|---|
| F2a (prior) | frontend/inertia-user-dashboard | 8637e89 | dashboard + auth shared props |
| F2b | frontend/inertia-user-readonly-lists | dd59bc8 | transactions, wallets, deposit history, withdraw history |
| F2c | frontend/inertia-user-profile-settings | cc7d5aa | profile, change-password, 2FA, QR (native forms) |
| F2d | frontend/inertia-user-kyc-readonly | a09aee7 | KYC data (read-only); upload form stays Blade |
| F2e | frontend/inertia-user-money-forms-audit | (this) | money-forms audit (docs only) |

## User routes now Inertia
`user.home`, `user.transactions`, `user.wallets`, `user.deposit.history`, `user.withdraw.history`,
`user.profile.setting` (GET), `user.change.password` (GET), `user.twofactor` (GET), `user.qr.code`,
`user.kyc.data`. POST handlers (submitProfile/submitPassword/create2fa/disable2fa) unchanged — driven
by native HTML forms.

## Intentionally still Blade (frozen money-movement + uploads)
deposit, withdraw (money/submit/methods), transfer, money-out, make-payment, exchange, request-money,
invoice, voucher, escrow/milestone, **KYC upload** (`kyc.form`/`kyc.submit`). See
`USER-MONEY-FORMS-MIGRATION-AUDIT.md` for the future plan. Also unchanged: auth pages, admin,
merchant/agent dashboards, checkout/IPN.

## Safety
- No payment/wallet/KYC/gateway/IPN/admin/merchant/agent/checkout business logic changed.
- No DB schema change; all test/visual-QA fixtures created inside rolled-back transactions (no
  persistent mutation; paytabz_DB untouched).
- Data exposure: `auth.user` shares only `{id,username,fullname,email,image,kv,ts}` (never password/
  remember_token/ver_code/balance/kyc_data). KYC file values use the existing encrypted download route.
  Money route names stay out of client Ziggy (server-resolved nav/action URLs).
- No secrets exposed; `core/.env`/`.env.local` untouched.

## Gates (each phase)
build ✅ · vue-tsc ✅ · composer validate ✅ · PHPUnit (no regressions) · route:list 798 ·
guest→302 smoke · authed Inertia mount · public/Blade unchanged · Ziggy 0 high-risk · visual QA
(desktop+mobile, no overflow, no console errors).

## Remaining risks / next
- Money-movement forms still Blade (by design) — see audit; require atomicity/idempotency coverage
  before FE migration.
- 2FA/password success paths not exercised against external APIs in tests (validation-failure +
  contract covered); acceptable.
- **Recommended next:** merchant/agent dashboards (mirror the F2 user pattern with their guards), OR
  begin the money-forms phase (F3) starting with voucher/invoice behind contract+atomicity tests.
