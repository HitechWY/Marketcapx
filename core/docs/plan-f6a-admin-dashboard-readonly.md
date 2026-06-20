# F6a — Admin Dashboard Read-Only (Plan, as shipped)

Migrate `AdminController@dashboard` to Inertia, read-only, reusing the panel infra.

## Foundation
- `HandleInertiaRequests`: ADMIN branch (gated on `auth('admin')->check()`) for `auth.user`
  (id/name/username/email/image, secret-free) + server-resolved `adminNav()`. `userGuard()`
  (shared backend helper) left unchanged.
- `Layouts/AdminLayout.vue` wraps the guard-agnostic `PanelShell` (reuses topbar/sidebar/flash).
- Charts: `chart.js` + `vue-chartjs` added to the Vite bundle.

## Page
`Admin/Dashboard.vue`: 12 metric cards (`$widget`), deposit/withdraw summary cards, login
browser/OS/country doughnuts (from props), and 4 time-series charts fetched client-side from the
UNCHANGED `admin.chart.*` JSON endpoints (date-range presets). `AdminMetricCard` + `AdminChart`.

## Rules / gates
No secrets/PII/raw KYC paths in props; no mutation links. Blade `admin.dashboard` kept as one-line
rollback. Admin login stays Blade. Test `AdminDashboardTest` (guest 302, admin Inertia, type=ADMIN,
no password/remember_token). Standard gates; route count 798.
