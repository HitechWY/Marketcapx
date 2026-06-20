# F8d — Safe Endpoint Extraction Design (Plan)

Design (docs-only) the future safe implementation for migrating admin detail/mutation pages to Inertia.

## Deliverables
- `core/docs/ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md`.
- Update `core/docs/ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md` if needed.
- Update `core/docs/INERTIA-MIGRATION-COMPLETION-MAP.md` if needed.

## Design requirements
Dedicated POST/PATCH/DELETE endpoints · CSRF-protected · permission-checked · FormRequest (or
equivalent) validation · atomic DB transaction where money/status consistency matters · idempotency
key or duplicate-submit protection for money-impacting actions · audit-log event on every mutation ·
no secret exposure in Inertia props · no raw KYC paths · no inline mutation mixed into read-only page
props · documented success/failure redirect/JSON behavior · documented rollback strategy.

## Proposed future implementation order
1. RBAC enforcement foundation.
2. Audit-log foundation.
3. Low-risk admin detail pages (read-only).
4. Support ticket reply/close.
5. User/merchant/agent status mutations.
6. KYC approve/reject.
7. Deposit/withdraw approve/reject.
8. Gateway/settings last.

## Constraints
Docs-only. No behavior change, no schema change, no UI migration this sprint. Standard gates; route count 798.
