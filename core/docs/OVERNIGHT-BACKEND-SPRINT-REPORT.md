# Overnight Backend Hardening & Laravel-Readiness Sprint — Final Report

Post-frontend backend sprint. Test-first, small reversible branches, standard
green gate, conditional auto-merge. No deploy, no production, no `paytabz_DB`
mutation. Tests run against `marketcapx_test`.

## 1. Baseline → final

| | Commit | Tests | Routes |
|---|--------|-------|--------|
| **Start** | `ed27f82` | 42 passed / 1 skipped | 796 |
| **End (master)** | `adad45d` (+ this report) | **53 passed / 1 skipped** | 796 |

Stack confirmed: **Laravel `v13.16.1` / PHP 8.3.31 / PHPUnit 12.5.30** — already on
the latest Laravel major. Remote `HitechWY/Marketcapx`. `paytabz_DB` untouched (54
tables); `core/.env.local` never committed.

## 2. Branches

| Phase | Branch | Disposition | Merge commit |
|-------|--------|-------------|--------------|
| B0 | (none — committed to master) | merged | `8352742` |
| B1 | `work/escrow-atomicity-final` | **merged** | `ccfd259` |
| B2 | `work/boot-test-isolation` | **merged** | `dab87c3` |
| B3 | `work/exchange-wallet-scope` | **merged** | `3b8fd41` |
| B4 | `work/api-idempotency-safety` | **merged** | `fb25fa7` |
| B5 | `work/kyc-pii-readiness` | **merged** | `1e72cee` |
| B6 | `work/gateway-residuals-audit` | **merged** | `6d7855c` |
| B7 | `work/model-audit-safe-improvements` | **merged** | `3d4de7f` |
| B8 | `work/laravel-upgrade-readiness` | **merged** | `adad45d` |
| B9 | `work/laravel-upgrade-dry-run` | **pushed, NOT merged** (by design) | `568218413` (HEAD) |
| B10 | `work/backend-overnight-final-report` | this report | — |

All eight functional branches were `--no-ff` merged and pushed; each merge
re-checked `origin/master` first (it never moved unexpectedly).

`work/escrow-atomicity` (the original, b9fb2f9) was **left untouched** — it predates
the frontend rebuild and a direct merge would have reverted all Material CSS, so its
3 escrow commits were cherry-picked onto a fresh branch instead.

## 3. Per-phase outcomes

- **B0 — Baseline & risk map.** `BACKEND-OVERNIGHT-SPRINT-BASELINE.md`; confirmed
  live 42/1, 796 routes; recorded the 3 corrections (Laravel already latest; escrow
  branch stale → cherry-pick; boot DB-query location).
- **B1 — Escrow atomicity (money).** Last un-hardened money flow. Both
  `User::dispatchEscrow` and `Admin/EscrowController::action` now wrap the status
  change + payouts + ledgers in `DB::transaction`, lock the escrow row with a status
  re-check (idempotent on replay), lock wallets id-ascending, notify after commit.
  F8 records-only `dispute_charge` precedence fix. +2 tests.
- **B2 — Boot/test isolation.** `AppServiceProvider::boot()` skips boot-time DB reads
  for pure artisan commands (`runningInConsole() && !runningUnitTests()`).
  `php artisan route:list`/`migrate`/etc. now boot with **no DB**; web + PHPUnit
  unchanged (verified by in-process web smoke + full suite).
- **B3 — Exchange wallet scope (real vuln).** Money-exchange (web + API) scoped only
  the source wallet; the destination used an unscoped `Wallet::find()`, letting a
  user credit any/other-user wallet and write a foreign ledger row. Destination now
  scoped via `checkWallet`. +3 tests (bug reproduced first).
- **B4 — Hosted-checkout replay safety.** `GetPaymentController` dereferenced
  `getPaymentInfo()`'s error array as an object on a settled/expired replay (fatal in
  `checkEmail`/`sendVerifyCode`, misleading in `verifyPaymentConfirm`). Added
  `instanceof ApiPayment` guards. `API-IDEMPOTENCY-AUDIT.md` + 2 tests.
- **B5 — KYC/PII filenames.** `FileManager::getFileName()` used time-derived
  `uniqid().time()`; web-served KYC docs were enumerable. Now appends 128 bits of
  CSPRNG entropy for new uploads (backward-compatible). `KYC-PII-READINESS.md` + 2 tests.
- **B6 — Gateway residuals (docs-only).** `GATEWAY-RESIDUALS-AUDIT.md`: CoinPayments
  /CoinPaymentsFiat **inbound IPN has no HMAC verification** (merchant_id only) —
  documented as creds-required (not fixed blind); raw `$e->getMessage()` exposure
  inventory across 10 gateways; prior fixes re-confirmed present.
