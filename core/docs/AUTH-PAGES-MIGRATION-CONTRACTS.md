# Auth Pages — Migration Contracts (F4a)

**Status:** docs-only. No code changed in this phase.
**Goal:** capture the EXACT current form contract for every auth GET page (user, merchant,
agent, admin) so an Inertia/Vue port posts byte-identically to the unchanged controllers,
and classify each page by migration risk.

Migration approach for any page we do migrate: **native HTML `POST`** (full-page submit) to the
existing route, with `_token` (CSRF) preserved, action URL + token passed as server props (no
Ziggy), validation errors surfaced via Inertia's shared `errors`, flash via shared `flash.notify`.
Controllers, guards, captcha, and reset-token logic are **NOT** changed.

---

## Captcha analysis (decisive for risk)

`<x-captcha>` → `resources/views/partials/captcha.blade.php`. It renders **whichever is enabled**:
- **Google reCAPTCHA** (`loadReCaptcha()`): emits the reCAPTCHA widget **and** `@push('script')` a
  jQuery handler (`.verify-gcaptcha` submit hook reading `grecaptcha.getResponse()`) plus a
  `window.verifyCaptcha()` callback. Posts `g-recaptcha-response`. **Depends on the external
  grecaptcha script + jQuery running on the page.**
- **Custom image captcha** (`loadCustomCaptcha()`): emits an inline image + `<input name="captcha">`;
  the answer lives in the session and is checked by `verifyCaptcha()` server-side.

Reproducing reCAPTCHA inside an Inertia/Vue SPA page means re-implementing its script bootstrap and
submit hook — that is **changing captcha behavior**, which is a hard-stop for this sprint. Therefore
**every form that renders `<x-captcha>` is DEFERRED** (login + register, all guards; admin login).

Forms that render **no captcha** (forgot-password / code-verify / reset-password) are safe to migrate.

---

## Risk classification

| Page (all guards unless noted) | Captcha | Heavy JS | Decision |
|---|---|---|---|
| Login | ✅ x-captcha | low | 🟡 DEFER (captcha) |
| Register | ✅ x-captcha | high (country→mobile_code/country_code, async checkUser, pw-strength, account toggle) | 🟡 DEFER (captcha + JS) |
| Forgot password (email request) | ❌ | none | 🟢 MIGRATE (F4b/F4c) |
| Code verify | ❌ | minor (code field formatting) | 🟢 MIGRATE (F4b/F4c) |
| Reset password | ❌ | none | 🟢 MIGRATE (F4b/F4c) |
| Admin login | ✅ x-captcha | low | 🟡 DEFER → F4d docs-only |

> Migrating only the captcha-free password-recovery trio keeps the sprint inside its safety rules
> while still moving real auth GET pages onto Inertia. Login/register/admin-login stay Blade.

---

## USER (web guard) — `routes/user.php`, controllers `App\Http\Controllers\User\Auth\*`

### Login — 🟡 DEFER
- GET `user.login` `/login` → `LoginController@showLoginForm`; POST `/login` (`throttle:login`) → `LoginController@login`
- View `user/auth/login.blade.php`; inputs: `_token`, `username`, `password`; **`<x-captcha>`**
- POST rules: `username`|`email` required|string, `password` required|string. Success → `user.home`; fail → back+notify. Middleware: `guest`.

### Register — 🟡 DEFER
- GET `user.register` `/register`; POST `/register` (`registration.status`,`throttle:registration`)
- View `user/auth/register.blade.php`; inputs: `_token`, `accountRadioCheck` (personal|company), `company_name` (sometimes), `referBy` (readonly, if session `reference`), `firstname`, `lastname`, `email`, `password`, `password_confirmation`, `agree` (if `gs('agree')`); **`<x-captcha>`**
- Async: POST `user.checkUser` (`/check-user`) email-exists check. Rules: firstname/lastname required, email required|email|unique:users, password required|confirmed(+secure_password rules), company_name sometimes|max:60.

### Forgot password (email request) — 🟢 MIGRATE
- GET `user.password.request` `/password/reset` → `ForgotPasswordController@showLinkRequestForm`
- POST `user.password.email` `/password/email` (`throttle:password-reset`) → `sendResetCodeEmail`
- View `user/auth/passwords/email.blade.php`; inputs: `_token`, **`value`** (username or email). No captcha in view.
- Rules: `value` required. Success → redirect `user.password.code.verify` + notify; fail → back+notify.

