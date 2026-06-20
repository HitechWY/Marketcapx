# F1c-2 — Blogs List / Blog Detail (Plan 2)

**Branch:** `frontend/inertia-public-blog` (from `master` after Plan 1 merge)

## Scope
- `blogs` — `GET /announces` → `SiteController@blogs` (paginated, 20/page)
- `blog.details` — `GET /announce/{slug}` → `SiteController@blogDetails` (404 on miss)

## Routes affected
None added/removed/renamed. Only responses swap to `inertia(...)`. Route count stays **798**.

## Controller methods affected (only these)
`blogs()`, `blogDetails()` return `inertia(...)`; original `view(...)` kept as commented rollback.

## Paginator handling
`blogs()` keeps `Frontend::where('data_keys','blog.element')->latest()->paginate(getPaginate())`.
Pass the paginator to Inertia via `->through(fn ($b) => [...])` so each item is shaped
(`{title, image, excerpt, url, day, month, slug}`) while preserving Laravel's pagination meta
(`links`, `current_page`, `last_page`, `total`, `per_page`). `Pagination.vue` renders `links`
(label/url/active); Inertia visits `?page=N` (same route → returns Inertia page N). Excerpt =
`strLimit(strip_tags(description),200)`; `url = route('blog.details', slug)`.

## Blog detail handling
`blogDetails($slug)` keeps `firstOrFail()` (→ 404). Props: `blog{title, image(640x480),
description(HTML), date}`, `recentBlogs[8]{title, image(thumb_), url, date}`, `seo`, plus
`shareUrls{facebook,linkedin,twitter}` computed server-side from the current URL.
**FB comments deferred** (social share links only).

## Recent blogs handling
Keep `Frontend::where('id','!=',$blog->id)->where('data_keys','blog.element')->latest()->limit(8)->get()`
→ mapped to `{title, image(thumb_), url, date}`.

## SEO/meta handling
`SeoHead.vue` (from Plan 1): blog detail uses `$blog->data_values->title` + `seo_content`
(description/keywords/image via `frontendImage('blog', …, seo:true)`). Blogs list uses a basic title.

## Vue pages/components to create
- `resources/js/Pages/Public/Blogs.vue`, `resources/js/Pages/Public/BlogDetails.vue`
- `resources/js/Components/Public/BlogCard.vue`, `resources/js/Components/Public/Pagination.vue`
- Extend `resources/js/types/public.ts` (Blog, Paginator types).

## Fallback Blade paths (unchanged)
`templates/basic/{blogs,blog_details}.blade.php` retained untouched.

## Rollback
Per method: restore commented `view(...)`. Per branch: revert branch.

## Tests / gates
- build / typecheck / composer validate / PHP lint.
- PHPUnit 53/216/1; `route:list` 798.
- Smoke: `/` Inertia; `/announces` Inertia; one real blog detail Inertia; a missing blog slug → 404
  preserved; `/contact` still Blade; `/user/login`, `/admin` still Blade; dashboards 302.
- Pagination check if >1 page of data (else note single-page); verify `links`/`last_page`.
- Ziggy scan clean (0 high-risk). No DB mutation; no form contract change.

## Auto-merge criteria
All gates green; only F1c-2 files changed; only the 2 GET responses swapped; Blade fallback intact;
404 preserved; no high-risk Ziggy exposure; no backend logic/route/schema/contract change.

## Stop conditions
Pagination cannot preserve behavior; 404 behavior changes; backend logic would change; route/contract
change; Ziggy exposure; tests fail unfixably in scope.
