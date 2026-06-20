# F2a — User Dashboard (Inertia) — Implementation Record

Status: **complete, gates green, merged.** First authenticated (web-guard) page migrated to
Inertia/Vue: the user dashboard (`GET /user/dashboard`). Read-only. Blade fallback retained.
Builds the shared auth/flash/nav plumbing that F2b/F2c reuse.

## Scope
- Migrated: `user.home` (`SiteController` n/a — `User\UserController@home`) → `inertia('User/Dashboard')`.
- Everything else (transactions, wallets, deposit history, profile, KYC, 2FA, and ALL
  money-movement) remains Blade. No money form/route/contract touched.

## Backend changes
- `app/Http/Middleware/HandleInertiaRequests.php` — added **lazy** shared props (evaluate only on
  Inertia responses; null/empty for guests + Blade): `auth.user` (curated web-guard subset),
  `flash.notify` (session notify tuples), `modules` (USER `ModuleSetting` booleans), `nav`
  (server-resolved primary/actions/account links — money links are resolved URLs here, NOT Ziggy),
  `locale`.
- `app/Http/Controllers/User/UserController.php` — `home()` returns `inertia('User/Dashboard', …)`
  with the SAME read-only data (wallets/totalBalance/insight/kyc/histories), amounts pre-formatted
  via `showAmount()`, dates via `showDateTime()`, text via `__()`. **Blade `view(...)` kept as a
  one-line commented rollback.** No new queries, no writes.
- `resources/views/app.blade.php` — added the Material Symbols icon font link (used by the Vue Icon).

## Frontend
- `Layouts/UserLayout.vue` (topbar + sidebar + content slot + flash toasts; responsive drawer).
- `Components/User/{UserTopbar,UserSidebar,UserNavItem,WalletCard,KycStatusCard,InsightCards,TransactionTable}.vue`.
- `Components/Ui/{Icon,FlashToasts}.vue`; `composables/useFlash.ts`.
- `Pages/User/Dashboard.vue`; `types/user.ts`.
- Reuses the F0/F1 token bridge + `useTheme`. No new npm packages. Charts deferred (InsightCards
  shows numeric money-in/out with a day filter that refetches `user.check.insight`).

## Security / data exposure
`auth.user` exposes only `{id, username, fullname, email, image, kv, ts}` — never password/
remember_token/ver_code/balance/kyc_data (all `$hidden`). No admin/merchant/agent data; no gateway/
API secrets. Money route NAMES stay out of client Ziggy (nav uses server-resolved URL strings);
`@routes('public')` unchanged (Dashboard uses passed URLs, no client `route()` for user routes).

## Tests (tests/Feature/UserDashboardTest.php — DatabaseTransactions, rollback only)
- Guest `GET /user/dashboard` → 302 to login.
- Authenticated (`CreatesMoneyFixtures::makeUser()` + `actingAs`) → 200, Inertia `User/Dashboard`
  with wallets/totalBalance/insight/histories/kyc props + `auth.user.username` shared correctly.
- License middleware (`Laramin\Utility\Utility`/`GoToCore`) + status/registration gates disabled
  **in-test only** (orthogonal to the view-layer migration; `auth` left on for the guest test).

## Verification
build ✅ · vue-tsc ✅ · PHPUnit **59/258/1** (+2, no regressions) · route:list **798 (unchanged)** ·
guest smoke 302 ✅ · public/Blade pages unchanged ✅ · Ziggy 12 public / 0 high-risk ✅ ·
visual QA (Playwright, fixture user in a rolled-back transaction → static-served render): desktop
dark + mobile, glyphs render, auth user in topbar, no horizontal overflow, 0 console errors.

## Rollback
Restore the commented `view('Template::user.dashboard', …)` return in `home()`. Shared-prop
additions are additive lazy closures (remove to revert). Blade view + assets untouched.

## Next
F2b — user wallets / transactions / deposit-history (read-only lists + filters + pagination),
reusing UserLayout + TransactionTable + Pagination.
