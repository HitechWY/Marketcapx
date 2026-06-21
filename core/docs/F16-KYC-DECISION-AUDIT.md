# KYC Approve/Reject — Semantic Admin-Audit (F16)

**Status:** F16 adds two semantic admin-mutation events — `account.kyc.approved` and
`account.kyc.rejected` — around the admin KYC decision for users, agents, and merchants. **Behavior is
unchanged**: validation, the `kv` / `kyc_rejection_reason` changes, the `notify()` email/SMS, the
redirect/flash, and the 404 path are all as before; only a **status-only** audit row is added (the `kv`
change and its audit row co-commit in one transaction). The audit **never** carries the rejection reason
text, KYC documents/filenames/paths, `kyc_data`, or any PII. No new routes (route count **798**), no
Inertia/Vue/Blade change, no migration, no real DB, `rbac.enforce` still **false**. Companions:
`F15-ACCOUNT-STATUS-AUDIT.md`, `F14b-SUPPORT-TICKET-REPLY-AUDIT.md`, `KYC-PII-READINESS.md`,
`ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`.

## What F16 shipped

- **Events:** approve → `account.kyc.approved`; reject → `account.kyc.rejected` (house style
  `account.<entity>.<verb>`; the guard is carried in `entity_type` + `metadata.guard`, mirroring F15).
  Written via `AdminAuditLogger::logMutation()` (`result='succeeded'`).
- **Where:** `app/Http/Controllers/Admin/ManageGuardController.php` `kycApprove($id)` and
  `kycReject(Request,$id)` — the guard-parameterized admin-only methods (no shared trait; no user-side
  panel uses them, so the edit affects only admin KYC decisions).
- **Change shape (minimal):** capture `before = ['kv' => (int) $user->kv]` before the decision; keep the
  existing field set + `notify()` + redirect; move the existing `$user->save()` into a `DB::transaction`
  that also writes the audit row; `notify()` stays **after and outside** the transaction.

## Why this is the F15 (transactional) pattern, not F14b

Unlike the support-ticket reply (F14b), KYC approve/reject are edited **directly** and `notify()` is
already the **last** step after `save()`. So F16 wraps only `save()` + audit in a transaction (like F15
status) and leaves `notify()` after it — giving atomic `kv`+audit co-commit on the success path **without**
changing behavior (notify was always after save, and remains outside the transaction). `logMutation`
stays **fail-open**: a failed audit write is caught/file-logged and the transaction still commits the
`kv` change, so the KYC decision is never broken. A reject **validation failure** (`reason` required)
throws **before** the transaction → no audit row; a **missing account** 404s before any write → no row.

## What the audit row captures (and excludes)

| Field | Value |
|---|---|
| `action` | `account.kyc.approved` / `account.kyc.rejected` |
| `result` | `succeeded` |
| `entity_type` / `entity_id` | guard (`users`/`agents`/`merchants`) / the account id |
| `before_values` | `{ kv: <prior int> }` |
| `after_values` | `{ kv: <new int> }` (`KYC_VERIFIED=1` / `KYC_UNVERIFIED=0`) |
| `metadata` | `{ guard, decision: 'approved'|'rejected' }` |
| admin + `method`/`path`/`ip`/`route_name`/`correlation_id` | from the admin + Request |

**Never captured:** the rejection `reason` / `kyc_rejection_reason` text, KYC document filenames/paths,
`kyc_data`, username/email/any PII, balances, tokens, secrets. `sanitizeMetadata` still redacts
SENSITIVE_KEYS (values here are ints / guard label / decision word). Per `KYC-PII-READINESS.md`, KYC
documents live under filename-obscurity only and must never appear in logs — F16 never touches them.

## Relationship to the F10 generic row

The F10 `AdminAuditLog` middleware still logs its generic `admin.request.mutation` row for the KYC POST
(entity inferred as the guard + id, no before/after, no request body). The F16 semantic row is
**complementary** (adds the named decision + before/after `kv` + guard), not a duplicate. Tests assert
specifically on `action='account.kyc.approved|rejected'`.

## RBAC

`admin.{guard}.kyc.approve|reject` already carry `permission:admin.{guard}.kyc.approve|reject` (F9). **No
middleware change**, no new slug. `rbac.enforce` stays **false** (allow-by-default); KYC behavior
unchanged. No deny-by-default.

## Deferred (next phases)

- **F17 — profile/verification update** (`account.profile.updated`): changed field-**names** only, never
  values (PII); the KYC-file-deletion branch handled carefully.
- **F18 — notify single/all**: channel + recipient_count + scope-name only.
- Money/gateway/IPN/API and any deny-by-default work remain **owner-gated**.

## Status after F16

KYC-decision semantic audit wired for all three guards; full suite green (**200 tests / 1916 assertions /
1 skipped**, up from 194 / 1887 / 1). Route count **798** unchanged. `rbac.enforce` remains **false**. No
behavior change to KYC, no reason text / KYC files / PII in the audit, no routes/migrations/real-DB, no
money/gateway touched.
