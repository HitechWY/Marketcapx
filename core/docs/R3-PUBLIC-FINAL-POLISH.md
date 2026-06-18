# R3 — Public Final Polish

Branch: `frontend/rebuild-public-final-polish` (off master `ab37b70`).
Presentation-only. Closes out the public-site rebuild after R1b/R2.

## Change

- New component **`x-ui.section-heading`** (`components/ui/section-heading.blade.php`)
  — DRYs the repeated centered `.section-header` block. It emits the EXACT existing
  theme classes (`.section-header.text-center`, `.section-subtitle.border-left`,
  `.section-title`, `.mt-3`; `text-white` via the `light` prop; `wow`/`data-wow-*`
  pass through `$attributes`), so main.css + the overlay CSS layers style it
  identically.
- Adopted in `sections/service` (light), `sections/why_choose_us`, `sections/blog`,
  and `blogs.blade.php` (the `/announces` listing header).

## Proven visually inert

Rendered `.section-header` fragments before vs after are byte-identical except a
single insignificant inter-block whitespace character (`</p> </div>` → `</p></div>`);
with whitespace-between-tags removed they are identical. No class, attribute, text,
or `wow` timing changed.

## Intentionally NOT changed (frozen / already done)

- `sections/testimonial` header — uses per-child `wow` delays (different shape), left
  as-is.
- `sections/about`, `sections/business`, `contact` headers — inline (not the centered
  `.section-header` shape); left as-is to avoid markup change for no DRY gain.
- Brand + testimonial slick sliders — markup frozen (JS-critical); no CSS change
  needed (already polished in M/R1b/R2).
- a11y/contrast/reduced-motion — re-verified clean (no gap), no new CSS.

## Verification

`php -l` clean · CSS braces 78/78 (material-app.css unchanged) · PHPUnit 42/1 ·
route:list 796 · smoke `/ /contact /blogs /announces /user/login /admin checkout` 200,
dashboards 302 · no legacy `.section-header` literals left in the 4 converted files
(component emits them) · forms untouched (contact/subscribe frozen) · Playwright:
4 `.section-header` + 9 `.section-title` render, `.brand-slider`/`.content-slider`
`slick-initialized`, 0 console errors, 0px overflow @1440 & 360.

## Next

R4 auth-component presentation (separate branch). Public rebuild is complete.
