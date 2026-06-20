# F3–F5 Non-Money Migration Sprint — Report

**Outcome:** all planned phases completed and merged to `master` (pushed to
`origin` = HitechWY/Marketcapx). Backend frozen; only read-only display surfaces and
captcha-free auth recovery pages migrated. No money/KYC/gateway/IPN/checkout/secret logic changed.

## Baseline → final
- Start: `master == origin/master == 1b3f882` (F3a + F3c already merged from a prior turn).
- Final: `master == origin/master == 83f9458`, working tree clean.
- Route count: **798** throughout (no routes added/removed — controllers re-pointed to Inertia in place).
- Tests: 90 → **100** (+10 new), **623 assertions, 1 skipped**, suite green.
- Build ✓ (vite), typecheck ✓ (vue-tsc), `composer validate` ✓.

## Phases (each its own branch, `--no-ff` merge, branch pushed)
| Phase | Branch | Merge | Type |
|------|--------|-------|------|
| F3b merchant lists | `frontend/inertia-merchant-readonly-lists` | `7550998` | code |
| F3d agent lists | `frontend/inertia-agent-readonly-lists` | `58e47fb` | code |
| F3e sensitive audit | `frontend/inertia-merchant-agent-sensitive-audit` | `121cbdd` | docs |
| F4a auth contracts | `frontend/inertia-auth-contract-plan` | `00fe2b3` | docs |
| F4b user auth recovery | `frontend/inertia-user-auth-pages` | `e65767b` | code |
| F4c merchant/agent auth recovery | `frontend/inertia-merchant-agent-auth-pages` | `7a10cc1` | code |
| F4d admin login | `frontend/inertia-admin-login-page` | `2f76af4` | docs (kept Blade) |
| F5 admin audit | `frontend/inertia-admin-readonly-audit` | `83f9458` | docs |

(F3a `c96b29e` and F3c `1b3f882` were merged before this turn.)

## Migrated to Inertia (read-only / native-POST; Blade fallbacks retained as one-line rollback)
- Merchant: `transactions`, `wallets`.
- Agent: `transactions`, `wallets`, `commission log`, `add-money history`.
- User/Merchant/Agent password recovery: `forgot-password`, `code-verify`, `reset-password`
  (native HTML POST to unchanged controllers; shared guard-agnostic `Auth/*` pages + `AuthLayout`).
- DRY refactors: User `Transactions`/`Wallets`/`DepositHistory` now reuse shared `Panel/*` components.

## Intentionally still Blade
- All login & register pages (user/merchant/agent/admin) — render `<x-captcha>`; migration would
  change captcha logic (hard-stop).
- All merchant/agent sensitive forms (API keys/secrets, withdraw, money-in, commission withdrawal,
  KYC, profile/password/2FA) — see F3e audits.
- Entire admin UI — see F5 plan + freeze list.

## Safety confirmations
- No payment/wallet/KYC/gateway/IPN/checkout/escrow logic touched.
- No merchant/API/admin secrets exposed (api_key page stays Blade; no secret in any prop).
- No Ziggy/client `route()` exposure — all URLs server-resolved into props.
- No DB schema/migrations; tests use `DatabaseTransactions` (rollback, no persistent rows).
- `core/.env` never read/edited/staged; no secret files committed.
- Old Blade fallback views all retained.

## Tests added
`MerchantReadonlyListsTest` (4), `AgentReadonlyListsTest` (6), `UserAuthPagesTest` (4),
`MerchantAgentAuthPagesTest` (6) — note net suite +10 after dedup of overlapping coverage.

## Recommended next phase
Admin read-only migration (F5 plan A1–A3): add an `admin`-guard secret-free shared-props layer +
`AdminLayout`, then migrate dashboard, read-only lists, and reports reusing the existing MD3 kit.
Login/register remain blocked on a verified in-SPA captcha strategy.
