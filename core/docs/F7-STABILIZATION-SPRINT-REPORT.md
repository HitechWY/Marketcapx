# F7 — Stabilization / Security / Bundle Sprint — Report

Hardening sprint after the F1–F6 Inertia migration. No feature work; no backend/money/admin-mutation
logic changed. All phases used branches + `--no-ff` merges (no direct-to-master commits).

## Baseline → final
- Start: `master == origin/master == 9da33ad`, route 798, suite 112/769/1, build/tsc/composer green.
- Final: `master == origin/master == <F7d merge>`; working tree clean.
- Route count: **798** throughout. Suite: 112 → **127** (+15), 1 pre-existing skip.

## Phases / commits
| Phase | Type | branch | merge |
|------|------|--------|-------|
| F7 plans | docs | `docs/f7-plans` | `22c7e43` |
| F7a props security audit | docs+test | `hardening/inertia-security-props-audit` | `055bfd4` |
| F7b regression smoke suite | tests | `hardening/inertia-regression-smoke-suite` | `d877401` |
| F7c chart/bundle optimization | code+docs | `hardening/chart-bundle-optimization` | `da89f72` |
| F7d completion map | docs | `docs/migration-completion-map` | (this) |

## F7a — Inertia props security audit
Inventoried every Inertia response F1–F6 + shared props + trait mappers. **Verdict: CLEAN** — no
password/remember_token/2FA-secret/api-secret/gateway-credential/raw-KYC-path/raw-storage-path/
cross-actor data exposed; files server-resolved; `v-html` only on admin/server/framework content.
No prop removed (nothing unsafe). Deliverables: `INERTIA-PROPS-SECURITY-AUDIT.md` +
`InertiaPropExposureTest` (3).

## F7b — Regression smoke suite (+12 tests)
`InertiaMigrationSmokeTest` (public + user/merchant/agent/admin pages: guest redirect + correct
component when authed + F4 auth recovery), `SensitiveRoutesRemainBladeTest` (admin login Blade;
merchant api-key + money forms have no Inertia page; money Blade fallbacks retained),
`ZiggyExposureTest` (public Ziggy group free of panel/money/sensitive routes; route count pinned 798).

## F7c — Chart/bundle optimization
`AdminChart` made a `defineAsyncComponent` dynamic import in the admin dashboard → chart.js/
vue-chartjs split into an admin-dashboard-only async chunk. Main `app.js` **578 → 394 kB**
(gzip 188 → 124); new `AdminChart` chunk 186 kB loaded only for admins. **Vite chunk-size warning
resolved.** Behavior-preserving (charts render identically; data/endpoints/calculations unchanged);
`AdminDashboardTest` green. Deliverable: `CHART-BUNDLE-OPTIMIZATION-NOTES.md`.

## F7d — Completion map
`INERTIA-MIGRATION-COMPLETION-MAP.md` — single source of truth for Inertia vs Blade vs frozen across
public/user/merchant/agent/admin/auth, money/admin/KYC/gateway freeze lists, retained Blade
fallbacks, and the recommended order for remaining high-risk work.

## Safety confirmations
- No payment/wallet/KYC/gateway/IPN/webhook/checkout/escrow/admin-mutation logic changed.
- No secrets exposed; `core/.env` never read/edited/staged; no env/secret files committed.
- No DB schema/migrations; tests `DatabaseTransactions` (rollback; no persistent rows; no
  money/gateway/approval endpoints called).
- All Blade fallbacks retained; route count 798.
- No direct-to-master commits (F6b deviation not repeated); no force-push/history rewrite.

## Remaining risks / next
- Admin mutations + RBAC remain the largest deferred surface (needs endpoint extraction + role
  enforcement before migration). Login/register need an in-SPA captcha strategy. Money forms remain
  frozen (highest risk). chart glob could later go fully lazy (`eager:false`) app-wide if desired.
- Recommended next: RBAC + admin mutation endpoints (see completion map order).
