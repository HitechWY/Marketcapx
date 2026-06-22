# F30 — Stabilization Checklist (docs-only)

A single read-only snapshot of where MarketCap X stands for stabilization/launch planning: the verified
test baseline, what the admin-audit track has shipped, and the known debt / launch blockers that remain
**owner-gated**. This file asserts only facts verifiable in-repo at the recorded commit; everything else is
linked to its source doc rather than restated. Companions: `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`,
`F27-PRODUCTION-READINESS-RUNBOOK.md`, `F19-MONEY-FLOW-ADMIN-ATTRIBUTION-MAP.md`, `HARDENING.md`,
`GATEWAY-IPN-AUDIT.md`, `API-IDEMPOTENCY-AUDIT.md`, `KYC-PII-READINESS.md`,
`ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`.

## Verified baseline (at this commit)

| Metric | Value | How verified |
|---|---|---|
| Full test suite | **208 tests / 1949 assertions / 1 skipped — green** | `phpunit` on `marketcapx_test` (`DatabaseTransactions`) |
| Route count | **798** | `php artisan route:list --json` count |
| RBAC mode | `rbac.enforce` = **false** (allow-by-default) | `config('rbac.enforce')` |
| Branch / remote | `master` = `origin/master` | `git rev-parse` |

> The test DB is the dedicated `marketcapx_test` MySQL schema; every test rolls back. No production DB is
> touched by the suite.

## Shipped — admin-audit semantic events (controller-level)

Per `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md` (authoritative): `support.ticket.closed` (F14),
`support.ticket.replied` (F14b), `account.status.changed` (F15), `account.kyc.approved` /
`account.kyc.rejected` (F16), `account.profile.updated` (F17). All are status/structure-only via
`AdminAuditLogger::logMutation()` — never PII, reason/reply/body text, KYC docs/paths/`kyc_data`, balances,
tokens, or secrets. Each has a dedicated feature test. The logger is **fail-open** (a failed audit write
never breaks the admin action; durable persistence is best-effort). The F10 middleware layer
(`admin.request.mutation` / `rbac.permission.*`) remains the always-on baseline.

## Known debt / still owner-gated (NOT done — do not start without approval)

- **Money-flow admin attribution & audit** — `account.balance.adjusted`, `deposit.*`, `withdrawal.*`,
  `escrow.resolved` remain design-only and **owner-gated**; ledger rows lack `admin_id` today. See
  `F19-MONEY-FLOW-ADMIN-ATTRIBUTION-MAP.md`. No payment/wallet/gateway behavior is changed in the
  docs/audit track.
- **Gateway / IPN / webhook hardening** — tracked in `GATEWAY-IPN-AUDIT.md`, `GATEWAY-RESIDUALS-AUDIT.md`,
  `API-IDEMPOTENCY-AUDIT.md`. Signing-secret and idempotency items are release-gated, not yet enforced.
- **RBAC enforcement** — `rbac.enforce` is deliberately **false**. Flipping to deny-by-default requires a
  reviewed `php artisan rbac:would-deny-report` pass and explicit owner sign-off (`F12-WOULD-DENY-REVIEW.md`).
- **Gateway/settings/notification-template audit** — needs per-endpoint field allow-lists before
  before/after can be logged (secrets/PII risk); design-only.
- **Sensitive admin UI freeze** — high-risk admin actions remain Blade/frozen per
  `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md` while the Inertia/Vue migration proceeds on feature branches.
- **KYC-file deletion is irreversible** — the F17 KYC-reset branch `@unlink`s files before the DB write;
  not recoverable by code rollback or DB restore (`F27-PRODUCTION-READINESS-RUNBOOK.md` §7). Independent
  file-storage backup is the only recovery path.

## Launch blockers (ALL must be GREEN — see F27 §9 for the deploy gate)

- [ ] Verified, test-restored **database backup** exists; restore target recorded.
- [ ] **No unapproved migration**; any migration owner-signed and reversible.
- [ ] **Secrets present and never exposed** in git/bundles/public routes/logs (verify presence only).
- [ ] **Independent KYC-file backup** exists if account-update KYC-reset may be exercised near release.
- [ ] Full suite green, route count **798**, `rbac.enforce=false`, smoke tests pass (F27 §8).
- [ ] Money/gateway behavior **unchanged** vs the released baseline (no in-flight money-flow edits).

## Status

F30 is docs-only: it records the verified baseline and consolidates the owner-gated debt list. No code,
routes, migrations, or config changed; no real-DB mutation.
