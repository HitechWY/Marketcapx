# RBAC / Permission Audit — Current State (F8a)

**Status:** read-only audit. Documents the admin authorization model **as it exists today**. No
behavior changed. Companion to the design-only `RBAC-DESIGN.md` (the proposed future model) and the
`ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md` (what stays Blade).

## Headline

**Admin authorization is flat.** The only gate on the entire admin panel is "are you logged in as
an admin?" There is **no role/permission system in force at any layer** — not in the model, guard,
middleware, routes, controllers, or Blade. Every authenticated admin is, in effect, a super-admin
with full access to every mutation (balance adjust, deposit/withdraw approve, KYC approve, gateway
secrets, plugin upload, system update, etc.).

## Evidence (by layer)

### 1. Admin model — `app/Models/Admin.php`
Minimal. Extends `Illuminate\Foundation\Auth\User`. Declares only `$hidden = ['password',
'remember_token']` (lines 8–19). **No `role_id`, no role/permission relation, no `is_super` flag,
no `can()`/ability logic.** → *super-admin assumption.*

### 2. Auth guard / provider — `config/auth.php`
`admin` guard = `session` driver + `admins` provider (lines 49–52); provider = Eloquent
`App\Models\Admin` (lines 85–88). Password broker `admins` (lines 131–136). **Pure authentication;
no authorization concept.**

### 3. Admin middleware
- `app/Http/Middleware/RedirectIfNotAdmin.php` (alias `admin`): the *only* access gate. Body is
  `if (!Auth::guard($guard)->check()) return to_route('admin.login'); return $next(...)` (lines
  16–23). **Authentication only — no permission check.**
- `app/Http/Middleware/AdminAuditLog.php`: metadata-only audit logger applied to the whole admin
  group; its own docblock calls it a *"non-repudiation stop-gap until a proper DB-backed audit log +
  RBAC are implemented"* (lines 9–19). **Records actions; enforces nothing.**
- Registration — `bootstrap/app.php`: the admin group is mounted with `['web', AdminAuditLog]`
  (lines 45–49); the middleware alias map (lines 81–111) defines `'admin' => RedirectIfNotAdmin`
  (line 92). Laravel's `'can' => Authorize` alias exists (line 84) but **is never applied to any
  admin route.** **No `permission` / `role` alias exists** (confirmed: grep of `bootstrap/app.php`).

### 4. Route-level authorization — `routes/admin.php`
- Guest/auth block (login, password reset) under `admin.guest` (lines 5–26).
- **Everything else** is one flat group: `Route::middleware('admin')->group(...)` (line 28 → end).
  All ~190 protected admin routes (dashboard, users/agents/merchants management, deposits,
  withdrawals, gateways, settings, cron, plugins, system) inherit **only** `admin`. The dynamic
  `$manageGuardRoutes` closure (lines 69–97) — which contains `kyc.approve`, `kyc.reject`, `update`,
  `add.sub.balance`, `status`, `login` (impersonate) — is applied identically to users, agents, and
  merchants (lines 100–104) with **no per-route or per-group permission middleware.**
  → *routes with no granular authorization.*

### 5. Blade menu / UI checks — `resources/views/admin/`
Grep for `@can`/`@cannot`/`@role`/`@hasrole`/`@permission`/`can('admin…`: **0 matches.** Sidebar
items, action buttons, and forms render unconditionally for any logged-in admin. → *no UI-only
checks either (there is simply nothing).*

### 6. Controller-level authorization — `app/Http/Controllers/Admin/`
Grep for `@can`/`authorize(`/`Gate::`/`->can(`: **0 matches.** No controller method authorizes
beyond the guard. Money/KYC/settings mutations rely solely on `admin` auth.

### 7. Roles / permissions data model
- **No `Role` / `Permission` model** in `app/Models/` (grep: 0 files).
- **No roles/permissions tables in live migrations** (`database/migrations/`: 0 matches).
- A **draft only** migration exists, not run: `database/migrations-drafts/2026_01_01_000003_create_rbac_roles_permissions.php`
  (defines `roles`, `permissions`, `permission_role`, nullable `admins.role_id`). Per `RBAC-DESIGN.md`
  it must be validated on a disposable test DB, never on `paytabz_DB`.
- **No RBAC package** (composer has `spatie/laravel-ignition` only — not `spatie/laravel-permission`).

### 8. Existing admin authorization tests
- `tests/Feature/AdminAuditLogTest.php` asserts the **audit middleware** is present on admin routes.
- **No test asserts permission enforcement / denial** (none can — there is no RBAC). Other admin
  tests (`AdminDashboardTest`, `AdminReadonlyListsTest`, `AdminReportsTest`,
  `MoneyFlow/AdminBalanceFlowTest`) cover read-only rendering and money-flow atomicity, not RBAC.

## Classification

| Area | Finding |
|---|---|
| Real enforced RBAC | **None.** |
| UI-only permission checks | **None** (no Blade gates at all). |
| Missing permission checks | **All mutations** — every admin mutation route. |
| Super-admin assumption | Model, guard, routes, controllers all assume one all-powerful admin. |
| Routes with no granular authorization | **All protected admin routes** (single flat `admin` group). |
| High-risk actions needing a gate before migration | balance add/sub; deposit approve/reject; withdraw approve/reject; escrow action; KYC approve/reject; account status/ban; impersonation (`login/{id}`); gateway automatic/manual CRUD; secrets-bearing settings/notification; currency/charge config; cron; plugins/extensions; system update; destructive deletes. |

## Required conclusions

1. **Is RBAC enforced at route/controller level?** **No.** The sole enforcement is authentication
   (`RedirectIfNotAdmin`). Zero authorization at route, controller, or view layers.
2. **Which mutation actions currently rely only on admin auth?** **All of them** — there is no
   finer gate than the `admin` guard anywhere in the admin panel.
3. **Which actions require permission gates before Inertia migration?** All "high-risk" actions in
   the table above. RBAC must exist and gate these **before** any of them is moved off frozen Blade
   into Inertia mutation endpoints (see F8d design).
4. **Which tables/models represent roles/permissions?** **None today.** Only the unapplied draft
   migration (`migrations-drafts/2026_01_01_000003_…`) and the `RBAC-DESIGN.md` proposal.
5. **Safest future RBAC implementation order** (additive, backward-compatible — see `RBAC-DESIGN.md`):
   1. Land/keep migration drafts (reviewed, **not** run against `paytabz_DB`).
   2. On a disposable test DB only: run them; seed a `super-admin` role with all permissions;
      backfill existing admin(s) → `super-admin` so nobody is locked out.
   3. Add a `permission:<slug>` route middleware that **allows by default** until roles are seeded
      (super-admin short-circuits to allow); pair with the existing `AdminAuditLog`.
   4. Apply the middleware to the high-risk routes above; verify on the test DB.
   5. Flip to **deny-by-default** only once every admin has a role.
   6. Add permission-denial tests (a `support`-role admin cannot approve withdrawals, etc.).

## Risk note

Until RBAC lands, operate with a single, human-audited super-admin account and rotate credentials;
`AdminAuditLog` provides a non-repudiation trail but **prevents nothing**. This audit changes no
behavior — it is the prerequisite map for the RBAC foundation that must precede any admin mutation
UI migration.
