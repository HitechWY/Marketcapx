# R7 — Final UI Rebuild QA & Documentation

Branch: `frontend/rebuild-final-ui-qa` (off master `d3b5ed6`). Verification + docs only;
no functional change. Consolidates the frontend rebuild program (R0/M-phases → R6).

## Component library (`core/resources/views/components/ui/`)

`button`, `card`, `badge` (R1 foundation) · `feature-card`, `blog-card` (R1b) ·
`stat`, `brand-logo-card`, `testimonial-card` (R2) · `section-heading` (R3) ·
`auth-card` (R4) · `widget` (R5, on its branch — not yet on master).
All presentation-only; built on the M0 `.md-*` classes + `--md-sys-*` tokens.

## CSS layers & route/layout gating (verified — no leaks)

Verified by rendering representative pages and checking which `material-*.css` +
component classes load where:

| Page | app | public | auth | dashboard | sensitive | admin | redesign | public md-* |
|------|-----|--------|------|-----------|-----------|-------|----------|-------------|
| home (public) | ✓ | ✓ | – | – | – | – | ✓ | yes |
| contact (public) | ✓ | ✓ | – | – | – | – | ✓ | yes (cards) |
| user/login (auth) | ✓ | – | ✓ | – | – | – | ✓ | footer only |
| /admin | – | – | – | – | – | ✓ | – | **none** |
| checkout | – | – | – | – | ✓ | – | – | **none** |

- `material-app.css` (component layer) loads on the public+auth frontend layout; it is
  component-scoped (inert except where `.md-*` render).
- `material-public` is correctly OFF auth; `material-auth` only on user auth.
- **Admin and checkout are fully isolated** — zero public/auth component classes leak in.
- Dashboards (user/merchant/agent) load `material-dashboard.css` via their masters
  (302 unauthenticated — not renderable without accounts).

## Frozen form contracts (confirmed)

- Public: `subscribe` md5 `1e2c39d1cbda12d548d82dbd9b20e417` (unchanged since R1b);
  `contact` fields `[_token,name,email,subject,message]` intact.
- Auth (R4): user login/register/forgot/reset forms byte-frozen (git-diff proven).
- Dashboard/admin money/KYC/config/approval forms: untouched (R5 changed only the
  read-only wallet widget on its branch; R6 made no admin change).

## Sliders / counters (frozen JS, verified working)

`.brand-slider`, testimonial `.thumb-slider`/`.content-slider` slick init confirmed
(`slick-initialized`, 0 console errors) after R2's testimonial-card wrap; overview
counter is static (no JS). lightcase + WOW preserved.

## Program status (branches)

| Phase | Branch | Status |
|-------|--------|--------|
| R2 | rebuild-component-sweep | merged (PR #3 `e58dd80`) + R2-G doc `ab37b70` |
| R3 | rebuild-public-final-polish | **merged** `59f6689` |
| R4 | rebuild-auth-components | **merged** `d3b5ed6` |
| R5 | rebuild-dashboard-components | merge-readiness (awaiting CTO authenticated test) |
| R6 | rebuild-admin-components | deferred (admin already componentized; no change) |
| R7 | rebuild-final-ui-qa | this branch |

## Verification (this branch)

PHPUnit 42/1 · route:list 796 · smoke: `/ /contact /blogs /announces blog-detail
policy /user/login /user/register /user/password/reset /admin checkout` = 200,
dashboards 302 · CSS-gating matrix above · forms frozen · `core/.env.local` untracked,
no artifacts.

## Recommendation: ready for backend hardening / Laravel-upgrade planning?

The **public** frontend is fully rebuilt (real component markup, not CSS overlay) and
**auth** is componentized with frozen contracts — both merged and green. Dashboard
(R5) is one CTO authenticated test away; admin (R6) is already component-based +
polished. The frontend is in a clean, stable, well-documented state. **Yes** — the
program can now move to backend hardening / Laravel-upgrade *planning* as a separate,
explicitly-scoped effort (the money/backend domain has stayed frozen and untouched
throughout, so it is unaffected and ready to be picked up independently).
