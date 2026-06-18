# Material Design System (Google Standards) — Migration

Full-app UI modernization to **Material Design 3**, brand-mapped, light + dark with a
persisted toggle. Delivered as a **plain-CSS "Material skin" over the existing
Bootstrap / jQuery markup** (no build step, no framework swap) so the frozen
money / auth / admin / checkout DOM is never restructured. Every phase is
**presentation-only** and **area-gated** (route- or layout-scoped) so a phase's CSS
can only affect its own area.

## Phase status (M0–M6 complete and merged to `master`)

| Phase | Branch | Scope | Merge commit | Status |
| ----- | ------ | ----- | ------------ | ------ |
| **M0** | `frontend/material-foundation` | Token + component layer, runtime JS, brand→M3 mapping, layout wiring (additive, inert) | (foundation, pre-M1) | **merged** |
| **M1** | `frontend/material-public-site` | Public marketing re-skin + header light/dark toggle | `78ed321` | **merged** |
| **M2** | `frontend/material-auth` | Auth & onboarding (login/register/forgot/reset/code-verify) | `c61e6f1` | **merged** |
| **M3** | `frontend/material-dashboards` | User + merchant + agent dashboard shells | `ac775b2` | **merged** |
| **M4** | `frontend/material-admin` | Admin panel visual layer (theme-independent) | `863fb89` | **merged** |
| **M5** | `frontend/material-sensitive-ui-review` | Checkout / payment-sensitive UI (safe polish; PCI forms frozen) | `6aed1a9` | **merged** |
| **M6** | `frontend/material-final-qa` | Consolidation, accessibility audit, this doc, final QA | (this branch) | **in progress** |

> Note: earlier drafts of this table used different phase/branch names (admin as
> M5, checkout as M6, dashboards split across M3/M4). The rows above are the
> **as-shipped** program. `work/escrow-atomicity` is an unrelated backend branch
> and was never touched by any Material phase.

## Material asset inventory + route/layout gating (the source of truth)

Foundation (loaded globally, inert until adopted):
- `assets/templates/basic/css/material/material-tokens.css` — `--md-sys-*` tokens (colour roles light `:root` + dark `[data-md-theme="dark"]`, state opacities, elevation 0–5, shape scale, motion, type scale, icon base). **Declares variables only.**
- `assets/templates/basic/css/material/material-components.css` — `.md-*` component classes (inert until an element opts in).
- `assets/templates/basic/js/material.js` — vanilla runtime: persisted theme toggle (`window.MaterialUI.toggleTheme()`), press ripples, nav drawer. Acts only on `[data-md-*]`/`.md-*` hooks.
- `assets/templates/basic/css/color.php` — emits `--ds-color-*` and `--md-sys-color-primary(+rgb)/on-primary` from the admin brand (`gs('base_color')`); container/surface tiers derive via `color-mix()`. Loaded on public/auth/dashboards/checkout-via-app; **NOT in admin** (admin uses the token fallback `#336699`).

Per-area skins (each loads ONLY in its area — verified by in-process render greps):

| Skin file | Linked from | Gating condition | Loads on | Never on |
| --------- | ----------- | ---------------- | -------- | -------- |
| `css/material/material-public.css` | `layouts/frontend.blade.php` `@push('style')` | `request()->routeIs('home','blogs','blog.details','contact','pages','policy.pages','cookie.policy')` | public marketing | auth, dashboards, admin, checkout |
| `css/material/material-auth.css` | `layouts/frontend.blade.php` `@push('style')` | `request()->routeIs('user.login','user.register','user.password.request','user.password.reset','user.password.code.verify')` | auth/onboarding | public, dashboards, admin, checkout |
| `css/material/material-dashboard.css` | `layouts/{user,merchant,agent}_master.blade.php` `@push('style')` | layout-gated (the 3 dashboard masters) | user/merchant/agent dashboards | public, auth, admin, checkout |
| `assets/admin/css/material-admin.css` | `admin/layouts/master.blade.php` `@stack('style')` | layout-gated (admin master) | admin panel | everything else |
| `css/material/material-sensitive.css` | `layouts/checkout_master.blade.php` (head) **and** `layouts/app.blade.php` route-gated | hosted checkout layout; + `routeIs('user.deposit.*','agent.deposit.*','merchant.deposit.*','user.payment','user.payment.done','invoice.payment','user.invoice.payment.*','deposit.app.confirm')` | checkout + in-app payment pages | public, auth, dashboard-home, admin |

The header light/dark toggle button (`[data-md-theme-toggle]`) is in
`partials/header.blade.php`, route-gated to the same marketing routes as M1. Auth
pages share the toggle's persisted scheme (cookie `md_theme` / localStorage
`md-theme`, default **dark**) but show no toggle button of their own.

## Design decisions worth remembering

