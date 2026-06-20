# F9 — RBAC Enforcement Foundation — Report

> RBAC foundation, **allow-by-default**. No payment/wallet/KYC/gateway/IPN/webhook/checkout/escrow or
> admin-mutation behavior changed. Deny-by-default is **not** enabled. No migration was run against
> any real DB.

## 1. Starting baseline
`master == origin/master == a91f007`, clean tree. Route count 798. Tests 131/1158/1. Build + vue-tsc
green; composer valid.

## 2. Final master commit
`47a12ad` (after this report merges, the report-merge becomes HEAD). **Not pushed** in this sprint
(no deploy/push performed); `origin/master` stays `a91f007` until a sync step.

## 3. Branches created
`hardening/rbac-schema-finalize`, `hardening/rbac-models-catalog`, `hardening/rbac-superadmin-seed`,
`hardening/rbac-permission-middleware`, `hardening/rbac-apply-middleware`,
`docs/rbac-deny-by-default-readiness`, `docs/f9-final-report`.

## 4. Branches merged
All of the above, each `--no-ff` into master.

## 5. Branches skipped and why
None.

## 6. Commit / merge hashes
| Phase | Work commit | Merge commit |
|---|---|---|
| F9a schema | `b7420a9` | `6a5e40e` |
| F9b models+catalog | `1f4dd04` | `963163b` |
| F9c seeder+command | `38594a4` | `0f37c99` |
| F9d middleware | `c3c311f` | `20cbff3` |
| F9e apply middleware | `39b9d2d` | `525d4f7` |
| F9f readiness docs | `51f9982` | `47a12ad` |
| Final report | _this commit_ | _this merge_ |

## 7. Files changed per phase
- **F9a:** promote `database/migrations/2026_01_01_000003_create_rbac_roles_permissions.php` (from
  drafts; +pivot FKs +role_id index), `migrations-drafts/README.md`, `docs/RBAC-SCHEMA.md`.
- **F9b:** `app/Models/Role.php`, `app/Models/Permission.php`, `app/Models/Admin.php` (+role()/
  hasPermission()), `app/Support/PermissionCatalog.php`, `tests/Feature/RbacStructureTest.php`,
  `tests/Feature/RbacPermissionAuditTest.php` (model guard updated).
- **F9c:** `database/seeders/RbacSeeder.php`, `app/Console/Commands/RbacInstall.php`,
  `docs/RBAC-SEEDING.md`, `tests/Feature/RbacSeederTest.php`.
- **F9d:** `app/Http/Middleware/PermissionMiddleware.php`, `config/rbac.php`, `bootstrap/app.php`
  (alias), `tests/Feature/PermissionMiddlewareTest.php`, `RbacPermissionAuditTest.php` (alias guard).
- **F9e:** `routes/admin.php` (permission middleware on high-risk routes; $guard-parameterized
  closure), `tests/Feature/RbacRouteCoverageTest.php`, `RbacPermissionAuditTest.php` (baseline guard).
- **F9f:** `docs/RBAC-DENY-BY-DEFAULT-READINESS.md`, `docs/INERTIA-MIGRATION-COMPLETION-MAP.md`,
  `docs/RBAC-PERMISSION-AUDIT.md`.
- **Whole sprint:** `git diff a91f007..HEAD` = 22 files; **no controller/service/gateway/IPN/escrow
  file touched** (verified by name filter).

## 8. Migration / schema summary
Additive, backward-compatible. `roles`, `permissions`, `permission_role` (unique pair + FKs to the
new tables, cascadeOnDelete), nullable indexed `admins.role_id` (null = legacy full access; FK
deliberately deferred). `down()` drops in FK-safe order. Promoted into `database/migrations/`; run
**only** against `marketcapx_test`; real-DB run owner-gated (`RBAC-SCHEMA.md`).

## 9. Models / catalog summary
`Role` (belongsToMany permissions; `super-admin` short-circuit), `Permission` (belongsToMany roles),
`Admin::role()` + `Admin::hasPermission($slug)` (null role_id → legacy allow; super-admin → allow;
role present → has-slug; dangling role_id → false). `App\Support\PermissionCatalog` = single source
of truth, **65 slugs** (per-guard users/agents/merchants action sets + finance/gateway/settings/
support/content/system/reports/roles groups).

