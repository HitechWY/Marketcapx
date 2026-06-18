# MarketCap X — Design System (Phase 1 foundation)

Branch: `frontend/design-system`. This is the **foundation** commit of the staged
frontend modernization (see the CTO audit). It is **presentation-only and
additive** — it introduces design tokens and documents the component inventory
**without rewriting any page** and **without any visual change**.

## What this phase delivers
1. **Design tokens** — `assets/templates/basic/css/design-system.css`: CSS custom
   properties (`--ds-*`) for color, spacing, typography, radii, shadows, motion,
   z-index, and layout. Variable declarations only; no selectors target existing
   elements, so loading the file changes nothing visually.
2. **Live brand tokens** — `assets/templates/basic/css/color.php` additionally
   emits `:root{ --ds-color-primary; --ds-color-secondary; }` from the
   admin-configured `gs('base_color')`, so `--ds-color-primary` tracks the running
   theme (color.php loads after design-system.css and overrides the static
   fallback). This is an additive `:root` block; the existing `!important` color
   rules are unchanged.
3. **Wiring** — `design-system.css` is linked once in
   `core/resources/views/templates/basic/layouts/app.blade.php` (after
   `custom.css`, before `@stack('style')`). All five main layouts
   (`frontend`, `user_master`, `agent_master`, `merchant_master`, `common_auth`)
   `@extends('Template::layouts.app')`, so tokens are available across the public
   site, all three dashboards, and auth screens from one include.

## Token reference (summary)
- **Color:** `--ds-color-primary` / `--ds-color-secondary` (live from base color;
  fallback `#336699`), neutral scale `--ds-color-gray-50…900` + white/black,
  semantics `--ds-color-{success,danger,warning,info}`, surfaces
  `--ds-color-{body-bg,surface,text,text-muted,border}`.
- **Spacing:** `--ds-space-0…8` on a 4px base (4/8/12/16/24/32/48/64px).
- **Typography:** `--ds-font-family-base` (`inherit` — keeps the theme webfont),
  `--ds-font-size-xs…2xl`, `--ds-font-weight-*`, `--ds-line-height-*`.
- **Radii / shadows / motion / z-index / layout:** `--ds-radius-*`,
  `--ds-shadow-{sm,md,lg}`, `--ds-transition-{fast,base}`, `--ds-z-*`,
  `--ds-container-max`. Breakpoints documented as comments (CSS vars can't be
  used inside `@media`).

## Component inventory (presentation surface)
Theme root: `core/resources/views/templates/basic/`.
- **Layouts:** `layouts/{app,frontend,user_master,agent_master,merchant_master,common_auth,checkout_master}.blade.php`.
- **Public site:** `sections/*` (about, feature, service, blog, testimonials…),
  `partials/{header,footer,banner,breadcrumb,auth_header,auth_footer}.blade.php`,
  `pages.blade.php`.
- **Dashboards:** `user/`, `agent/`, `merchant/` (dashboard, wallets, profile,
  sidenav/topbar partials).
- **Shared widgets:** cards (`.d-widget`, `.quick-link-card`), buttons
  (`.btn--base`), sidebar menu (`.sidebar-menu__*`), pagination, forms
  (`.account-form`, Bootstrap form-controls), header menus.
- **Global vendor assets:** `assets/global/{css,js}` (Bootstrap 5, jQuery,
  FontAwesome, Line Awesome, select2, slick, lightcase). Static — **no Vite/Mix
  build**; assets are linked via `asset()` / `activeTemplate(true)`.

## Usage guidelines (for later phases)
- Consume tokens instead of hard-coded values, e.g.
  `padding: var(--ds-space-4)`, `color: var(--ds-color-text)`,
  `border-radius: var(--ds-radius-md)`.
- Put net-new presentational rules in `custom.css` (or a phase-specific stylesheet
  linked after it); do not edit vendor/minified files in `assets/global/`.
- Prefer extending existing classes over renaming them — Blade markup references
  these class names widely.

## Freeze boundaries (do NOT touch during frontend work)
Per the CTO audit, presentation work must not alter financial logic or markup
contracts:
- No edits to controllers, `routes/*`, `Gateway/*/ProcessController`, money-flow
  `DB::transaction` bodies, KYC approval, `ModuleSetting`/boot, the license layer,
  or DB schema.
- In financial Blade **forms** (`payment/*`, `*/withdraw/*`, `*/kyc/*`,
  `user/escrow/*`, `user/operations/transfer_money`, `user/voucher/*`,
  `payment/deposit`) you may restyle surrounding layout/markup but must **not**
  change form field `name`s, hidden amount/charge values, `@csrf`, or `route()`
  targets.
- `checkout_master.blade.php` (payment checkout) and `core/resources/views/admin/**`
  are intentionally **out of scope** for this token wiring; revisit deliberately
  in a later phase.

## Verification
- Local run on `127.0.0.1:1000` (temp router; `artisan serve` does not work here).
- Confirm `design-system.css` and `color.php` load `200` and pages render
  identically (token declarations are inert until consumed).

## Roadmap (next phases — not in this commit)
- **Phase 2 — public site:** restyle `sections/*` + `partials/header|footer|banner`
  using tokens. **Phase 3 — dashboard UX:** `*_master` layouts, sidenav/topbar,
  `dashboard.css`. Both markup/CSS only, financial fields frozen.
