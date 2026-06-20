# Admin Login — Migration Assessment (F4d)

**Status:** docs-only. **Decision: NOT migrated — admin login stays Blade.**

## What was evaluated
- Route: GET `admin.login` `/` and POST `/` (`throttle:login`) → `App\Http\Controllers\Admin\Auth\LoginController` (`showLoginForm` / `login`), guard `admin`, middleware `admin.guest`.
- View: `resources/views/admin/auth/login.blade.php`; inputs `_token`, `username`, `password`; renders **`<x-captcha>`**.
- Controller `login()` calls `verifyCaptcha()` (returns "Invalid captcha provided" on failure).

## Why it stays Blade (hard-stop conditions hit)
1. **Captcha logic.** The page renders `<x-captcha>` (Google reCAPTCHA → external script + jQuery submit hook, or custom image captcha) and the controller enforces it. Porting to Inertia/Vue would require reproducing the captcha widget/script — a **captcha-logic change**, which is an explicit hard-stop for this sprint (same reason user/merchant/agent **login & register** were deferred in F4b/F4c).
2. **Admin surface is out of read-only scope.** F5 keeps the entire admin UI frozen this sprint (audit/plan only). Migrating the admin entry point ahead of that plan is out of scope.
3. **No safe, contract-proving path** without touching captcha/admin-auth behavior.

## Outcome
- Admin login Blade view + controller **unchanged**.
- No Inertia component created for admin login.
- Revisit only alongside the F5 admin migration plan, and only after a captcha rendering
  strategy is proven (note: `Pages/Public/Contact.vue` already passes captcha HTML as a prop —
  a possible future pattern, pending verification that reCAPTCHA initializes correctly in-SPA).

This matches the sprint instruction for F4d: "Otherwise produce docs-only audit and leave Blade."
