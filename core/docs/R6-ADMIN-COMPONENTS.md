# R6 — Admin Components (ASSESSMENT → DEFERRED, awaiting CTO)

Branch: `frontend/rebuild-admin-components` (off master `d3b5ed6`).
Outcome: **no admin markup change** — admin componentization is deferred. This file
records why, per the sprint's "unsure about a sensitive flow → stop" rule.

## Findings (read-only inventory)

1. **Admin is already component-based.** The theme ships a full component library used
   throughout admin: `x-widget` + `x-widget-1..7` (stat cards), `x-confirmation-modal`,
   `x-user-confirmation-modal`, `x-search-form`, `x-search-date-field`,
   `x-search-key-field`, `x-select-currency`, `x-select-user-type`, `x-viser-form`,
   `x-generated-form`, `x-image-uploader`, `x-edit-withdraw`, `x-back`. **59 of 123**
   admin views already use `<x-*>` components (e.g. the admin dashboard stat cards are
   `<x-widget>`).
2. **Status chips are model-driven.** Admin status badges render via model accessors
   (`$withdrawal->statusBadge`, `$deposit->statusBadge`, …), not raw Blade markup —
   componentizing them would require touching models (forbidden).
3. **Admin is deliberately theme-independent.** M4 added `assets/admin/css/material-admin.css`
   (shape/elevation/motion/focus only, 0 `!important`, keeps the #4634ff light theme).
   Introducing the public `.md-*` component surfaces into admin would clash with the
   established admin look.
4. **Unrenderable + form-dense.** The DB has **0 admin accounts**, so admin pages can't
   be rendered/QA'd by login (creating one = forbidden DB mutation). ~80 of the views
   carry high-risk action forms (approve/reject, balance add/sub with hidden `act`,
   ban/unban, gateway/settings/KYC, CMS builders) whose contracts must stay frozen.

## Decision

No safe, verifiable, high-value admin componentization remains:
- duplicating the existing theme components adds no value,
- the remaining markup is either sensitive forms (frozen) or model-driven badges
  (would touch models),
- any markup edit is unverifiable without admin authentication and sits next to
  money/permission forms.

Therefore R6 makes **no admin code change** and is presented for CTO direction. The
admin panel already meets the rebuild bar (component-based + M4 Material polish).

## Recommendation

Treat admin componentization as **complete for this sprint** (already componentized +
polished). If deeper admin work is wanted later, it should be a dedicated, separately
scoped effort with: a seeded admin test account (controlled, outside `paytabz_DB`),
authenticated QA, and an explicit per-form freeze proof — not part of a no-account
frontend sprint.

## Verification

No code change → PHPUnit 42/1, route:list 796, smoke unchanged (public 200, dashboards
302, `/admin` 200 login). `core/.env.local` untracked; no artifacts.