- **B7 — Model/Eloquent (safe).** Added `$hidden` to `Agent` (password,
  remember_token) and `Merchant` (+ `secret_api_key`). `MODEL-AUDIT.md` inventories
  all 52 models with deferred recommendations. +2 tests.
- **B8 — Laravel readiness (docs-only).** `LARAVEL-UPGRADE-READINESS.md`: on latest
  major; only safe minor/patch bumps; transitive majors parent-constrained.
- **B9 — Dependency dry-run (NOT merged).** Scoped real update of 4 leaf packages
  (guzzle/psr7/carbon/uuid), Laravel unchanged, suite 53/1 green. Vendor restored to
  master afterward. `DEPENDENCY-DRY-RUN.md`. Awaits human review.

## 4. Tests added (42/1 → 53/1, +11)

`EscrowDispatchFlowTest`, `EscrowDisputeResolveFlowTest`, `MoneyExchangeFlowTest`
(+1 method), `ApiMoneyExchangeFlowTest` (×2), `HostedPaymentReplayTest` (×2),
`FileNameEntropyTest` (×2), `ModelHiddenAttributesTest` (×2). Fixtures extended:
`makeEscrow`/`makeConversation`/`makeApiPayment` in `CreatesMoneyFixtures`.

## 5. Money-flow safety improvements

- Escrow dispatch + dispute-resolve are now atomic + idempotent (B1) — completes the
  money-flow hardening program (all user/admin balance flows now covered).
- Exchange can no longer credit a foreign wallet (B3).
- Hosted-checkout replays fail cleanly instead of fatally/misleadingly (B4).

## 6. Remaining risks (documented, not actioned)

- **CoinPayments inbound IPN HMAC** (B6) — HIGH; needs the IPN secret + a sandbox
  callback to implement safely.
- **Raw gateway exception messages** to users (B6) — generic-message + server-log;
  needs gateway creds to test the flows.
- **Flat admin RBAC** — migration draft only, not enforced (needs a schema migration
  on production data; out of sprint scope).
- **`laramin/utility`** external license client — third-party, watch for future
  Laravel-major compatibility.
- **Mass assignment** (`$guarded = []`) on several models (B7) — latent; explicit
  `$fillable` is a behaviour-review change.
- **B5/B7 follow-ups** — move KYC out of web root behind an auth controller; hide
  `ver_code`/`kyc_data`/reset-tokens after API-response verification.

## 7. Laravel upgrade readiness & model audit status

- **Upgrade**: already current (L13.16.1). No major to perform. Safe minor/patch
  refresh dry-run verified (B9, unmerged). Re-check when L14 ships.
- **Model audit**: complete (`MODEL-AUDIT.md`); safe `$hidden` applied, the rest
  documented as verified-follow-ups.

## 8. Hard stops

**None.** No hard-stop condition was triggered. `origin/master` never moved
unexpectedly; no change required a `paytabz_DB` mutation, secret/PII exposure, a
deploy, or an unverifiable gateway/HMAC change (the one such item — CoinPayments
inbound HMAC — was documented, not implemented, per the rules).

## 9. Verification (final, on master)

- PHPUnit `APP_ENV=testing … phpunit`: **53 passed / 1 skipped** (the 1 skip is the
  Blockchain IPN test that needs a live gateway mock).
- `artisan route:list`: **796 routes**.
- `composer validate`: valid. `composer audit`: no advisories.
- `git diff --check`: clean every phase; no forbidden files/secrets staged.

## 10. Safety confirmations

- ✅ No deploy. ✅ No force-push. ✅ No history rewrite.
- ✅ No non-test DB mutation; `paytabz_DB` read-only (54 tables) — only B4's in-process
  smoke issued read-only SELECTs against it; all test writes were `marketcapx_test`
  under `DatabaseTransactions` (rolled back).
- ✅ No secrets exposed; `core/.env`/`core/.env.local` never staged (`.env.local`
  remained untracked throughout).
- ✅ `work/escrow-atomicity` handled (cherry-picked, original left intact).
- ✅ B9 composer changes confined to its own unmerged branch; master vendor restored.

## 11. Recommended next steps

1. Review + merge `work/laravel-upgrade-dry-run` (B9) if the minor/patch refresh is
   wanted, then `composer install` on deploy targets.
2. Schedule a **credentialed gateway pass**: CoinPayments inbound IPN HMAC + generic
   gateway error messages (both need sandbox creds).
3. Plan the **KYC-out-of-web-root** change (authenticated download controller) — the
   real fix beyond B5's filename entropy.
4. Decide on **admin RBAC** (the draft migration) as a separate, scheduled change.
5. Optional B7 follow-ups (hide `ver_code`/`kyc_data`/reset-tokens) after verifying
   the agent/merchant API payloads.
