# R2 — Componentization + Remaining Legacy UI Sweep

Branch: `frontend/rebuild-component-sweep` (off master `5e3ed89`, R1b merge).
Goal: finish componentizing the **public** frontend and sweep remaining legacy UI
patterns, presentation-only. No backend/route/controller/model/form-contract change;
no Laravel upgrade; no DB mutation. Auth/dashboard/admin/checkout are **out of
structural scope** (visual inventory only).

## Legacy UI inventory (public templates)

Scope = public templates only: `partials/{header,footer,breadcrumb,banner}`,
`sections/*`, `home/contact/blogs/blog_details/pages/policy/cookie`. (`btn--base`
also appears across `user/`, `agent/`, `merchant/`, `payment/` — those are
auth/dashboard/checkout and are intentionally NOT touched in R2.)

| Public area | Legacy pattern | JS hook? | R2 action |
|---|---|---|---|
| `sections/overview` | `.overview-single` stat tiles | **none** (static `{{ counter_digit }}`) | rebuild → `x-ui.stat` |
| `sections/business` | `.coutomer-single` logo grid | **none** | rebuild → `x-ui.brand-logo-card` |
| `sections/testimonial` | `.single-slide` content slides | **slick** (`.thumb-slider`+`.content-slider` asNavFor) | wrap inner content → `x-ui.testimonial-card`, keep `.single-slide` as slick child |
| `sections/brands` | `.brand-slider` `.brand-item` | **slick** (`.brand-slider`) | **FROZEN** — JS-critical carousel, decorative logos |
| `sections/subscribe` | newsletter form | n/a | **FROZEN** — form contract |
| `sections/{service,feature,why_choose_us,blog,about}` | — | — | already componentized in R1b |
| `partials/{header,breadcrumb,banner,footer}` | — | — | already componentized in R1b |
| `.section-header/.section-title/.section-subtitle` | heading wrappers | — | keep (semantic, styled by main.css + overlays) |

### JS hooks that MUST be preserved (verified in `assets/templates/basic/js/app.js`)
- `.brand-slider` → slick (each direct `.single-slide` = one slide).
- `.testimonial-slide-area .thumb-slider` + `.content-slider` → paired slick
  (`asNavFor`); each direct `.single-slide` = one slide.
- `a[data-rel^=lightcase]` → lightcase lightbox (hero + overview video buttons).
- `.wow` → WOW.js scroll reveal.
The overview counter has **no** JS init (confirmed: no counterUp/odometer/countTo).

## Components added (Slice C)

- `x-ui.stat` — number + label stat tile (overview).
- `x-ui.brand-logo-card` — logo chip surface (business).
- `x-ui.testimonial-card` — quote + author block (testimonial content slides).

All presentation-only: no DB queries, no routes/controllers/models, no business logic.

## Frozen sections (documented)

- `sections/brands` — slick carousel of tiny logos; structural change risks the
  slider; low UI value → frozen (visual polish via CSS only, if any).
- `sections/subscribe` — newsletter form contract (`.subscription-form`,
  `input[name=email]`, `route('subscribe')` AJAX) byte-frozen.
- Testimonial thumb-image slider structure kept; only the content slide *inner*
  markup is wrapped.

## Per-slice gate

`git status` clean · `git diff --check` · forbidden-file scan · `php -l` ·
CSS brace balance · `node --check` if JS changed · PHPUnit (42/1) · `route:list`
(796) · in-process smoke (/ /contact /blogs /announces blog-detail policy
/user/login /admin checkout = 200; dashboards 302) · contact + subscribe contracts
unchanged. Screenshots kept outside the repo; `core/.env.local` untracked.

## Slices delivered

- **B** `9c8e60b` inventory + plan (doc).
- **C/D/E** `99b051b` `x-ui.stat`/`brand-logo-card`/`testimonial-card`;
  overview/business rebuilt; testimonial content wrapped (slick child kept).
- **E** `dd6304c` cookie-consent Allow button → `<x-ui.button>` (`.policy` hook kept).

No JS file was changed in R2 — only Blade markup that preserves every JS hook.

## Verification results (branch HEAD)

- **CRITICAL (sliders still work):** with real JS in a browser, `.brand-slider`,
  `.testimonial-slide-area .thumb-slider` and `.content-slider` all report
  `slick-initialized`; the testimonial-card quote renders inside the active slide;
  **0 console errors.** The `.single-slide` slick children were never restructured.
- PHPUnit **42 passed / 1 skipped**; `route:list` **796**; smoke (/ /contact /blogs
  /announces /user/login /admin checkout = 200; dashboards 302).
- Rendered DOM: 0 legacy `overview-single`/`coutomer-single`; the only remaining
  public `btn--base` are the two frozen form submit buttons (contact, subscribe).
- diff --check clean; forbidden scan = frontend-only; `core/.env.local` untracked.

## Visual QA results

- **0px horizontal overflow** on home at **1440 / 1024 / 768 / 360**.
- **Light + dark** both verified: dark-theme stat values render white on the
  overview overlay (AAA), testimonial text near-white (#e3e2e6) on dark (AA+),
  brand-logo chips use a deliberate light surface so client logos stay legible on
  the dark theme; focus state-layer active; every new transition has a
  `prefers-reduced-motion` guard.
- Screenshots (home 375, 1440 light, 1440 dark) kept outside the repo.

## Next phase recommendation

R3 candidates (all still presentation-only, separate branch, CTO-gated): (a) a
shared `x-ui.section`/`x-ui.section-heading` to DRY the `.section-header` blocks;
(b) optional CSS-only polish of the frozen brand/testimonial sliders without
markup change; (c) a separate, explicitly-scoped effort for auth/dashboard/admin
componentization (out of the public scope of R1b/R2). Backend, Laravel upgrade and
money-domain remain frozen and out of scope.
