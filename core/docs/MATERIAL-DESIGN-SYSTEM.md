# Material Design System (Google Standards) — Migration

Full-app UI migration to **Material Design 3**, brand-mapped, light + dark with a
persisted toggle. Delivered as a **plain-CSS "Material skin" over the existing
Bootstrap 5 / jQuery markup** (no build step, no framework swap) so the frozen
money/auth/admin DOM is never restructured. See the approved plan for the full
program; this doc tracks the system and per-phase status.

## Phase status

| Phase | Branch | Scope | Status |
| ----- | ------ | ----- | ------ |
| **M0** | `frontend/material-foundation` | Token + component layer, runtime JS, brand→M3 mapping, layout wiring. **Additive & visually inert.** | **in progress** |
| M1 | `frontend/material-public` | Public marketing re-skin | planned |
| M2 | `frontend/material-auth` | Auth (user/agent/merchant) | planned |
| M3 | `frontend/material-user` | User dashboard | planned |
| M4 | `frontend/material-agent-merchant` | Agent + merchant dashboards | planned |
| M5 | `frontend/material-admin` | Admin panel | planned |
| M6 | `frontend/material-checkout` | Checkout / deposit / gateways | planned |

## M0 — what shipped (all additive; nothing renders differently yet)

- `assets/templates/basic/css/material/material-tokens.css` — M3 `--md-sys-*`
  tokens: colour roles (light `:root` + dark `[data-md-theme="dark"]`),
  state-layer opacities, elevation 0–5, shape scale, motion easing/duration, the
  M3 type scale, and the Material Symbols icon base class. **Declares variables
  only** — styles no existing element.
- `assets/templates/basic/css/material/material-components.css` — component
  classes scoped to `.md-*` (buttons filled/tonal/outlined/text/elevated, icon
  button, FAB, cards, text fields, switch, chips, lists, top app bar, nav
  drawer/rail, data table, dialog/scrim, snackbar, tabs, ripple/state layer,
  elevation + typography utilities). Inert until an element uses a `.md-*` class.
- `assets/templates/basic/js/material.js` — vanilla runtime: persisted theme
  toggle (`window.MaterialUI.toggleTheme()`), press ripples, nav drawer. Acts
  only on `[data-md-*]` hooks and `.md-*` classes.
- `assets/templates/basic/css/color.php` — appended a `:root{--md-sys-color-primary…}`
  block: the admin brand (`gs('base_color')`) becomes M3 `primary`; `on-primary`
  flips white/near-black by luminance; container/surface tiers derive from
  `primary` via `color-mix()` in the token file, so the whole palette tracks the
  live brand.
- Layout wiring: `templates/basic/layouts/app.blade.php` (public + all
  dashboards + auth + checkout) and `admin/layouts/master.blade.php` load the
  token+component CSS, the Material Symbols font, and `material.js`, plus a tiny
  inline `<head>` script that applies the persisted `data-md-theme` before paint
  (no flash). Default scheme: **dark** (premium fintech identity); the toggle
  switches to light and persists the choice.

## How to adopt Material in a later phase

1. Replace area markup classes with `.md-*` components (e.g. a Bootstrap button →
   `<button class="md-button md-button--filled md-state">…`), or alias the area's
   legacy tokens to Material in that area's skin (`--ds-color-primary: var(--md-sys-color-primary)`).
2. Use `var(--md-sys-color-*)`, `var(--md-sys-elevation-*)`, `var(--md-sys-shape-corner-*)`.
3. Add a `[data-md-theme-toggle]` control to that area's chrome for the light/dark switch.

## Inviolable rule (every authed / financial / admin / checkout / gateway view)

Presentation-only. Material markup wraps **around** existing controls; it never
changes a form's `action`/`method`/`@csrf`/hidden inputs/route, any field `name=`
(incl. `item_name[]`/`amount[]`, gateway `track`, card fields), or the JS hooks
the app relies on (`id="form"`, `.form--control`, validation/AJAX selectors,
`<x-captcha/>`). No backend/financial/gateway/auth logic is touched.

## Notes

- Material Symbols is loaded from Google Fonts (the admin layout already uses a
  Google Fonts CDN). Self-hosting the icon font is an optional later hardening.
- No Vite/build step is involved; all CSS/JS is static, served via `asset()`.
