# F5 — Admin Read-Only Audit (Plan, docs-only)

**Docs-only. No admin UI migrated.** Admin uses a separate layout (`admin/layouts/master.blade.php`,
M4 Material re-skin) + its own asset stack — a future admin Inertia migration is a large, separate
effort. F5 produces:
- `ADMIN-READONLY-INERTIA-MIGRATION-PLAN.md` — classify admin pages: read-only-safe / caution /
  frozen-sensitive / out-of-scope; recommend an admin Inertia approach (AdminLayout, admin guard
  shared props, Ziggy admin group) for a future phase.
- `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md` — every admin mutation that must stay Blade until tested
  (KYC approve/reject, deposit/withdraw approve, gateway settings, system/email settings, balance
  add/subtract, user/merchant/agent status, notifications).

## Gates: docs-only; build/tsc/phpunit unchanged; route 798. Auto-merge if docs-only + green.
