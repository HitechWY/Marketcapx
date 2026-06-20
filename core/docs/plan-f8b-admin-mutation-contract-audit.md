# F8b — Admin Mutation Contract Audit (Plan)

Audit every high-risk admin mutation contract without changing it. Documentation over execution.

## Categories
user/merchant/agent status (block/unblock) · KYC approve/reject · deposit approve/reject ·
withdrawal approve/reject · manual balance adjustment · support ticket reply/close/delete ·
gateway/payment-method enable/disable/update · system settings update · notification/email
template update · role/permission changes · destructive deletes · private file/KYC downloads.

## Per-mutation fields to document
route name · URL · HTTP method · controller@method · Blade view / triggering page ·
form/action/button source · input names · hidden fields · CSRF status · validation rules ·
model rows affected · balance impact · notification/email side effects · audit-log side effects ·
current permission check · current transaction/atomicity status · idempotency risk ·
required tests before migration · risk rating.

## Deliverables
- `core/docs/ADMIN-MUTATION-CONTRACT-AUDIT.md`.
- Optional read-only route-inventory test asserting the documented mutation routes exist with the expected verb/name (no endpoint execution).

## Rules
Do not change mutation methods. Do not call success paths except inside rollback-protected test
transactions and only if safe. Never run a balance-affecting success path outside a rollback.
Prefer documentation. Standard gates; route count stays 798.
