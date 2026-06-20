# F7c — Bundle / Chart Optimization (Plan)

Address the Vite chunk-size warning introduced by chart.js/vue-chartjs (F6a), behavior-preserving.

## Approach (preferred)
Lazy-load the chart layer so chart.js lands in an admin-only async chunk fetched only when the admin
dashboard mounts. Options: `defineAsyncComponent(() => import('@/Components/Admin/AdminChart.vue'))`
in `Pages/Admin/Dashboard.vue`, and/or a Vite `manualChunks` entry isolating `chart.js`/`vue-chartjs`.
Do NOT change dashboard data, chart endpoints, chart calculations, or remove charts; do not add another
chart library.

## Guardrails
Admin dashboard must still render all charts (AdminDashboardTest stays green). If lazy-loading risks
the render or types, prefer `manualChunks` (pure build-config split) or document-and-defer.

## Deliverables
- Safe code-splitting patch OR docs-only deferral.
- `core/docs/CHART-BUNDLE-OPTIMIZATION-NOTES.md` (before/after chunk sizes, decision).

Gates standard; build/tsc/tests + AdminDashboardTest green; route count 798.
