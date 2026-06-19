# F1b — Public Homepage Inertia Migration

Status: **complete, gates green.** The public homepage `/` now renders via Inertia/Vue,
using the **same backend CMS data** (read-only). The old Blade homepage is retained,
untouched, as an instant fallback. Builds on F0/F1a.

## Route / controller change

- **Route unchanged:** `GET /` → `SiteController@index` (name `home`). No route added/removed
  (route count stays **798**).
- **Controller:** `SiteController@index` now returns `inertia('Public/Home', [...])` instead of
  `view('Template::home', ...)`. The same query (`Page` slug `/`) and SEO content are used.
  A new protected `homePageData()` / `homeSection()` builds the props by **mirroring** each
  Blade partial's `getContent()` / `frontendImage()` / `__()` calls — no new queries, no DB
  writes, no business logic.

## Old Blade fallback + rollback

- **Fallback file (untouched):** `core/resources/views/templates/basic/home.blade.php` plus
  `partials/banner.blade.php` and `sections/*.blade.php` — all retained.
- **One-line rollback:** in `SiteController@index`, replace the `inertia(...)` return with the
  original (kept in a comment directly above it):
  ```php
  return view('Template::home', compact('pageTitle', 'sections', 'seoContents', 'seoImage'));
  ```

## Sections migrated (Vue)

Banner (hero) + the 10 CMS sections in the home page's `secs` order:
`brands, about, service, why_choose_us, feature, business, overview, testimonial, subscribe, blog`.

| CMS key | Vue component | Content/element data used |
|---|---|---|
| banner | `HeroSection.vue` | title, heading, subheading, button, video link, background_image |
| brands | `BrandsSection.vue` | heading; element images |
| about | `AboutSection.vue` | title, heading, short_details, button, experience_text; award logos |
| service | `ServiceSection.vue` | heading, subheading; element title, **service_icon (raw HTML)**, description |
| why_choose_us | `WhyChooseUsSection.vue` | heading, subheading; element title, short_details, icon |
| feature | `FeatureSection.vue` | heading, subheading; element title, short_details, icon |
| business | `BusinessSection.vue` | title, heading, subheading; client logos |
| overview | `StatsSection.vue` | heading, subheading; element counter_digit, title |
| testimonial | `TestimonialSection.vue` | title, heading, subheading; author, designation, quote, image |
| subscribe | `SubscribeSection.vue` | heading, subheading; **newsletter form** |
| blog | `BlogSection.vue` | title, heading, subheading; latest 3 posts (title, excerpt, image, url) |

Dispatcher: `Pages/Public/Home.vue` renders `HeroSection` then maps `home.sections` by key to
components. Uses the F1a `PublicLayout` (header/footer/theme). Shared types in `types/home.ts`.

`service_icon` is admin-authored HTML/SVG rendered via `v-html` — same trust model as the Blade
partial, which echoes it raw.

## Data props

```
seo:  { title, description, keywords, image }
home: { banner: {...} | null, sections: [ { key, content:{...}, elements:[...] } ] }
```
All text passes through `__()` server-side (localization preserved). Images resolved via
`frontendImage()` server-side. Verified against live data: banner heading
"The Ultimate Solution for e-Wallet Service", correct section order + element counts.

## SEO/meta

Title via Inertia `<Head :title>` (→ `… — MarketCap X`). `description`, `keywords`,
`og:title/description/image` emitted via `<Head>` meta tags. (The Blade `partials/seo` emits a
larger OG/Twitter set; parity can be extended in a later pass — the core tags are covered.)

## Forms / contract

One form on the homepage — **newsletter subscribe** (`SubscribeSection.vue`):
- **Backend unchanged:** `POST /subscribe` → `SiteController@subscribe` (verified via `route:list`).
- **Contract preserved:** posts field **`email`** to **`route('subscribe')`** with the CSRF token
  (`X-CSRF-TOKEN` header from the `<meta name="csrf-token">`), and handles the same JSON response
  shape `{ success, message }` / `{ error: [] }` — identical to the original jQuery AJAX in
  `sections/subscribe.blade.php`. Body is JSON instead of url-encoded; the controller reads via
  `$request->all()/->email`, which handles both. No backend route/validation/logic changed.
- **Not exercised against the DB** (a successful subscribe would insert a row); contract is
  preserved by construction (backend untouched + identical field/URL/CSRF from the client).

## Ziggy exposure

Unchanged from F1a — `@routes('public')`, the `public` group (12 routes). Scan on `/`: contains
**no** admin/ipn/api/merchant/agent/deposit/withdraw/gateway/kyc/user-money routes. No new routes
needed (home/blogs/contact/subscribe/blog.details already in the group).

## Visual QA

Rendered via in-process render (APP_ENV=local) → static-served build, driven by Playwright:
- **Desktop 1440 (light & dark):** premium fintech layout; all sections render real CMS data;
  theme toggle switches MD3 tokens correctly.
- **Mobile 390:** all sections stack; header collapses; no horizontal overflow (375 ≤ 390).
- **No horizontal overflow** at 1440 or 390; **no JS console errors** (only a benign
  `favicon.ico` 404 on the static preview host).
- **Note:** CMS images/icons 404 in the *local* preview (the image files aren't in the local
  `assets/`); they load on production. Layout/spacing are correct regardless.

## Verification results (F1b)

| Gate | Result |
|---|---|
| `npm run build` | ✅ 837 modules; manifest + assets at `<web-root>/build` |
| `vue-tsc --noEmit` | ✅ exit 0 |
| `composer validate` | ✅ valid |
| PHPUnit | ✅ 53 / 216 / 1 skipped — identical to baseline |
| `route:list` | ✅ 798 routes (unchanged); `/` and `subscribe` definitions intact |
| Smoke `/` | ✅ 200, Inertia `Public/Home` + `/build` assets |
| Smoke `/poc/inertia`, `/poc/public-home` | ✅ 200 |
| Smoke `/contact`, `/announces`, `/user/login`, `/admin` | ✅ 200, still Blade |
| Smoke dashboards | ✅ 302 unauthenticated |
| Ziggy scope on `/` | ✅ 12 public routes; 0 high-risk |
| `git diff --check` / PHP lint | ✅ clean / no syntax errors |

## Remaining risks / notes

- Local CMS images 404 (production-only assets) — visual parity of imagery to be confirmed on a
  real environment.
- SEO meta is a core subset of the Blade `seo` partial; extend OG/Twitter tags in a later pass if
  full parity is required.
- esbuild/vite dev-server advisory (F0) still deferred (dev-only).

## Rollback path

One-line controller revert (above). All Blade views/partials/assets retained; fully reversible.
