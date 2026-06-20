# F2d — User KYC Read-Only / Status (Plan)

**Branch:** `frontend/inertia-user-kyc-readonly` (from master after F2c)

## Scope
Migrate the **read-only** KYC status/info page only. The KYC **upload form** (dynamic Form +
document upload, `kyc.submit`) stays Blade unless its full contract is mapped + tested.
- `user.kyc.data` (`/kyc-data`) — `UserController@kycData` — read-only submitted-KYC display
  (keeps `abort_if($user->kv == VERIFIED, 403)`).

## Decision rule
- **A (preferred):** migrate `kyc.data` (read-only) to Inertia.
- **B (kyc.form/kyc.submit):** the upload form is a mutation (dynamic Form fields + file upload via
  FormProcessor → KYC submit). **Leave Blade** this sprint; document the contract for a future phase.
  Do NOT change upload paths or expose raw document storage paths.

## Data exposure
Render submitted KYC fields (label/type/value) the user already sees. File-type values link via the
existing user attachment download route only (no raw storage paths). No admin KYC logic touched.

## Controller (read-only only)
`UserController@kycData` → `inertia('User/KycInfo', { fields, pageTitle })`; Blade kept as rollback.
`kycForm()`/`kycSubmit()` UNCHANGED (stay Blade).

## Vue files
`Pages/User/KycInfo.vue`; reuse Card/UserLayout. Extend `types/user.ts`.

## Tests (DatabaseTransactions, rollback)
- guest → 302 login.
- authed unverified user → Inertia `User/KycInfo`.
- verified user (kv=VERIFIED) → 403 preserved.
- no raw document storage path in props; no KYC mutation.

## Gates / auto-merge / rollback / stop
Standard gates. Auto-merge if read-only-only + green (or docs-only audit + green). Rollback: restore
commented `view()`. Stop if migrating would require touching the upload/FormProcessor mutation path.
