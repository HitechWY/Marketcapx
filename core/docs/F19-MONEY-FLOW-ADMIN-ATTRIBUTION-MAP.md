# F19 — Money-Flow Admin-Attribution Map (docs-only, read-only)

**Status:** docs-only, read-only audit. **No money code is changed, proposed-as-code, or executed by
this file.** It maps *where an admin action moves or authorizes money* and *what attribution exists today*
vs *what is missing*, so a future, **owner-gated** phase can decide whether/how to attribute money
mutations. F19 changes **no** payment/wallet/deposit/withdrawal/transfer/escrow/gateway/ledger behavior,
adds no routes, runs no migration, touches no real DB. `rbac.enforce` stays `false`.

Companions: `MONEY-FLOW-AUDIT.md` (atomicity/idempotency — already hardened), `ADMIN-AUDIT-LOG-READINESS.md`
(gap table), `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md` (F10 middleware + F14–F16 semantic events),
`ADMIN-AUDIT-LOG-SCHEMA.md`, `ADMIN-MUTATION-CONTRACT-AUDIT.md`, `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md`.

---

## 1. What "admin attribution" means here

Three independent layers can record *who did a money action*:

1. **Request-level (exists, F10d):** the `AdminAuditLog` middleware writes an `admin.request.mutation`
   row for every admin POST/PUT/PATCH/DELETE — capturing **admin_id, admin_username, method, path,
   route_name, ip, correlation_id, http_status** and a best-effort `entity_type`/`entity_id` from route
   metadata. This already answers "*which admin hit which money endpoint, when*" — but **not** the exact
   target row, the before/after amounts, or the resulting ledger id.
2. **Semantic event (exists for status/KYC/support, F14–F16; absent for money):** a controller hook calls
   `AdminAuditLogger::logMutation(action, admin, entity, id, before, after, metadata)` with a sanitized
   before/after snapshot, co-committed in the action's transaction. **No money flow uses this yet.**
3. **Ledger-level (absent):** the financial `Transaction` row that records the actual balance movement
   has **no `admin_id` column** — so a row created by an admin reject/adjust is indistinguishable, at the
   ledger, from a system- or user-initiated one.

> Layer 1 makes money actions **traceable** in the audit log. Layers 2 and 3 are the **gaps**: there is
> no semantic money-decision event and no admin stamp on the ledger row itself.

## 2. Money-moving admin actions — attribution map (verified read-only)

| # | Admin action | Controller @ method | Atomicity (today) | Writes ledger `Transaction`? | `admin_id` on ledger? | Semantic `logMutation`? | Request row (F10d)? |
|---|---|---|---|---|---|---|---|
| 1 | Manual balance add/subtract | `ManageGuardController@addSubBalance` (L354) | ✅ `DB::transaction` + `lockForUpdate` (Phase 5) | ✅ yes (`balance_add` / `balance_subtract`) | ❌ no | ❌ no | ✅ yes |
| 2 | Deposit approve (credit wallet) | `DepositController@approve` (L230) → `PaymentController::userDataUpdate` | per `PaymentController` credit path (see `MONEY-FLOW-AUDIT.md`) | ✅ yes (credit) | ❌ no | ❌ no | ✅ yes |
| 3 | Deposit reject | `DepositController@reject` (L241) | status + `admin_feedback` only (no money move) | ❌ n/a (no balance change) | — | ❌ no | ✅ yes |
| 4 | Withdrawal approve | `WithdrawalController@approve` (L204) | status + `admin_feedback` only (no balance move) | ❌ n/a | — | ❌ no | ✅ yes |
| 5 | Withdrawal reject (refund) | `WithdrawalController@reject` (L227) | ✅ `DB::transaction` + `lockForUpdate`, idempotent re-check (Phase 13) | ✅ yes (`withdraw_reject` refund `+`) | ❌ no | ❌ no | ✅ yes |
| 6 | Escrow dispute resolve (payouts) | `EscrowController@action` (L148) | ✅ `DB::transaction` + `lockForUpdate` | ✅ yes (buyer/seller payouts) | ❌ no on the fund/ledger move (admin_id is set only on the dispute **message**, L119) | ❌ no | ✅ yes |

