# F23 — RBAC Permission-Coverage Reconciliation (docs-only, read-only)

**Status:** docs-only / read-only reconciliation. **No enforcement, middleware, catalog, route, or config
is changed.** F23 reconciles the permission-slug counts (catalog vs applied-to-routes) and documents the
route-to-permission coverage so a future owner can review it **before** any deny-by-default decision.
`rbac.enforce` stays **`false`** (allow-by-default); no deny-by-default, no new slug, no middleware change.

Companions: `RBAC-SCHEMA.md`, `RBAC-SEEDING.md`, `RBAC-DENY-BY-DEFAULT-READINESS.md`,
`F9-RBAC-ENFORCEMENT-FOUNDATION-REPORT.md`, `F12-WOULD-DENY-REVIEW.md`,
`ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`, `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`.

---

## 1. The counts (measured read-only on master)

`PermissionCatalog` (`app/Support/PermissionCatalog.php`) is the single source of truth:

- **3** guards (`users`, `agents`, `merchants`) × **9** per-guard actions (`view`, `status.update`,
  `update`, `balance.adjust`, `login`, `notify`, `kyc.review`, `kyc.approve`, `kyc.reject`) = **27**.
- **38** flat (non-guard) slugs (dashboard, reports, finance, gateway, settings, support, content,
  system, roles).
- **Total = 65 unique slugs** (`PermissionCatalog::slugs()` → 65; no duplicates).

Applied to routes via `permission:<slug>` middleware (measured over `Route::getRoutes()`):

| Metric | Count |
|---|---|
| Catalog slugs (unique) | **65** |
| Distinct slugs **applied** to ≥1 route | **52** |
| Applied slugs **not** in catalog (drift) | **0** |
| Catalog slugs **not applied** to any route | **13** |

> **The "65 vs 52" gap is fully explained below — it is intentional, not drift.** Zero applied slugs are
> missing from the catalog, so the catalog↔route invariant (`RbacRouteCoverageTest`) holds.

## 2. The 13 unapplied catalog slugs — reconciled

| # | Slug | Why unapplied (by design) |
|---|---|---|
| 1–3 | `admin.users.view`, `admin.agents.view`, `admin.merchants.view` | read-only list GETs |
| 4 | `admin.dashboard.view` | read-only dashboard GET |
| 5 | `admin.reports.view` | read-only reports GET |
| 6 | `admin.deposits.view` | read-only list GET |
| 7 | `admin.withdrawals.view` | read-only list GET |
| 8 | `admin.escrow.view` | read-only list GET |
| 9 | `admin.gateway.view` | read-only list GET |
| 10 | `admin.settings.view` | read-only settings GET |
| 11 | `admin.support.view` | read-only ticket list GET |
| 12 | `admin.roles.view` | roles-management UI/routes not built yet (reserved) |
| 13 | `admin.roles.manage` | roles-management UI/routes not built yet (reserved) |

Two distinct reasons:

- **(a) Read-only `.view` slugs (11 of 13).** F9e deliberately gated **high-risk mutation** routes, not
  ordinary read GETs. These `.view` slugs are **reserved** so a future role can be scoped to "view X" once
  read gating is wanted; until then the read pages are allow-by-default like the rest of the panel.
  *Note the contrast:* **privileged GETs are already gated** — impersonation (`admin.*.login`) and KYC
  document view (`admin.*.kyc.review`) carry middleware and are **not** in this unapplied list (see
  `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md` "Privileged GET coverage").
- **(b) Roles-management slugs (2 of 13).** `admin.roles.view` / `admin.roles.manage` are catalog
  placeholders for an RBAC role-management admin UI that **does not exist yet** (the foundation is
  allow-by-default, seeded via `rbac:install`, with no in-panel role editor). They will bind to routes
  when/if that UI ships — owner-gated.

## 3. Coverage-reporting mechanisms (already exist — read-only)

- **Static drift test:** `RbacRouteCoverageTest` (F9e) asserts every `permission:<slug>` on any route
  exists in `PermissionCatalog` and that no non-admin route is gated. F23 confirms this passes
  (applied-not-in-catalog = 0).
- **Runtime would-deny review:** `php artisan rbac:would-deny-report` (F12) summarises the
  `rbac.permission.would_deny` evidence the middleware records under allow-by-default, and flags
  **unmapped** slugs seen at runtime (catalog↔log drift) and legacy null-role admins. Read-only; never
  writes; never emits sensitive columns.

Together these cover both directions of drift: **route→catalog** (static test) and **log→catalog**
(runtime report). F23 adds the **catalog→route** view (which slugs are reserved/unapplied) that was not
previously written down.

## 4. What F23 does NOT do (guardrails)

- Does **not** apply the 13 unapplied slugs to routes (that is a coverage/behavior decision — owner-gated).
- Does **not** change `rbac.enforce`, add deny-by-default, alter `PermissionMiddleware`, or add/rename a
  slug.
- Does **not** build the roles-management UI.
- Adds no route, no migration, touches no real DB.

## 5. Recommended (FUTURE owner-gated, not done here)

1. Before any deny-by-default flip, complete `RBAC-DENY-BY-DEFAULT-READINESS.md` and review
   `rbac:would-deny-report` output for real admins.
2. Decide per read page whether `.view` gating is wanted; if so, apply the reserved `.view` slugs.
3. When a role-management UI is built, bind `admin.roles.view/manage` to its routes.
4. Optionally add a tiny **read-only** test/report assertion that pins the catalog/applied/reserved counts
   (65 / 52 / 13) so future drift is caught — test-only, no enforcement change.

## 6. Status

F23 is docs-only: it reconciles 65 catalog slugs = 52 applied + 13 intentionally-unapplied (11 read-only
`.view`, 2 reserved roles-management), confirms **zero** route→catalog drift, and points to the existing
static + runtime coverage tooling. No enforcement/middleware/catalog/route/config change; `rbac.enforce`
stays `false`; no deny-by-default; no migration; no real DB.
