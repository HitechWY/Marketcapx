# R4 — Auth Presentation Components

Branch: `frontend/rebuild-auth-components` (off master `59f6689`).
Presentation-only rebuild of the **user-guard** auth card shell. All auth form
contracts are byte-frozen.

## Change

- New component **`x-ui.auth-card`** (`components/ui/auth-card.blade.php`) — emits the
  EXACT existing user-auth shell (`.account-wrapper > .left.bg_img + .right > .inner`)
  that `material-auth.css` styles, with the image as a prop and everything inside
  `.inner` passed through the slot.
- Adopted in the 4 user-auth views that share this shell: `user/auth/login`,
  `user/auth/register`, `user/auth/passwords/email` (forgot), `user/auth/passwords/reset`.
- Only the shell wrapper (open/close) was swapped; the heading, social-login include,
  the **form**, and footer links inside `.inner` were moved into the slot untouched.

## Forms FROZEN (proven)

`git diff master..HEAD` over `user/auth/**` shows **zero** form-contract lines changed
(no `<form>`, `@csrf`, `name=`, `<x-captcha>`, `action=`, `method=`, `<input>`,
`<select>`, `onsubmit`, `.disableSubmission`, `.verify-gcaptcha`, `.checkUser`).
Runtime DOM confirms unchanged field-name sets + csrf:
- login `[username, password]`; register `[accountRadioCheck, company_name, firstname,
  lastname, email, password, password_confirmation, agree]` (+ 2 account-type chips +
  `#existModalCenter`); forgot `[value]`. All JS hooks preserved.

## Intentionally NOT changed (documented)

- `user/auth/passwords/code_verify` — different shell (`.verification-code-wrapper`,
  6-box `#verification-code`); left as-is.
- **Agent + merchant auth** (login/register/reset/code, authorization 2fa/email/sms,
  ban) — different shell (`.account-section > .left > .left-inner + .right`) on the
  `common_auth` layout, NOT covered by material-auth.css. Componentizing them needs a
  separate component + (optional) Material CSS coverage → deferred to a future pass.
  These views are unchanged in R4.

## Verification

`php -l` clean · PHPUnit 42/1 · route:list 796 · smoke `/ /contact /blogs /announces
/user/login /user/register /user/password/reset /admin checkout` 200, dashboards 302 ·
diff proves forms frozen · Playwright: login + register render the Material account
card (`.account-wrapper` + `.left.bg_img`), 0px overflow @1440 & 375, csrf + full
field sets present, 0 console errors. Screenshot kept outside the repo.

## Next

R5 dashboard components (separate branch; expected to stop at merge-readiness — no
authenticated QA possible, sensitive-form proximity). Agent/merchant auth deferred.
