# Account Profile Update — Semantic Admin-Audit (F17)

**Status:** F17 adds one semantic admin-mutation event — `account.profile.updated` — around the admin
account-edit form for users, agents, and merchants. **Behavior is unchanged**: validation, the
mobile-duplicate guard, every field assignment, the KYC-reset file deletion, the redirect/flash, and the
404 path are all as before; only a **category-only** audit row is added (the `save()` and its audit row
co-commit in one transaction on the success path). The audit records **changed field names**, the four
verification-flag ints, and KYC-reset **signals** only — **never** any PII value, KYC document data, KYC
filenames, or KYC file paths. No new routes (route count **798**), no Inertia/Vue/Blade change, no
migration, no real-DB mutation, `rbac.enforce` still **false**. Companions: `F16-KYC-DECISION-AUDIT.md`,
`F15-ACCOUNT-STATUS-AUDIT.md`, `KYC-PII-READINESS.md`, `ADMIN-AUDIT-LOG-ACTION-COVERAGE.md`.

## Scope

- **Event:** `account.profile.updated` (house style `account.<entity>.<verb>`; the guard is carried in
  `entity_type` + `metadata.guard`, mirroring F15/F16). Written via `AdminAuditLogger::logMutation()`
  (`result='succeeded'`).
- **Where:** `app/Http/Controllers/Admin/ManageGuardController.php` `update(Request,$id)` — the single
  guard-parameterized admin-only method shared by **users, agents, and merchants** (`$this->guardType`
  resolves the model). Admin-only; no user/agent/merchant self-service panel uses this method.
- **Routes (unchanged):** `POST admin/{guard}/update/{id}`, names `admin.{guard}.update`, each already
  carrying `permission:admin.{guard}.update` (F9). No middleware change, no new slug, no deny-by-default.

## Change shape (minimal)

Capture `before = ['ev','sv','ts','kv']` (ints) **after** the validation + mobile-duplicate guards and
**before** the field assignments; keep every existing assignment and the KYC-reset branch byte-for-byte;
add a `$filesRemoved` counter inside the existing reset loop; capture changed column **names** via
`array_keys($user->getDirty())`; move the existing `$user->save()` into a `DB::transaction` that also
writes the audit row. The `notify()`/flash + redirect stay after and outside the transaction.

## Payload contract — what the audit row captures

| Field | Value |
|---|---|
| `action` | `account.profile.updated` |
| `result` | `succeeded` |
| `entity_type` / `entity_id` | guard (`users`/`agents`/`merchants`) / the account id |
| `before_values` | `{ ev, sv, ts, kv }` — prior verification-flag ints only |
| `after_values` | `{ ev, sv, ts, kv }` — new verification-flag ints only |
| `metadata` | `{ guard, changed_fields, kv_reset, files_removed }` |
| admin + `method`/`path`/`ip`/`route_name`/`correlation_id` | from the admin + Request |

- `changed_fields` — a list of **column names** that were dirty (e.g. `email`, `address`, `kv`,
  `kyc_data`), never the submitted values. Names are list **values**, so they pass through the redactor
  untouched (the redactor inspects keys, not values).
- `kv_reset` — boolean signal that the KYC-reset branch fired. Deliberately keyed `kv_reset`, **not**
  `kyc_*`, because `AdminAuditLogger::SENSITIVE_KEYS` redacts any key containing `kyc`; a `kyc_*` key would
  be silently `[REDACTED]` and the signal lost.
- `files_removed` — integer **count** of KYC files deleted by the reset branch. A count only — never a
  path, filename, or `kyc_data` value.

## Explicit exclusions — what the audit row NEVER carries

No `firstname`/`lastname`/`email`/`mobile`/`address`/`city`/`state`/`zip`/`country` **values**; no
`country_name`/`dial_code` values; no old/new profile values of any kind; no KYC file **paths**
(`getFilePath('verify')`); no KYC **filenames** (`$kycData->value`); no `kyc_data` **contents**; no
`kyc_rejection_reason`; no balances, wallet/payment/gateway/IPN/webhook/API data; no tokens/secrets.
`before`/`after` hold only the four integer flags; `metadata` holds only the four safe keys above.
`sanitizeMetadata` still redacts `SENSITIVE_KEYS` as a backstop. Per `KYC-PII-READINESS.md`, KYC
documents live under filename-obscurity only and must never appear in logs — F17 never logs them.

## KYC-reset branch caveat

The reset branch (`!$request->kv` → set `kv=0`, delete `type=='file'` KYC files via
`fileManager()->removeFile()`, null `kyc_data`) **pre-existed F17** and is unchanged except for the
`$filesRemoved++` counter. The audit records only the `files_removed` **count** and the `kv_reset`
boolean. **The DB transaction does not — and cannot — restore deleted KYC files:** deletion happens
before `save()` and is irreversible (direct `@unlink`); the transaction only guarantees that the DB
`save()` and its audit row co-commit. The file deletion is intentionally left outside the transaction
(matching the pre-F17 behavior; wrapping it would not make it reversible and would hold the lock across
`unlink`).

## Fail-open logger caveat

`logMutation` is **fail-open** (by design, F10): if the audit write throws, it is caught and routed to a
file channel, and the surrounding transaction still commits the profile change — the admin action is
never broken by an audit failure. Consequently F17 **does not guarantee** that every profile update has a
durable DB audit row; it guarantees that a successful update *attempts* an audit write and, on the normal
path, co-commits it. Do not overclaim guaranteed audit persistence.

## Failure paths write no audit row

- **404** — `findOrFail($id)` is the first statement; a missing account aborts before any write.
- **Validation failure** — `firstname`/`lastname`/`email`/`country` rules throw before the `before`
  snapshot and the transaction.
- **Mobile-duplicate** — the existing `if ($exists) return back()` early-returns before the snapshot and
  the transaction.

All three are covered by tests asserting `count('account.profile.updated') === 0`.

## Relationship to the F10 generic row

The F10 `AdminAuditLog` middleware still logs its generic `admin.request.mutation` row for the update POST
(entity inferred as guard + id, no before/after, no body). The F17 semantic row is **complementary** (adds
the named event + flag before/after + changed-field names + KYC-reset signals), not a duplicate. Tests
assert specifically on `action='account.profile.updated'`.

## Tests

`tests/Feature/AccountProfileUpdateAuditTest.php` (DB-backed on `marketcapx_test`, `DatabaseTransactions`
rolls back): 8 tests / 33 assertions — happy path + audit row; changed-field-names + flag-ints-only;
PII-value exclusion (sentinel assertions); KYC-reset signals without paths/filenames; validation→no row;
404→no row; mobile-duplicate→no row; agent/merchant `entity_type` coverage. Targeted regression re-run
green: F16 KYC decision, F15 account status, F14/F14b support ticket, AdminAuditLogger/AdminAuditLog/
AdminRequest audit (32 tests / 424 assertions).

## Status after F17

Account-profile semantic audit wired for all three guards. Route count **798** unchanged. `rbac.enforce`
remains **false** (allow-by-default; no deny-by-default). No behavior change to the update flow, no PII /
KYC files / KYC paths / `kyc_data` in the audit, no routes/migrations/real-DB mutation, no
money/wallet/gateway/IPN/webhook/API behavior touched. Full-suite total recorded in the F17 session
report.
