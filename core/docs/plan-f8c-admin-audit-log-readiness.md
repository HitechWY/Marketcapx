# F8c — Admin Audit-Log Readiness (Plan)

Determine whether sensitive admin mutations are auditable enough for fintech-grade operation.

## Inspect
`app/Http/Middleware/AdminAuditLog.php` (current metadata logger) · activity/admin-action logs ·
transaction logs · support ticket logs · notification logs · who/when/IP/user-agent capture ·
before/after value capture · sensitive payload masking · log retention (config/logging.php).

## Deliverables
- `core/docs/ADMIN-AUDIT-LOG-READINESS.md`.
- Tests only if safe and non-mutating (the admin group's `AdminAuditLog` registration is already
  covered by `tests/Feature/AdminAuditLogTest.php`; extend only if it adds value without mutation).

## Required conclusion (per high-risk mutation category)
currently audited or not · who is captured · target entity captured or not · before/after captured
or not · payload masked or not · required audit fields before migration.

## Constraints
Docs/tests only. No behavior change. Standard gates; route count stays 798.
