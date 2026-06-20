# F3 — Merchant & Agent Read-Only Migration (Plan)

Merchant/agent dashboards mirror the user dashboard; reuse `TransactionTable`, `WalletCard`,
`KycStatusCard`, `InsightCards`, `Pagination`, `FilterBar`. Add guard-aware shared props +
`MerchantLayout`/`AgentLayout`.

## Foundation (in F3a): guard-aware shared props
`HandleInertiaRequests` currently shares web-guard user only. Extend to resolve the active panel
guard via `userGuard()` (web/agent/merchant), and build `auth.user`, `modules`, and `nav` per guard
type (user/merchant/agent route prefixes). Lazy closures; null for guests/admin. No web-guard
behavior change for already-migrated user pages.

## F3a `frontend/inertia-merchant-dashboard-readonly`
`MerchantController@home` → `inertia('Merchant/Dashboard')` (wallets/totalBalance/insight/kyc/
histories — same shape as user). `MerchantLayout.vue` (reuses Topbar/Sidebar/FlashToasts). Blade
rollback kept. **No API key/secret data; api_key page stays Blade.**

## F3b `frontend/inertia-merchant-readonly-lists`
`MerchantController@trxHistory` (transactions, filters+pagination), `@wallets` → Inertia. Reuse
Transactions/Wallets pages pattern (Merchant/Transactions.vue, Merchant/Wallets.vue).

## F3c `frontend/inertia-agent-dashboard-readonly`
`AgentController@home` → `inertia('Agent/Dashboard')`. `AgentLayout.vue`. No money-in/out/commission forms.

## F3d `frontend/inertia-agent-readonly-lists`
`AgentController@trxHistory`, `@commissionLog`, `@depositHistory`, `@wallets` → Inertia (read-only,
paginated). Reuse TransactionTable/Pagination.

## F3e `frontend/inertia-merchant-agent-sensitive-audit` (docs-only)
`MERCHANT-SENSITIVE-FORMS-MIGRATION-AUDIT.md` + `AGENT-SENSITIVE-FORMS-MIGRATION-AUDIT.md`.

## Frozen (stay Blade): merchant api_key/generate.key (secrets), withdraw, profile/password/2FA/KYC
forms; agent money-in, deposit, withdraw, commission withdrawal, profile/password/2FA/KYC forms.

## Gates/rollback/auto-merge: standard sprint gates; per-method `view()` rollback comment;
auto-merge if green + only read-only changed + no secrets + Ziggy clean + route 798.
