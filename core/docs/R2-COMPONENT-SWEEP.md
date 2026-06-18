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