### Code verify — 🟢 MIGRATE
- GET `user.password.code.verify` `/password/code-verify` → `codeVerify`
- POST `user.password.verify.code` `/password/verify-code` (`throttle:password-reset`) → `verifyCode`
- View `user/auth/passwords/code_verify.blade.php`; inputs: `_token`, **`email`** (hidden), **`code`** (partial `partials/verification_code`). No captcha.
- Rules: `code` required, `email` required (spaces stripped from code). Success → `user.password.reset` (code param) + notify; fail → back to `user.password.request`.

### Reset password — 🟢 MIGRATE
- GET `user.password.reset` `/password/reset/{token}` → `ResetPasswordController@showResetForm`
- POST `user.password.update` `/password/reset` → `reset`
- View `user/auth/passwords/reset.blade.php`; inputs: `_token`, **`email`** (hidden), **`token`** (hidden), `password`, `password_confirmation`. No captcha.
- Rules: token required, email required|email, password required|confirmed(+secure_password). Success → `user.login` + notify; fail → back to `user.login`.

---

## MERCHANT — `routes/merchant.php`, `App\Http\Controllers\Merchant\Auth\*`
Mirrors USER, prefix `merchant.`, guard `merchant`, guest middleware `merchant.guest`.
- Login `merchant.login` `/` 🟡 (x-captcha); Register `merchant.register` `/register` 🟡 (x-captcha + country/mobile_code/country_code hidden + async `merchant.checkUser`; username `alpha_num|min:6|unique:merchants`, mobile stored as `mobile_code.mobile`).
- 🟢 Forgot `merchant.password.request` `/password/reset` (POST `merchant.password.email` `/password/email`), input `value`.
- 🟢 Code verify `merchant.password.code.verify` `/password/code-verify` (POST `merchant.password.verify.code` `/password/verify-code`), inputs `email`(hidden)+`code`. (Code-field JS auto-format present.)
- 🟢 Reset `merchant.password.reset` `/password/reset/{token}` (POST `merchant.password.update` `/password/reset`), inputs `email`(hidden)+`token`(hidden)+`password`+`password_confirmation`.

## AGENT — `routes/agent.php`, `App\Http\Controllers\Agent\Auth\*`
Identical shape to MERCHANT, prefix `agent.`, guard `agent`, guest middleware `agent.guest`.
- Login `agent.login` `/` 🟡; Register `agent.register` `/register` 🟡 (same country/mobile/async as merchant, `unique:agents`).
- 🟢 Forgot `agent.password.request`; Code verify `agent.password.code.verify`; Reset `agent.password.reset` — same inputs as merchant.

## ADMIN (login only) — `routes/admin.php`, `App\Http\Controllers\Admin\Auth\LoginController`
- GET `admin.login` `/` ; POST `/` (`throttle:login`) ; View `resources/views/admin/auth/login.blade.php`; inputs `_token`,`username`,`password`; **`<x-captcha>`**; guard `admin`, `admin.guest`. 🟡 DEFER → F4d produces docs-only (captcha + admin surface out of read-only scope).

---

## Shared concerns for the migrated (🟢) pages
1. **Native POST**: each Vue page renders a real `<form method="POST" :action>` with a hidden `_token`. Action URLs + CSRF token come from server props (no Ziggy, no client route table).
2. **Errors**: Inertia shares `errors` (Laravel `$errors`) automatically; pages read `$page.props.errors`. Flash via the existing shared `flash.notify`.
3. **Reset token**: keep `{token}` in the GET URL → pass through as a prop into the hidden `token` field; do not change `ResetPasswordController` token logic. The hidden `email` carries the address as today.
4. **Verification code partial**: reproduce `partials/verification_code` markup as a Vue field posting `code` (single field; server strips spaces). Preserve `email` hidden field.
5. **Layout**: a lightweight guest/auth Vue layout (centered card) — does NOT use the authenticated PanelShell. Public Inertia infra already exists.
6. **Blade fallbacks** for every migrated page are retained (one-line rollback comment in the controller method).

## Stop conditions honored
Login/register/admin-login are NOT migrated because doing so would require reproducing captcha
(reCAPTCHA script + submit hook) — a captcha-logic change. No guard, reset-token, or captcha
server logic is altered by the 🟢 migrations.
