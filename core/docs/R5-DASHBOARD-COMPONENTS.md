# R5 — Dashboard Components (MERGE-READINESS — awaiting CTO)

Branch: `frontend/rebuild-dashboard-components` (off master `d3b5ed6`).
Presentation-only. **Not auto-merged** — see verification limitation below.

## Change

- New component **`x-ui.widget`** (`components/ui/widget.blade.php`) — read-only
  dashboard stat/wallet card emitting the EXACT existing markup
  (`.d-widget.curve--shape > .d-widget__content > i + h2.d-widget__amount`), with an
  optional `footer` slot for the existing nav link.
- Adopted in **`user/dashboard.blade.php`** wallet loop only: amount in the default
  slot, the transfer-money nav link (route + `module('transfer_money')` conditional)
  in the footer slot — both preserved verbatim.

## Frozen / untouched

- No money/KYC form touched. The diff over `user/dashboard.blade.php` changes **only**
  the wallet-widget markup — no `<form>`, `@csrf`, `name=`, `.money` insight dropdown,
  `.reason`/`#kycRejectionReason` modals, ApexCharts `#apex-line`, or accordion JS.
- Merchant/agent dashboards, sidenav/topbar, transaction tables, KYC notices — all
  unchanged in R5 (deferred until CTO validates the widget approach on user dashboard).

## Verification

- `php -l` clean · `git diff` shows no form/JS-hook lines · `php artisan view:cache`
  compiles ALL blades (incl. the edited dashboard) successfully · PHPUnit 42/1 ·
  route:list 796 · public smoke 200 · dashboards still **302 → login** (auth intact).
- **`Blade::render('<x-ui.widget>…</x-ui.widget>')`** confirms the component emits
  byte-equivalent `.d-widget` markup, including the no-footer (module-disabled) case.
- Visual: the rendered markup is byte-identical to the pre-existing widget that M3
  already static-harness QA'd, so the visual result is unchanged by construction.

## Verification LIMITATION (why this stops at merge-readiness)

The DB has **0 user/merchant/agent accounts** (dump is schema+settings only), so the
authenticated dashboard cannot be rendered by logging in, and creating an account is a
forbidden DB mutation. Verification therefore covers **Blade compile + isolated
component render + markup equivalence**, NOT a live logged-in dashboard page. Per the
sprint's "unsure about a sensitive flow → don't auto-merge" rule, R5 is presented for
**CTO authenticated testing** before merge.

## Recommendation

Low risk (read-only markup, byte-equivalent, no form/JS touched). Suggest CTO log into
a user dashboard on a staging copy, confirm the wallet cards + transfer links render
and work, then approve; merchant/agent adoption can follow in a fast follow-up.
