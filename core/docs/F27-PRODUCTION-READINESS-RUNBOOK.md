# F27 — Production-Readiness & Rollback Runbook (docs-only)

**Status:** docs-only operational runbook. **Nothing in this file deploys, migrates, mutates a database,
reads or prints secrets, or changes application behavior.** It is the human checklist an owner/operator
works through *before*, *during*, and *after* a production release of MarketCap X. Every payment/wallet/
gateway/IPN/webhook/API behavior stays frozen; `rbac.enforce` stays `false` until its own gate
(`RBAC-DENY-BY-DEFAULT-READINESS.md`) is satisfied.

Companions: `F11-REAL-DB-SCHEMA-APPLY-RUNBOOK.md` (schema apply), `RBAC-DENY-BY-DEFAULT-READINESS.md`,
`MONEY-FLOW-AUDIT.md`, `API-IDEMPOTENCY-AUDIT.md`, `GATEWAY-IPN-AUDIT.md`, `GATEWAY-RESIDUALS-AUDIT.md`,
`ADMIN-AUDIT-LOG-SCHEMA.md`, `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`, `KYC-PII-READINESS.md`,
`ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`, `HARDENING.md`, `DEPENDENCY-LOCK-RECOVERY.md`, `SETUP.md`.

> **How to use:** copy the checklists into the release ticket and tick each box with evidence (command
> output, dashboard link, approver name). A release proceeds only when **every launch blocker is GREEN**.
> If any step fails, follow **§7 Rollback** and stop.

---

## 1. Pre-deploy checks (T-minus)

- [ ] **Branch/tag is the intended release commit.** Record the exact commit hash. Working tree clean.
- [ ] **CI green on that commit:** full test suite passes (current baseline **200 tests / 1916 assertions
      / 1 skipped**) and route count is the pinned **798** (`ZiggyExposureTest::route count is pinned`).
- [ ] **No uncommitted migrations / no accidental scope.** `git diff --stat origin/master...release` is
      reviewed; any DB migration is listed explicitly and routed through **§3 Migration approval**.
- [ ] **Dependencies locked & reproducible.** `composer.lock` / `package-lock.json` committed; release
      built from lock (see `DEPENDENCY-LOCK-RECOVERY.md`). No ad-hoc `composer update` on the server.
- [ ] **Build artifacts produced from the release commit** (front-end assets compiled, not stale).
- [ ] **Config cache strategy decided** (`config:cache` / `route:cache` only after env is final).
- [ ] **Maintenance window scheduled** and stakeholders notified.
- [ ] **Change approver recorded** (name + timestamp) for any owner-gated item below.

## 2. Backup requirements (MANDATORY before any write to prod)

- [ ] **Full database backup taken and verified restorable** (test-restore to a scratch instance, not
      just "the dump exists"). Record backup ID + location + size + checksum.
- [ ] **Backup is consistent** (taken with the app in maintenance mode or via a transactional/locked
      dump) so in-flight money rows are not half-captured.
- [ ] **File/upload storage backed up** (KYC documents live under filename-obscurity per
      `KYC-PII-READINESS.md` — back up the storage path, never copy KYC files into logs/tickets).
- [ ] **Backup retention + access** confirmed (encrypted at rest; access limited to operators).
- [ ] **Rollback DB snapshot point recorded** (the exact restore target for §7).

## 3. Migration approval rules (owner-gated)

- [ ] **No migration runs without explicit owner sign-off.** Default release path is **no schema change**.
- [ ] If a migration is required, it is reviewed against `F11-REAL-DB-SCHEMA-APPLY-RUNBOOK.md`:
  - [ ] Migration is **additive / backward-compatible** (new nullable columns / new tables) — no
        destructive `drop`/`rename`/type-narrowing on a live money/ledger table without a staged plan.
  - [ ] Migration has been **dry-run on a copy of production data** and timed (lock duration estimated).
  - [ ] Money/ledger tables (`transactions`, `wallets`, `deposits`, `withdrawals`, `escrows`) are
        **not** altered in a way that changes existing semantics.
  - [ ] A **down path / restore plan** exists.
  - [ ] Approver name + timestamp recorded on the release ticket.
- [ ] **RBAC schema** (`rbac.php`, permission tables) apply follows its own runbook; seeding is
      idempotent (`RBAC-SEEDING.md`) and **never** auto-creates admins.

## 4. Secrets / environment checklist (verify presence, NEVER print values)