**Notes (evidence):**
- The `Transaction` model has **no `admin_id`** field (no fillable/guarded reference); ledger rows record
  `user_id`, `user_type`, `wallet_id`, `amount`, `post_balance`, `trx_type`, `remark`, `trx` — but not the
  acting admin. So rows 1, 2, 5, 6 above are **unattributed at the ledger**.
- `admin_feedback` (deposit/withdraw) and the escrow dispute **message.admin_id** capture *some* admin
  context on the **business** row, but that is not a uniform, queryable money-decision attribution and is
  not on the **ledger** row.
- Atomicity/idempotency for these flows is **already hardened** (`MONEY-FLOW-AUDIT.md`, Phases 3/5/13 +
  escrow). F19 does **not** revisit atomicity — only attribution/audit visibility.

## 3. The gap, precisely

- **No semantic money-decision audit event.** Unlike `account.status.changed` (F15) and
  `account.kyc.approved/rejected` (F16), there is no `deposit.approved`, `withdrawal.rejected`,
  `account.balance.adjusted`, or `escrow.resolved` event with before/after balance + amount + admin.
- **No ledger attribution.** The `transactions` row that *is* the money movement does not name the admin
  who caused it; reconciling "which admin refunded/credited what" requires correlating the F10d request
  row to the ledger by time/route/trx — possible but indirect.
- **Consequence:** money actions are **traceable** (who hit the endpoint) but not **semantically audited**
  (what changed, by how much) nor **ledger-attributed** (which row, which admin).

## 4. Why this is documented, not implemented (owner-gated)

Closing the gap touches money code and/or schema, all of which are **frozen** in this docs/audit track:

- **Semantic event** would add a `logMutation` call **inside each money transaction** (the F15/F16
  pattern). Low-risk in shape, but it edits payment-adjacent controllers → **owner-gated**.
- **Ledger `admin_id`** would require a **migration** (new nullable `transactions.admin_id`) plus writes
  in each flow → **migration + money-code change → owner-gated**, and must follow
  `F11-REAL-DB-SCHEMA-APPLY-RUNBOOK.md` and the freeze list.
- Any before/after money snapshot must be **field-allow-listed and sanitized** (amounts/ids only — never
  gateway secrets, card/crypto/mobile-money detail, or PII) via `AdminAuditLogger::sanitizeMetadata`.

## 5. Suggested sequencing (for a FUTURE owner-gated phase — not done here)

1. **Phase A — semantic events only (no schema).** Add `logMutation` (status/amount/before-after, admin)
   inside the existing transactions for actions 1/2/5/6, mirroring F15/F16. Tests-first on
   `marketcapx_test` with `DatabaseTransactions`. No migration, no ledger change.
2. **Phase B — ledger attribution (schema).** Only if owner approves: additive nullable
   `transactions.admin_id`, populated on admin-initiated movements; backfill not required. Strictly per
   F11 runbook; money semantics unchanged.
3. **Phase C — reconciliation report (read-only).** An artisan report joining money-decision events ↔
   ledger rows for an audit/compliance view, like `rbac:would-deny-report` is for RBAC.

Each phase is independently revertible and **must not** alter deposit/withdraw/transfer/escrow/gateway
behavior, balances, or idempotency.

## 6. Status

F19 is docs-only. It records the money-flow admin-attribution map and the precise gap (no semantic
money-decision event; no `admin_id` on the ledger), and defers any code/schema to an explicitly
owner-gated phase. No payment/wallet/gateway/IPN/webhook/API behavior changed; no routes, no migration,
no real DB; `rbac.enforce` stays `false`.
