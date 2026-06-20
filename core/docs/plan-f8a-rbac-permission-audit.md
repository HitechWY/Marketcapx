# F8a — RBAC / Permission Audit (Plan)

Read-only audit of the current admin authorization model. Document what exists; change no behavior.

## Method
Enumerate and classify, with file references:
- Admin model (`app/Models/Admin.php`) — role/permission fields?
- Auth guard + provider (`config/auth.php`, `admin` guard).
- Admin middleware (`app/Http/Middleware/RedirectIfNotAdmin.php`, `AdminAuditLog.php`) and registration (`bootstrap/app.php`).
- Any permission/role middleware, roles/permissions tables/models, third-party RBAC package.
- Admin route-group middleware (`routes/admin.php`).
- Sidebar/menu permission checks in admin Blade.
- Controller-level authorization (`$this->authorize()`, `Gate`, `can()`).
- Existing admin permission tests.

Classify each area as: enforced RBAC · UI-only check · missing check · super-admin assumption · route with no granular authorization · high-risk action needing a permission gate.

## Deliverables
- `core/docs/RBAC-PERMISSION-AUDIT.md` — current-state audit (distinct from the design-only `RBAC-DESIGN.md`, which it cross-references).
- Optional non-mutating test proving current behavior (admin group carries `admin` middleware; no permission middleware exists today).

## Required conclusions
RBAC enforced at route/controller? Which mutations rely only on admin auth? Which need permission gates before Inertia migration? Which tables/models represent roles/permissions? Safest future RBAC implementation order.

## Constraints
Docs/tests only. No behavior change. Standard gates; route count stays 798.