> Confirm each key is **set to a production value** (non-empty, not a dev placeholder). **Do not echo,
> log, screenshot, or paste any secret value** into the ticket — record only "set ✓ / missing ✗".

- [ ] `APP_KEY` set; `APP_ENV=production`; `APP_DEBUG=false`; `APP_URL` correct (https).
- [ ] Database credentials set and pointing at the **production** DB (not staging/local).
- [ ] Mail transport credentials set; `MAIL_FROM_*` correct.
- [ ] Queue connection + credentials set (see §5).
- [ ] Cache/session/store credentials set.
- [ ] **Payment gateway credentials** set per enabled gateway (live keys, not sandbox) — **owner-gated**,
      and gateway behavior frozen for this release.
- [ ] **Webhook/IPN signing secrets** set to strong per-environment values (see `GATEWAY-IPN-AUDIT.md`;
      no shared/default/hardcoded secret reaches production — verify the configured value is a real
      secret without printing it).
- [ ] `RBAC_ENFORCE` is **absent or `false`** for this release (allow-by-default; flipping is a separate
      owner-gated change — `RBAC-DENY-BY-DEFAULT-READINESS.md`).
- [ ] No secret is committed to git, baked into front-end bundles, or exposed via a public route.

## 5. Mail / queue / scheduler checks

- [ ] **Mail:** send a test mail through the production transport to an internal address; confirm
      delivery + correct From/Reply-To. Notification templates render (no broken shortcodes).
- [ ] **Queue:** worker is running under a supervisor (auto-restart) and processes the default queue;
      failed-job table/handler is monitored. Confirm a test job runs end-to-end.
- [ ] **Scheduler:** the system cron invokes the framework scheduler on schedule. **Verify the scheduler
      is driven by an authenticated system cron — not by any public HTTP `/cron` route** (public cron/
      clear endpoints are a known risk; they must not be reachable in production). See `HARDENING.md`.
- [ ] **Logs:** application log channel writes to a persistent, rotated destination; the admin-audit
      file fallback channel (`storage/logs/admin-audit.log`) is writable (used when DB audit insert
      fails — `ADMIN-AUDIT-LOG-SCHEMA.md`).

## 6. Audit / RBAC verification (post-deploy, read-only)

- [ ] **Admin audit log writes.** Perform one benign admin status action on staging-equivalent and
      confirm both the generic `admin.request.mutation` row and the semantic row land
      (`ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`: `account.status.changed`, `account.kyc.approved/rejected`,
      `account.profile.updated`, `support.ticket.closed/replied`). Confirm **no** reason text / KYC docs /
      PII in the row.
- [ ] **F17 profile-update audit (gate).** A benign account-profile edit writes `account.profile.updated`
      with before/after limited to `ev/sv/ts/kv` and metadata limited to `guard`, `changed_fields`
      (column **names** only), `kv_reset`, `files_removed` — **no** PII values, KYC filenames/paths, or
      `kyc_data` (`F17-ACCOUNT-PROFILE-UPDATE-AUDIT.md`). The audit logger is **fail-open**, so durable
      audit persistence is best-effort, not guaranteed — do not gate the release on every row persisting.
- [ ] **F18 admin-notify audit (gate).** An admin "send notification" (single/all, under the
      `notify.dry_run=true` test seam) writes `account.notification.sent` with metadata limited to `guard`,
      `mode`, `channels_requested`, `recipient_count`, `recipient_scope`, `image_attached` (+bulk
      `batch_start`/`batch_size`/`is_final_batch`) and **empty** before/after — **no** subject/message/HTML,
      recipient PII, image filename/path, recipient IDs, or `dry_run`
      (`F18-ADMIN-NOTIFY-AUDIT.md`). The outbound send is **irreversible** and the logger is **fail-open**
      (non-transactional) — durable persistence is best-effort; do not gate the release on every row
      persisting, and never run the notify path with `notify.dry_run=false` in verification.
- [ ] **RBAC allow-by-default confirmed** (`config('rbac.enforce') === false`). The middleware records
      `rbac.permission.would_deny` evidence for ungranted-but-allowed actions.
- [ ] **Would-deny review run** before ever considering enforcement: `php artisan rbac:would-deny-report`
      (read-only) — owner reviews coverage; do **not** flip `rbac.enforce` as part of this release.
- [ ] **Sensitive admin actions still Blade/frozen** per `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`.

## 7. Rollback checklist (if any gate fails)

