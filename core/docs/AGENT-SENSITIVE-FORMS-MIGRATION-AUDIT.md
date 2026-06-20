# Agent Sensitive Forms — Migration Audit (F3e)

**Status:** docs-only. No code changed in this phase.
**Scope:** classify every agent-panel form/action that moves money, mutates balances,
or changes account/security state, and record the freeze decision for the non-money sprint.

Legend: 🟢 migrated (read-only, done) · 🟡 caution / deferred · 🔴 frozen (do not migrate this sprint).

## Already migrated (read-only — F3c/F3d)
| Page | Route | Component | Notes |
|------|-------|-----------|-------|
| Dashboard | `agent.home` (GET) | `Agent/Dashboard` | 🟢 read-only metrics/wallets/histories/KYC status |
| Transactions | `agent.transactions` (GET `/transaction/history`) | `Agent/Transactions` | 🟢 read-only paginator + filters |
| Wallets | `agent.wallets` (GET) | `Agent/Wallets` | 🟢 read-only balances |
| Commission log | `agent.commission.log` (GET `/commission-log`) | `Agent/CommissionLog` | 🟢 read-only paginated commission transactions |
| Add-money history | `agent.deposit.history` (GET `/add-money/history`) | `Agent/DepositHistory` | 🟢 read-only paginator (gated by `module:withdraw_money`) |

## 🔴 Frozen — money movement
| Form / action | Route | Controller | Why frozen |
|---------------|-------|-----------|-----------|
| Money-In user check | `agent.user.check.exist` (POST `/user/check`) | `MoneyInController@checkUser` | Step in money-in flow. |
| Money-In form | `agent.money.in` (GET) | `MoneyInController@moneyInForm` | Entry to balance-crediting flow. |
| Confirm Money-In | `agent.money.in` (POST) | `MoneyInController@confirmMoneyIn` | **Credits a user balance / moves money.** Hard-frozen. |
| Withdraw entry | `agent.withdraw` (GET `/withdraw`) | `AgentWithdrawController@withdraw` | Money-out flow. |
| Withdraw money | `agent.withdraw.money` (POST) | `AgentWithdrawController` | Initiates withdrawal (balance mutation). |
| Withdraw submit | `agent.withdraw.submit` (POST `/withdraw/preview`) | `AgentWithdrawController` | Confirms withdrawal / commission withdrawal. |
| Add/edit withdraw method | `agent.withdraw.method.add` / `.update` (POST) | `AgentWithdrawController` | Payout destination (sensitive). |
| Deposit (add money) | `agent.deposit*` (GET/POST) | `Gateway\PaymentController` | Payment gateway flow — untouched. |

> **Commission withdrawal** is performed through the same `AgentWithdrawController` withdraw
> pipeline (commission balance → withdraw). All of it is frozen.

## 🔴 Frozen — security / account mutation (deferred to a later, dedicated phase)
| Form | Route | Controller | Notes |
|------|-------|-----------|-------|
| Profile update | `agent.profile.setting` (POST) | `AgentController@submitProfile` | Image upload + profile mutation. |
| Change password | `agent.change.password` (POST) | `AgentController@submitPassword` | Credential mutation. |
| Enable/disable 2FA | `agent.twofactor.enable` / `.disable` (POST) | `AgentController@create2fa` / `disable2fa` | 2FA secret handling. |
| KYC submit | `agent.kyc.submit` (POST) | `AgentController@kycSubmit` | KYC upload/approval pipeline — frozen by sprint rule. |
| Agent data (onboarding) | `agent.data.submit` (POST) | `AgentController@userDataSubmit` | Registration-step mutation. |

## 🟡 Caution / deferred — read-only but inside money controllers or secret-adjacent
| Page | Route | Controller | Notes |
|------|-------|-----------|-------|
| Withdraw history | `agent.withdraw.history` (GET) | `AgentWithdrawController@withdrawLog` | Read-only list, but lives in the withdraw money-flow controller. Left Blade this sprint. Safe future candidate (identical to `PanelTransactions`). |
| Withdraw methods list | `agent.withdraw.methods` (GET) | `AgentWithdrawController@withdrawMethods` | Read-only payout-method list; defer. |
| Money-In done (GET) | `agent.money.in.done` | `MoneyInController@moneyInDone` | Read-only confirmation page in the money-in flow; defer (flow integrity). |
| KYC form / KYC data (GET) | `agent.kyc.form` / `agent.kyc.data` | `AgentController@kycForm` / `kycData` | GET pages tied to a frozen submit; defer. |
| QR code (GET) | `agent.qr` | `AgentController@qrCode` | Read-only; future candidate (mirrors user QR page). |

## Out of scope (not an agent-panel UI form)
- `agent.attachment.download`, `agent.add.device.token`, gateway deposit confirm/IPN endpoints.

## Summary
No agent money-in/out, withdrawal, commission-withdrawal, KYC, or account-mutation form was
migrated. Only read-only display pages (dashboard, transactions, wallets, commission log,
add-money history) are on Inertia. Withdraw history and withdraw-methods lists are the safest
future read-only candidates but were deliberately left Blade because they live in the
money-movement controller.
