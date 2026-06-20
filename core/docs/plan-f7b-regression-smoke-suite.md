# F7b — Regression Smoke Suite (Plan)

Add broad regression tests to protect the F1–F6 migration. Tests only; no app changes.

## Coverage
- Public pages mount expected Inertia components (`/`, `/contact`).
- User/merchant/agent/admin migrated pages: guest → redirect; authenticated → correct Inertia component.
- F4 auth recovery pages render (forgot/code-verify/reset) for user/merchant/agent.
- Sensitive pages intentionally still Blade (admin login, merchant api-key, money forms) — assert NOT Inertia.
- Route count remains 798.
- No sensitive route names in the client Ziggy `public` group.

## Rules
Testing DB only; `DatabaseTransactions` (rollback); no persistent mutation; never call money-mutation /
gateway / IPN / webhook / admin-approval endpoints (GET pages + redirects only).

## Deliverables
- `tests/Feature/InertiaMigrationSmokeTest.php`
- `tests/Feature/SensitiveRoutesRemainBladeTest.php`
- `tests/Feature/ZiggyExposureTest.php`

Gates standard; route count 798.
