# F1c-1 — Static / Policy / Pages / Cookie (Plan 1)

**Branch:** `frontend/inertia-public-static` (from `master` @ f572edf)

## Scope
Migrate read-only public pages to Inertia/Vue; keep Blade as fallback:
- `policy.pages` — `GET /policy/{slug}` → `SiteController@policyPages`
- `pages` — `GET /{slug}` (catch-all) → `SiteController@pages`
- `cookie.policy` — `GET /cookie-policy` → `SiteController@cookiePolicy`

## Routes affected
None added/removed/renamed. Only the **responses** of the three GET methods change
(`view(...)` → `inertia(...)`). Route count stays **798**.

## Controller methods affected (only these)
`policyPages()`, `pages()`, `cookiePolicy()` return `inertia(...)`. Original `view('Template::…')`
return kept directly above as a one-line commented rollback (F1b pattern). `cookiePolicy()` keeps its
`abort_if(... != ENABLE, 404)`. `pages()` reuses the existing `homeSection()` builder for `secs`.

## Vue pages/components to create
- `resources/js/Components/Public/SeoHead.vue` — `<Head>` title + description/keywords/OG meta (shared).
- `resources/js/Components/Public/ProseContent.vue` — themed `v-html` wrapper for CMS HTML bodies.
- `resources/js/Components/Public/SectionRenderer.vue` — extract Home.vue's key→component section map (Home refactored to use it; reused by Page/Contact).
- `resources/js/Pages/Public/PolicyPage.vue` — `SeoHead` + page-header + `ProseContent`.
- `resources/js/Pages/Public/Page.vue` — `SeoHead` + `SectionRenderer`.
- `resources/js/Pages/Public/CookiePolicy.vue` — `SeoHead` + page-header + `ProseContent`.
- Types in `resources/js/types/public.ts`.

## Data props
- PolicyPage: `{ seo, title, details(HTML) }`
- CookiePolicy: `{ seo, title, details(HTML) }`
- Page: `{ seo, sections: HomeSection[] }` (via `homePageData()`-style builder w/o banner)

## Fallback Blade paths (unchanged)
`templates/basic/{policy,pages,cookie}.blade.php` retained untouched.

## Rollback
Per method: restore the commented `view('Template::…')` return. Per branch: revert the branch.

## Tests / gates (all green to merge)
- `npm run build`, `vue-tsc --noEmit`, `composer validate`, PHP lint changed files.
- PHPUnit stays 53/216/1; `route:list` stays 798.
- Smoke: `/` Inertia; a real policy slug Inertia; a real page slug Inertia (if a safe one exists);
  `/cookie-policy` Inertia (or preserved 404 if disabled); `/contact`, `/announces`, `/user/login`,
  `/admin` still Blade; dashboards 302; `/poc/*` 200.
- Ziggy scan: 0 admin/ipn/api/merchant/agent/user-money/deposit/withdraw/gateway/kyc.
- No DB mutation; no form contract change.

## Auto-merge criteria
All gates green; only F1c-1 files changed; only the 3 GET responses swapped; Blade fallback intact;
no high-risk Ziggy exposure; no backend logic/route/schema/contract change.

## Stop conditions
Backend logic would need changing; a route/contract would change; 404 behavior can't be preserved;
Ziggy would expose sensitive routes; tests fail unfixably in scope; build/typecheck broad failure.
