# F2e — User Money-Forms Audit (Plan, docs-only)

**Branch:** `frontend/inertia-user-money-forms-audit` (from master after F2d)

## Scope
**Docs-only.** Audit every user money-movement flow and produce
`core/docs/USER-MONEY-FORMS-MIGRATION-AUDIT.md` with a future migration plan. **No code change.**

## Flows to document
deposit (index/insert/confirm/manual), withdraw (money/preview/submit/methods/add/edit/update),
money-out, make-payment, transfer, request-money (confirm/accept/reject), invoice (create/update/
discard/publish/pay), voucher (create/redeem), exchange (money), escrow (steps/accept/dispute/
dispatch/cancel/milestone pay), plus the deposit `kyc.submit` boundary.

## Per flow: route name+URL, method, controller method, Blade view, form action, input names, hidden
fields, CSRF/captcha status, validation rules, side effects, wallet/balance mutation point,
idempotency/atomicity notes, required contract tests before migration, risk rating, recommended phase.

## Gates / auto-merge
Docs-only; PHP/JS untouched. Auto-merge if `git diff` is docs-only and all standard gates green
(build/tsc/phpunit unchanged, route 798). Stop if any inspection would require editing code.