## 10. Seeder / command summary
`RbacSeeder` (idempotent: upserts all catalog permissions + super-admin role, grants all via
syncWithoutDetaching). `php artisan rbac:install [--admin=<id>]` wraps it; `--admin` assigns an
**existing** admin to super-admin only when passed (fails on unknown id), and **never creates
accounts**. Verified idempotent; verified zero rows persist after tests.

## 11. Middleware behavior summary
`permission:<slug>` (alias `permission`). Allows when the admin has the permission (incl. super-admin
and legacy null role_id). Otherwise logs a decision to the admin-audit channel (metadata only) and:
allow-by-default (`config('rbac.enforce')=false`, the shipped default) → **allow** ("would_deny"
logged); deny-by-default (`true`, owner-gated) → **403** ("denied" logged). Default verified false.

## 12. Routes protected with permission middleware (F9e)
Per-guard (users/agents/merchants): `kyc.review`, `kyc.approve`, `kyc.reject`, `update`,
`balance.adjust`, `login` (impersonation), `status.update`, `notify`. Money: deposit approve/reject,
withdrawal approve/reject + methods, escrow action, transaction charges. Secrets/config: gateway
(automatic/manual update/status/remove), currency, general/system/socialite settings, notification/
email/sms/push settings+templates, KYC settings. System/code: cron, module, extensions, plugins,
system optimize/update. Content: frontend, page builder, language, category, subscriber. Support:
reply/close/delete. **Route count unchanged (798)**; no controller/name/URL/verb changed; every
applied slug exists in the catalog (drift-tested); no non-admin route is gated (tested).

## 13. Tests added and final suite result
Added: `RbacStructureTest` (6), `RbacSeederTest` (5), `PermissionMiddlewareTest` (6),
`RbacRouteCoverageTest` (4) — all DB-backed on `marketcapx_test` with `DatabaseTransactions`, plus
the F9-evolved `RbacPermissionAuditTest` (3). Final suite: **152 passed / 1418 assertions /
1 skipped** (baseline 131/1158/1 + 21 net tests).

## 14. Route count
**798** — unchanged.

## 15. Build / typecheck results
`npm run build` ✅; `vue-tsc --noEmit` ✅ (exit 0); `composer validate` valid.

## 16. DB safety result
RBAC migration applied only to `marketcapx_test`. All seeding/role-assignment ran inside test
`DatabaseTransactions` and rolled back — verified `roles=0, permissions=0` persisted. `paytabz_DB`
untouched.

## 17. Confirmation — no real DB migration was run
Confirmed. The migration ran only against the disposable `marketcapx_test`. No dev/staging/prod or
`paytabz_DB` migration.

## 18. Confirmation — no admin mutation behavior changed
Confirmed. allow-by-default + every existing admin has null `role_id` (legacy allow) ⇒ identical
behavior. No admin controller/method/route-name/URL/verb changed.

## 19. Confirmation — deny-by-default remains disabled
Confirmed. `config('rbac.enforce')` default = **false** (asserted by a test). No env default set true.

## 20. Confirmation — no payment/wallet/KYC/gateway/IPN/checkout/escrow behavior changed
Confirmed. Diff touches models/middleware/config/seeder/command/migration/routes/docs/tests only —
no controller/service/gateway/IPN/escrow file. `work/escrow-atomicity` untouched.

## 21. Remaining risks
1. Enforcement is **not** active yet (allow-by-default) — RBAC provides no protection until the
   owner-gated deny flip; until then the panel remains effectively flat in behavior.
2. Real-DB schema/seed not applied (owner-gated) — must run before enforcing.
3. Admins still need roles assigned before deny-by-default (null role_id = legacy allow).
4. Admin mutations remain **unaudited** at the DB level (the audit-log foundation is a separate phase;
   RBAC currently logs decisions to the file channel only).
5. Read-only admin routes are mostly ungated (by design this phase); revisit during enforcement.

## 22. Recommended next phase
**F10 — Admin audit-log foundation** (F8d `admin_audit_logs`): DB-backed, queryable audit with
admin_id + entity + before/after + result (incl. denied), and swap `PermissionMiddleware::logDecision`
to write to it. This unblocks fintech-grade auditability and is the last prerequisite (with the
owner-gated deny-by-default flip per `RBAC-DENY-BY-DEFAULT-READINESS.md`) before migrating admin
mutation UI to Inertia.