- **M4 admin is theme-independent.** The admin panel is a mature LIGHT theme
  (`#4634ff` accent, white cards, static `assets/admin/css/app.css`) with 80+
  forms and many third-party widgets (select2, datepickers, nicEdit, charts).
  `material-admin.css` adds only Material **shape/elevation/motion/focus** + chip
  badges + neutral state layers — it does **not** repaint surfaces onto the
  dark/light `--md-sys` roles (that would risk widespread contrast regressions).
  Full admin dark/light token theming is **intentionally deferred**.
- **M5 sensitive is theme-independent too**, for the same reason (hosted checkout
  has a merchant-configurable `.checkout-wrapper--dark` variant + merchant base
  CSS). It adds shape/elevation/focus/chips on `.checkout-section`/`.add-money-card`
  only. **PCI card-entry forms (`payment/Authorize|Checkout|Stripe|NMI`) and the
  auto-submit `payment/redirect` are frozen** — given no payment-specific styling.
- **Justified `!important`** appears only where `color.php` paints an element with
  `!important` and an AA contrast fix required winning the cascade: M2 auth links
  (→ 6.71/7.09:1) and M3 active sidebar nav (→ 8.92:1). M4 and M5 use **zero
  `!important`** (fully additive).

## Inviolable rule (every authed / financial / admin / checkout / gateway view)

Presentation-only. Material CSS wraps **around** existing controls; it never
changes a form's `action`/`method`/`@csrf`/hidden inputs/route, any field `name=`
(incl. `amount`, `currency`, `wallet_id`, `method_code`, gateway `track`, card
fields), button names/values, validation, or the JS hooks the app relies on
(`id="form"`, `.form--control`, AJAX selectors, `<x-captcha/>`). No
backend/financial/gateway/auth/KYC logic is touched. Every phase's diff is a new
CSS file + additive Blade `<link>`/`@if` lines — **no form view is ever edited**.

## Verification commands (run from repo root unless noted)

- Tests: `cd core && APP_ENV=testing php -d extension=gmp vendor/phpunit/phpunit/phpunit` → **42 passed / 1 skipped**.
- Route boot: `cd core && APP_ENV=testing php -d extension=gmp artisan route:list` → **796 routes**.
- CSS brace balance: count `{` vs `}` per material CSS file (must be equal).
- Blade lint: `php -l <changed .blade.php>`.
- Per-phase gate also runs: `git status --short`, `git diff --check`, forbidden-file scan, structural form-contract proof (`git diff <base>..HEAD` must add no `<form>/<input>/name=/action=/method=/hidden/csrf/amount/currency/gateway/wallet/reference/button-value`), and a route-gating render check (each skin loads only in its area).

## Local run + QA method (non-obvious — see also the local-run-procedure note)

- `php artisan serve` does NOT work (basePath is `core/`). The app's env loader
  reads `core/.env` (production); process-env overrides do not reliably reach a
  background `php -S`. For smoke/render, **boot the app in-process from a CLI
  script** that `require`s `core/vendor/autoload.php` + `core/bootstrap/app.php`
  and dispatches `Illuminate\Http\Request::create($uri)` through the HTTP kernel,
  run as `cd core && APP_ENV=local php -d extension=gmp -d variables_order=EGPCS <script>`.
  This loads a **gitignored `core/.env.local`** (a copy of `.env` with DB→`root`,
  `APP_ENV=local`) so it hits the local `paytabz_DB` as root. **Delete
  `core/.env.local` afterward.**
- Visual QA uses **static harness HTML** (real CSS files + dummy data) served by a
  plain `php -S 127.0.0.1:1000 -t <root>`, measured in a browser. This is because
  the local DB has **0 user/merchant/agent/admin accounts**, so authenticated
  dashboard/admin pages cannot be rendered live, and **creating an account would
  mutate `paytabz_DB` (prohibited)**. Screenshots are written **outside the repo**
  (`%TEMP%`). Asset cache: skins use a static `?v=` query, so bust the browser
  cache with a unique `?cb=` when re-measuring.

## Intentionally deferred / out of scope

- Full admin dark/light **token** theming (M4 kept theme-independent on purpose).
- **Live authenticated** dashboard/admin screenshots (blocked by the no-account /
  no-DB-mutation rule; static harness used instead).
- `work/escrow-atomicity` — separate backend branch, untouched by the Material program.
- Self-hosting the Material Symbols icon font (currently Google Fonts CDN).
- Production deployment of any merged Material phase (no deploy performed).

## How to add a future Material phase safely

1. Branch from `master`; create one new `material-*.css` under the area's asset dir.
2. Link it **area-gated** only (route `@if` or a layout that's unique to the area);
   never global. Load it **after** the area's base CSS so the Material layer wins.
3. Style **existing classes** with `var(--md-sys-*)` tokens — do not edit any Blade
   form markup. Prefer theme-independent shape/elevation/focus on high-risk areas.
4. Gate every slice: PHPUnit 42/1, route:list 796, in-process smoke, structural
   form-contract proof, render-grep that the skin loads only in its area, static
   harness visual QA (1440/1024/768/375, dark+light, focus, no overflow, AA contrast).
5. Commit + push per slice; merge `--no-ff` only after a green gate + CTO approval.
