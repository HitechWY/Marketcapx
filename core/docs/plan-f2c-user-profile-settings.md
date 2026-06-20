# F2c — User Profile & Low-Risk Settings (Plan)

**Branch:** `frontend/inertia-user-profile-settings` (from master after F2b)

## Scope
Migrate low-risk account pages; forms = **native HTML POST**, contract-identical. No auth/2FA/
password-hashing/security business logic changed.
- `user.profile.setting` (GET + POST `submitProfile`) — profile update (firstname/lastname/address/
  state/zip/city/image multipart)
- `user.change.password` (GET + POST `submitPassword`) — current/new/confirm
- `user.twofactor` (GET) — **status display + native enable/disable POST** (`twofactor.enable`/`disable`,
  field `code` + `key` for enable). 2FA logic UNCHANGED.
- `user.qr.code` (GET) — read-only QR display + JPG download link.

## Contracts preserved (exact)
Each form posts the same action/method/field names + `_token`; `enctype=multipart/form-data` for the
profile image; validation errors via Inertia shared `errors`/redirect-back; flash via `notify`. POST
handlers (`submitProfile`/`submitPassword`/`create2fa`/`disable2fa`) **untouched**.

## Controller methods (GET only → inertia; Blade kept as rollback)
`ProfileController@profile`, `ProfileController@changePassword`, `UserController@show2faForm`,
`UserController@qrCode` → `inertia(...)`. POST handlers unchanged.

## Vue files
- `Pages/User/{Profile,ChangePassword,TwoFactor,QrCode}.vue`
- `Components/User/{ProfileForm,PasswordForm,SecurityStatusCard}.vue`; reuse Ui/Button/Card.
- Extend `types/user.ts`.

## Tests (DatabaseTransactions, rollback)
- guest → 302 login each.
- authed → Inertia component renders.
- profile/password/2FA **markup-contract** asserted on the rendered DOM (method/action/`_token`/field
  names/file input) via Playwright DOM eval (server HTML is data-page only) OR via a posted
  validation-failure test (missing fields → errors, no mutation).
- success path only inside DatabaseTransactions (profile update) — rolled back; password/2FA success
  NOT run against DB unless trivially safe under rollback.

## Gates / auto-merge / rollback / stop
Standard gates. Auto-merge if green, contract preserved, no auth/security logic change, no persistent
mutation. Rollback per method. **Stop & leave Blade** if any form contract is unclear (esp. 2FA
enable/disable or password) — document instead of forcing.
