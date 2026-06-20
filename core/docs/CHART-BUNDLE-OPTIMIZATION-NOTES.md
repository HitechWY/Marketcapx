# Chart / Bundle Optimization Notes (F7c)

## Problem
F6a added `chart.js` + `vue-chartjs` for the admin dashboard. `resources/js/app.ts` resolves
Inertia pages with `import.meta.glob('./Pages/**/*.vue', { eager: true })` — i.e. **one eager
bundle for the whole app** — so chart.js was pulled into the single main chunk, pushing it past
Vite's 500 kB warning threshold.

## Fix (behavior-preserving, chart-scoped)
`AdminChart.vue` is used by exactly one page (`Pages/Admin/Dashboard.vue`). Changed that page to
import it lazily:

```ts
const AdminChart = defineAsyncComponent(() => import('@/Components/Admin/AdminChart.vue'));
```

Vite now emits `AdminChart-*.js` (containing `AdminChart` + `chart.js` + `vue-chartjs`) as a
**separate async chunk fetched only when the admin dashboard mounts**. No template change; charts
render identically. Dashboard data, the `admin.chart.*` JSON endpoints, chart calculations, and all
backend logic are unchanged. The page glob stays eager (no app-wide loading change).

## Result (production build)
| Chunk | Before | After |
|------|--------|-------|
| `app-*.js` (main, loaded everywhere) | **578.3 kB** (gzip 188.5) | **394.4 kB** (gzip 123.9) |
| `AdminChart-*.js` (admin dashboard only, async) | — | 186.5 kB (gzip 65.2) |

- Main bundle shrunk ~184 kB (~64 kB gzip) for every non-admin-dashboard visitor.
- chart.js is no longer downloaded by public/user/merchant/agent visitors.
- **Vite chunk-size warning resolved** (main chunk < 500 kB).
- `vue-tsc` clean; `AdminDashboardTest` green.

## Not done (intentionally)
- No `manualChunks` config change (the async import already isolates chart.js cleanly).
- No switch of the page glob to lazy (`eager:false`) — that is an app-wide loading change beyond
  chart scope; deferred as a possible future optimization.
- No chart library swap, no chart removal, no data/endpoint changes.