1. [ ] **Stop the rollout.** Put the app into maintenance mode.
2. [ ] **Do not run compensating money mutations by hand.** Money flows are transaction-wrapped and
       idempotent (`MONEY-FLOW-AUDIT.md`); prefer restore over manual ledger edits.
3. [ ] **Revert code** to the previous released commit/tag (the recorded pre-deploy hash). No history
       rewrite, no force-push to shared branches.
4. [ ] **If a migration ran:** restore from the §2 backup snapshot point, or apply the reviewed down
       path. Re-verify money/ledger row counts and balances against the backup.
5. [ ] **Rebuild caches** (`config:clear`/`route:clear` then re-cache) for the reverted commit.
6. [ ] **Re-run smoke tests (§8).** Confirm green before lifting maintenance mode.
7. [ ] **Record incident:** what failed, the gate that caught it, restore point used, time-to-recover.

> **F17 KYC-reset caveat (irreversible side effect).** The admin account-update KYC-reset branch deletes
> KYC files from disk (`assets/verify/…`, direct `@unlink`) **before** the DB write. Code rollback and DB
> restore **cannot** recover those files — they are gone unless an independent file/object-storage backup
> exists. The F17 `DB::transaction` only co-commits the DB row + its audit row; it does **not** make file
> deletion reversible. Treat KYC-file backup as a precondition if this action is exercised near a release.

## 8. Smoke tests (post-deploy, before lifting maintenance)

- [ ] Public home + login pages render (200), assets load, no console/server errors.
- [ ] Admin login works; dashboard renders; a read-only admin list paginates.
- [ ] One benign admin action (e.g. view/read) succeeds and is audit-logged.
- [ ] Read-only money pages render (transactions/wallets/deposit/withdraw history) — **no** test
      deposit/withdrawal/transfer is performed against live gateways during smoke.
- [ ] Scheduled job heartbeat observed; queue worker processing.
- [ ] Route count still **798**; health/status endpoint OK.

## 9. Launch blockers (ALL must be GREEN to go live)

- [ ] Verified, test-restored **database backup** exists (§2).
- [ ] **No unapproved migration** ran; any migration is owner-signed and reversible (§3).
- [ ] **Secrets present** (§4) and **no secret exposed** in git/bundles/public routes/logs.
- [ ] **No public `/cron` or `/clear`-style endpoint** is reachable; scheduler driven by system cron (§5).
- [ ] **Webhook/IPN signing secret** is a real per-env secret (no shared/hardcoded default) (§4, §A).
- [ ] **Payment/gateway behavior frozen** — this release changes no payment/wallet/gateway/IPN/webhook/
      API behavior (§A).
- [ ] **`rbac.enforce=false`** for this release; no deny-by-default flip bundled in.
- [ ] **Audit logging confirmed writing** (§6); admin-audit file fallback path writable.
- [ ] **Full suite green** (200/1916/1) and **route count 798** on the release commit.
- [ ] **Rollback plan rehearsed** and restore point recorded (§7).

## Appendix A — Payment/gateway freeze rules

For any release governed by this runbook:

- No change to payment, wallet, balances, deposits, withdrawals, transfers, gateways, IPN, webhooks,
  checkout, escrow, cards, crypto, mobile-money, or API payment behavior.
- Gateway credentials may be **set** (config), but gateway **code/flow is frozen**; outstanding gateway
  hardening (signature/HMAC verification, idempotency, replay protection, raw-exception leakage) is
  tracked separately in `GATEWAY-IPN-AUDIT.md` / `GATEWAY-RESIDUALS-AUDIT.md` and is **owner-gated** —
  not bundled into a routine release.
- Money-flow atomicity/idempotency is as documented in `MONEY-FLOW-AUDIT.md` and
  `API-IDEMPOTENCY-AUDIT.md`; do not introduce manual ledger edits during deploy/rollback.

## Appendix B — Evidence template (paste into the release ticket)

```
Release commit:        <hash>
CI suite:              200 passed / 1916 assertions / 1 skipped   [link]
Route count:           798
DB backup id:          <id>  restored-tested: yes/no   [link]
Migration:             none | <approved-by + runbook ref>
Secrets verified:      APP_KEY✓ DB✓ MAIL✓ QUEUE✓ GATEWAYS✓ WEBHOOK_SECRET✓ (values NOT recorded)
rbac.enforce:          false
Audit write confirmed: yes  [evidence]
Public /cron|/clear:   not reachable  [evidence]
Smoke tests:           pass  [evidence]
Rollback point:        <backup id / commit>
Approver:              <name @ timestamp>
```
