# F1a — Public Inertia PoC + Ziggy Scope

Status: **complete, gates green.** First real public-facing Inertia/Vue **preview**
foundation. **No production page was replaced** — every existing public Blade route
(`/`, `/contact`, `/announces`, `/blogs`, `/user/login`, `/admin`, checkout, …) is
unchanged and remains live. Builds on F0 ([INERTIA-VUE-FOUNDATION.md](INERTIA-VUE-FOUNDATION.md)).

## What was added

A modern Vue/Tailwind **public layout system** + UI primitives + one isolated preview
page, plus **Ziggy route-exposure scoping**. All additive; no backend logic touched.

New frontend files:
- `resources/js/Layouts/PublicLayout.vue` — themed shell (header + slot + footer).
- `resources/js/Components/Public/PublicHeader.vue` — sticky nav, theme toggle, mobile menu.
- `resources/js/Components/Public/PublicFooter.vue` — multi-column footer.
- `resources/js/Components/Ui/{Button,Card,SectionHeading}.vue` — MD3 UI primitives.
- `resources/js/composables/useTheme.ts` — light/dark toggle reusing the Blade theme mechanism (`data-md-theme`, `md_theme` cookie, `md-theme` localStorage).
- `resources/js/Pages/Public/HomePreview.vue` — the preview page (hero + stats + features + CTA).

Changed:
- `resources/css/app.css` — extended the MD3 token bridge (added secondary, on-surface-variant, surface-container-high, outline-variant).
- `resources/views/app.blade.php` — `@routes` → `@routes('public')` (scoped Ziggy).
- `routes/web.php` — added one preview route `GET /poc/public-home`.
- `vite.config.ts` — robust `@` alias via `fileURLToPath` (was a bare string).
- `config/ziggy.php` — **new**; defines the `public` route group (allow-list).

## Ziggy scoping decision

**Before:** `@routes` serialized the **entire** application route table (~hundreds of
routes incl. admin/user-money/gateway/IPN) into every Inertia page's source.

**After:** `config/ziggy.php` defines a **`public` allow-list group**, and the root view
uses **`@routes('public')`**. Only 12 guest-safe routes are exposed:

```
blog.details, blogs, contact, cookie.accept, cookie.policy, home,
lang, pages, poc.inertia, poc.public-home, policy.pages, subscribe
```

**Proof (in-process render of `/poc/public-home`):** Ziggy object contains exactly those
12 routes; a scan for `admin|ipn|merchant|agent|deposit|withdraw|gateway|kyc|api|user.`
patterns returns **NONE**. Route URIs are not secrets and protected routes stay
auth-gated regardless, but this trims the client payload and avoids advertising the full
surface — appropriate for a payment app.

Extensibility: later phases add their own named groups (or extend `public`) and call
`@routes('<group>')` from the relevant root view. Only public, guest-safe routes belong
in `public` — never admin/*, user money, gateway, KYC, `ipn.*`, or API routes.

## Preview route

- **`GET /poc/public-home`** (`poc.public-home`) — guest-accessible, **no DB**, **no
  business logic**. Props are `config('app.name')` + static content only.
- Proves: PublicLayout + header/footer render, hero/stats/features/CTA render, Tailwind +
  MD3 tokens + light/dark theme work, Inertia props flow (`brand`, `tagline`, `features`,
  `stats`), and the Vite manifest resolves (`/build/assets/*`).
- `/poc/inertia` (F0) is retained and still works.

## What remains Blade (unchanged & live)

Everything real: `/`, `/contact`, `/announces`/`/blogs`, `/user/login`, all auth,
dashboards, admin, checkout/payment, KYC, gateway. F1a replaces **nothing**.

## Migration strategy for F1b (public home replacement)

1. Wire **read-only** real CMS/site data into the preview: `gs()` (site name, logo,
   colors), and the existing frontend "sections" content the live home uses — **read
   only**, no controller used by a live page modified, no DB writes.
2. Build the remaining public sections as Vue components to reach visual parity with the
   live Blade home.
3. Flip `/` (or a feature-flagged route) to `Inertia::render('Public/Home', …)` **only
   after** visual QA + parity sign-off, keeping the Blade home view in place as instant
   fallback (revert the controller line).
4. Adopt Inertia `<Link>` for intra-Inertia navigation once ≥2 public Inertia pages exist.

## Rollback path

Fully additive. To revert F1a: remove the `poc.public-home` route, the new Vue/TS/CSS
files, `config/ziggy.php`, and restore `@routes` (unscoped) in `app.blade.php`. The legacy
Blade frontend is unaffected throughout. (Reverting just the Ziggy scope = change
`@routes('public')` back to `@routes`.)

## Verification results (F1a)

| Gate | Result |
|---|---|
| `npm run build` | ✅ 813 modules; manifest + assets at `<web-root>/build` |
| `vue-tsc --noEmit` | ✅ exit 0 |
| `composer validate` | ✅ valid |
| PHPUnit | ✅ 53 / 216 / 1 skipped — identical to baseline |
| `route:list` | ✅ 798 routes = 797 (F0) + 1 preview; no existing route changed |
| Smoke `/`, `/contact`, `/announces`, `/user/login`, `/admin` | ✅ 200, still Blade |
| Smoke `/poc/inertia` | ✅ 200, Inertia mount |
| Smoke `/poc/public-home` | ✅ 200, mounts `Public/HomePreview` + `/build` assets |
| Ziggy scope | ✅ 12 public routes only; 0 high-risk leaked |
| `git diff --check` / PHP lint | ✅ clean / no syntax errors |

## Remaining risks / notes

- **Visual QA not automated.** Functional correctness is proven via in-process SSR
  render. To view it: launch the documented dev server (APP_ENV=local, see the local-run
  procedure) and open `/poc/public-home`. Automated Playwright was skipped because the
  Bash-tool `php -S` reads prod `APP_URL`/DB (env-propagation gotcha), which breaks a
  localhost browser render.
- **Header/footer link to live Blade routes** via `<a>` (full page nav) — intentional for
  a preview. Inertia `<Link>` is adopted in F1b when multiple Inertia pages exist.
- **esbuild/vite dev-server advisory** (F0 note) still applies — dev-only, deferred.
