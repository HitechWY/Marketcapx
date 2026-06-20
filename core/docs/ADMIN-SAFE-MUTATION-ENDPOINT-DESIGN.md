# Admin Safe Mutation Endpoint Design (F8d)

**Status:** design only (docs). No code, schema, route, or UI change in this sprint. This is the
target contract for migrating admin detail/mutation pages off frozen Blade into Inertia **safely**.
Built on the F8 audits: `RBAC-PERMISSION-AUDIT.md` (F8a), `ADMIN-MUTATION-CONTRACT-AUDIT.md` (F8b),
`ADMIN-AUDIT-LOG-READINESS.md` (F8c). Honors `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md` and
`RBAC-DESIGN.md`.

## Why (problem recap)

Today every admin mutation is gated only by the admin auth guard (flat — F8a), validated inline if
at all with no FormRequests (F8b), atomic only on some money paths, idempotent only where a status
pre-check happens to exist, and audited only as a metadata log line with no entity id / before-after
/ admin attribution — with GET-based privileged actions (impersonation, cron pause, system optimize)
unlogged entirely (F8c). Moving any of this into Inertia without first fixing authorization,
auditing, validation, atomicity, and idempotency would widen the blast radius. **Two foundations
(RBAC, audit log) must land before any mutation UI migrates.**

## Endpoint contract (every migrated admin mutation MUST satisfy)

1. **Dedicated verb-correct endpoint.** A single mutation = a dedicated `POST`/`PATCH`/`DELETE`
   route. **No privileged mutation may be a GET** (fixes today's GET impersonation/cron-pause/
   optimize gap). Reads stay GET; mutations never pigg-back on a read.
2. **CSRF-protected.** Stays in the `web` group; never added to the `preventRequestForgery`
   except-list. Inertia's XSRF-token handling covers the SPA calls.
3. **Permission-checked.** Gated by the RBAC `permission:<slug>` middleware (per `RBAC-DESIGN.md`) in
   addition to the `admin` guard. Controller re-checks with `authorize()`/policy as defense in depth.
   Both **allowed and denied** attempts are audited.
4. **FormRequest validation.** Every input validated in a dedicated FormRequest (not inline). All
   persisted fields must be validated — including ones unvalidated today (`wallet_id`, withdraw/
   deposit `details`, account `update` mobile/address).
5. **Atomic where money/status consistency matters.** Wrap balance/status transitions in
   `DB::transaction` with `lockForUpdate` and an under-lock status re-check (the Phase 3/5/13 pattern).
   Extend this to KYC approve and status toggle so they are atomic too.
6. **Idempotency / duplicate-submit protection for money-impacting actions.** Either a status
   pre-check under lock (deposit/withdraw already do this) **or** a per-submission idempotency key
   (required for balance add/sub, which has none today). Re-submitting must be a safe no-op.
7. **Audit event on every mutation.** Write a structured DB audit record (see schema below) inside
   the same transaction as the mutation, capturing admin_id, target entity, action, before/after,
   ip, user-agent, reason, idempotency key, result.
8. **No secret exposure in props.** Inertia props for detail/mutation pages are explicit field
   allow-lists. Never serialize password/remember_token/2FA secret/API keys/gateway credentials/
   SMTP/OAuth secrets/raw KYC paths/withdraw_information (extends the F7a `InertiaPropExposureTest`
   guard). Secret-bearing settings stay server-rendered or use write-only fields (submit-without-
   read-back).
9. **No raw KYC/storage paths.** KYC documents served via signed, permission-checked, time-limited
   controller routes — never a raw `storage/...` path in a prop.
10. **No inline mutation mixed into read-only page props.** A page's read props and its mutation
    endpoints are separate; the page calls the dedicated endpoint, it does not receive mutate-capable
    data inline.
11. **Documented success/failure behavior.** Inertia mutations return a redirect with flash
    (`->withNotify`) on success and `back()->withErrors()` on validation/authz failure (HTTP 422/403);
    document any JSON variant. Consistent across all migrated mutations.
12. **Documented rollback strategy.** Keep the existing Blade view + route as a one-line rollback
    (the established per-phase pattern); a feature flag or route swap reverts a page without code loss.
    DB rollback = the wrapping transaction; no partial writes.

## Proposed `admin_audit_logs` schema (foundation, design only — not run)

`id`, `admin_id`, `admin_username`, `ip`, `user_agent`, `action_type` (APPROVE/REJECT/BAN/UNBAN/
ADJUST_BALANCE/IMPERSONATE/SETTING_UPDATE/DELETE/…), `entity_type`, `entity_id`, `before` (JSON,
masked), `after` (JSON, masked), `reason`, `idempotency_key` (nullable, unique per action where
applicable), `result` (success/denied/failed), `created_at`. Written via a small service/trait
invoked from each mutation inside its transaction. Sensitive values masked before persistence.
**Migration lives under `database/migrations-drafts/` and is run only on a disposable test DB**, never
on `paytabz_DB` (same discipline as the RBAC draft).

## Future implementation order (safe sequence)

1. **RBAC enforcement foundation** — land/seed roles+permissions on a test DB; add allow-by-default
   `permission` middleware; flip to deny-by-default once admins have roles (`RBAC-DESIGN.md`).
2. **Audit-log foundation** — `admin_audit_logs` table + service; wire into existing Blade mutations
   first (no UI change) so the trail exists before any migration; cover GET privileged actions too.
3. **Low-risk admin detail pages (read-only)** — migrate detail views to Inertia with allow-listed,
   secret-free props (no mutation yet).
4. **Support ticket reply/close** — lowest-risk mutations; already the best-audited (admin_id on
   message); good first real mutation migration.
5. **User/merchant/agent status mutations** — ban/unban via dedicated POST + permission + audit.
6. **KYC approve/reject** — add atomicity + idempotency + audit; signed KYC document access.
7. **Deposit/withdraw approve/reject** — already atomic+idempotent; add FormRequest + admin-attributed
   audit; migrate last among money actions.
8. **Gateway / settings (secrets) last** — write-only secret fields; never read secrets into props;
   only after the above are proven.

Escrow, gateway/IPN/webhook/checkout, and wallet-mutation internals remain **out of scope / frozen**
and require their respective owners' sign-off independent of this sequence.

## Acceptance tests each migration must add (target)

Validation (FormRequest) · authorization allow **and** deny (post-RBAC) · success path **inside DB
rollback only** · idempotency/duplicate-submit no-op · atomicity under concurrency (balance
consistency) · audit record written with admin_id + entity_id + before/after · **prop-exposure
assertion that no secret/PII is serialized** (extends `InertiaPropExposureTest`). No success path
that moves money or approval state may run outside a rollback-protected test.
