# R1b — Full Public Structural Rebuild

Branch: `frontend/rebuild-public` (off master `ec50a7b`, the R1 foundation merge).
Goal: a **real structural rebuild** of the public website — replace legacy theme
Blade markup with the new Material Blade component library (`components/ui/*`), not
another CSS overlay. Presentation only; backend, routes, CMS bindings and form
contracts are preserved.

## Why old UI persisted after R0–M6 + R1

The public layout had accumulated four CSS overlay layers
(`public-redesign.css`, `material/material-public.css`, `material/material-auth.css`,
`material/material-app.css`) on top of the original 5,190-line `main.css`. The
**markup still used the original theme classes** (`.service-card`, `.feaure-card`,
`.choose-card`, `.blog-card`, `.hero .btn--base`, `.footer-contact-card`, …) which
`main.css` styles. The overlays only re-skinned those classes, so the underlying
legacy structure remained. R1b replaces the markup itself with real components.

## Active public template map (template = `basic`)

Layout: `layouts/frontend.blade.php` → extends `layouts/app.blade.php`
(app.blade.php loads `material-tokens.css` + `material-components.css` globally;
frontend.blade.php adds `public-redesign`, route-gated `material-public`/`material-auth`,
and `material-app.css`).

| Area | File | Rebuild slice |
|------|------|---------------|
| Header / top bar / nav | `partials/header.blade.php` | B |
| Breadcrumb | `partials/breadcrumb.blade.php` | B |
| Footer (DONE in R1 pilot) | `partials/footer.blade.php` | — |
| Hero / banner | `partials/banner.blade.php` | C |
| Service section | `sections/service.blade.php` | C |
| Feature section | `sections/feature.blade.php` | C |
| Why-choose-us section | `sections/why_choose_us.blade.php` | D |
| About section | `sections/about.blade.php` | D |
| Overview (stats) | `sections/overview.blade.php` | D |
| Business (client logos) | `sections/business.blade.php` | D |
| Blog preview section | `sections/blog.blade.php` | D |
| Testimonial (slider) | `sections/testimonial.blade.php` | freeze* |
| Brands (slider) | `sections/brands.blade.php` | freeze* |
| Subscribe (form) | `sections/subscribe.blade.php` | FROZEN |
| Blog list | `blogs.blade.php` | E |
| Blog detail | `blog_details.blade.php` | E |
| CMS pages / policy | `pages.blade.php`, `policy.blade.php` | E |
| Contact | `contact.blade.php` | E (info tiles only; form FROZEN) |

\* Slider sections keep their JS-init markup hooks (`.thumb-slider`,
`.content-slider`, `.brand-slider`, `.single-slide`) intact — restyle via CSS only,
no structural change that would break the slider init.

## Component contract

- `<x-ui.button variant=filled|tonal|outlined|text|elevated [href] [type] [icon]>` →
  `.md-button`. Pass-through `$attributes` so existing classes/`type=submit` survive.
- `<x-ui.card variant=elevated|filled|outlined [flush]>` → `.md-card` (+ `.md-card__body`).
- `<x-ui.badge tone=success|warning|danger|info|primary>` → `.md-chip`.
- New `x-ui.*` components only if justified; styled in `material/material-app.css`.

## FROZEN form contracts (must not change)

Only two public forms exist. No public search form exists.

1. **Contact** (`contact.blade.php`): `<form method="post">` (+ default action),
   `@csrf`, inputs `name`/`email`/`subject`/`message`, `<x-captcha />`, submit button,
   `required`/`readonly` rules. Baseline md5 of the form block frozen; only the
   left-column contact-info presentation is rebuilt.
2. **Subscribe** (`sections/subscribe.blade.php`): `<form class="subscription-form" method="POST">`,
   `@csrf`, `input[name=email]`, AJAX to `route('subscribe')`. The JS selects
   `.subscription-form`, `.input--group`, `input[name=email]` — **this file is left
   byte-identical** (md5 `1e2c39d1cbda12d548d82dbd9b20e417`).

Freeze rule: action, method, input `name`s, hidden fields, CSRF, captcha, route names,
validation behaviour and submit behaviour are never touched.

## Per-slice gate

`git status` clean · `git diff --check` · forbidden-file scan · `php -l` changed Blade ·
CSS brace balance · PHPUnit (42/1) · `route:list` (796) · in-process smoke
(/ /contact /blogs /user/login /user/register /admin 200; dashboards 302) ·
contact + subscribe form signatures unchanged. Screenshots kept outside the repo;
`core/.env.local` stays untracked.
