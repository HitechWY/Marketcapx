# Merchant Sensitive Forms — Migration Audit (F3e)

**Status:** docs-only. No code changed in this phase.
**Scope:** classify every merchant-panel form/action that mutates state, moves money, or
exposes secrets, and record the freeze decision for the non-money migration sprint.

Legend: 🟢 migrated (read-only, done) · 🟡 caution / deferred · 🔴 frozen (do not migrate this sprint).

## Already migrated (read-only — F3a/F3b)
| Page | Route | Component | Notes |
|------|-------|-----------|-------|
| Dashboard | `merchant.home` (GET) | `Merchant/Dashboard` | 🟢 read-only metrics/wallets/histories/KYC status |
| Transactions | `merchant.transactions` (GET `/transaction/history`) | `Merchant/Transactions` | 🟢 read-only paginator + filters |
| Wallets | `merchant.wallets` (GET) | `Merchant/Wallets` | 🟢 read-only balances |

## 🔴 Frozen — secrets exposure
| Form / action | Route | Controller | View | Why frozen |
|---------------|-------|-----------|------|-----------|
| API key page | `merchant.api.key` (GET) | `MerchantController@apiKey` | `merchant/api_key.blade.php` | View renders `merchant()->public_api_key` **and** `merchant()->secret_api_key` directly. Migrating to Inertia would serialize the **secret API key** into the page-props JSON (client-readable, cacheable). MUST stay Blade. |
| Generate API key | `merchant.generate.key` (POST) | `MerchantController@generateApiKey` | — | Regenerates public/secret keys (write + secret). Out of scope. |

**Rule:** never place `secret_api_key` (or any private/callback secret) into Inertia props.
If the API key page is ever migrated, the secret must be masked server-side and revealed only
through a dedicated, audited, on-demand endpoint — not via shared props.

## 🔴 Frozen — money movement
| Form / action | Route | Controller | Why frozen |
|---------------|-------|-----------|-----------|
| Withdraw entry | `merchant.withdraw` (GET `/withdraw`) | `MerchantWithdrawController@withdraw` | Money-out flow. |
| Withdraw money | `merchant.withdraw.money` (POST `/withdraw/money`) | `MerchantWithdrawController` | Initiates withdrawal (balance mutation). |
| Withdraw submit | `merchant.withdraw.submit` (POST `/withdraw/preview`) | `MerchantWithdrawController` | Confirms withdrawal. |
| Add withdraw method | `merchant.withdraw.method.add` (POST) | `MerchantWithdrawController` | Payout destination (sensitive). |
| Update withdraw method | `merchant.withdraw.update` (POST) | `MerchantWithdrawController` | Payout destination (sensitive). |

## 🔴 Frozen — security / account mutation (deferred to a later, dedicated phase)
| Form | Route | Controller | Notes |
|------|-------|-----------|-------|
| Profile update | `merchant.profile.setting` (POST) | `MerchantController@submitProfile` | Image upload + profile mutation; not part of this read-only sprint. |
| Change password | `merchant.change.password` (POST) | `MerchantController@submitPassword` | Credential mutation. |
| Enable/disable 2FA | `merchant.twofactor.enable` / `.disable` (POST) | `MerchantController@create2fa` / `disable2fa` | 2FA secret handling. |
| KYC submit | `merchant.kyc.submit` (POST) | `MerchantController@kycSubmit` | KYC upload/approval pipeline — frozen by sprint rule. |
| Merchant data (onboarding) | `merchant.data.submit` (POST) | `MerchantController@userDataSubmit` | Registration-step mutation. |

## 🟡 Caution / deferred — read-only but inside money controllers
| Page | Route | Controller | Notes |
|------|-------|-----------|-------|
| Withdraw history | `merchant.withdraw.history` (GET) | `MerchantWithdrawController@withdrawLog` | Read-only list, **but** lives in the withdraw money-flow controller. Left Blade this sprint to honor "do not touch withdraw flow". Safe future candidate (pattern identical to `PanelTransactions`). |
| Withdraw methods list | `merchant.withdraw.methods` (GET) | `MerchantWithdrawController@withdrawMethods` | Read-only list of payout methods; may expose method metadata. Defer. |
| KYC form (GET) | `merchant.kyc.form` | `MerchantController@kycForm` | Renders a dynamic form definition; submission is frozen, so leave the GET page Blade too for contract integrity. |
| KYC data (GET) | `merchant.kyc.data` | `MerchantController@kycData` | Read-only KYC values; deferred (mirrors user KYC info page, future candidate). |
| QR code (GET) | `merchant.qr` | `MerchantController@qrCode` | Read-only; future candidate (mirrors user QR page). |

## Out of scope (not a merchant-panel UI form)
- Payment creation/initiation and IPN/callbacks are served by the public/API gateway
  (`Gateway\*`, `routes/ipn.php`), **not** the merchant panel — untouched by the frontend sprint.
- `merchant.attachment.download`, `merchant.add.device.token` — non-UI endpoints, left as-is.

## Summary
No merchant secret, API key, withdrawal, KYC, or account-mutation form was migrated.
Only read-only display pages (dashboard, transactions, wallets) are on Inertia. The single
hard blocker for the API-key page is **secret serialization into props** — keep it Blade.
