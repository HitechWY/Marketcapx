# Inertia Migration — Completion Map (source of truth)

Status of every area after F0–F7. **Inertia** = migrated Vue page. **Blade** = retained (frozen or
deferred). Every migrated controller method kept its Blade view as a one-line rollback. Backend
money/KYC/gateway/IPN/checkout/escrow logic is unchanged throughout.

## Public (guest) — ✅ fully Inertia
Home, custom Pages, Policy pages, Cookie policy, Blogs list, Blog details, Contact.
(`SiteController@*` → `Public/*`.) POST flows (contact/subscribe/cookie-accept) unchanged.

## User (web guard)
| Inertia | Blade (frozen) |
|---------|----------------|
| Dashboard, Transactions, Wallets, Deposit History, Withdraw History, Profile, Change Password, 2FA (GET), KYC Info (GET), QR Code | Deposit, Withdraw, Transfer, Exchange, Money-Out, Make-Payment, Request-Money, Invoice, Voucher, Escrow forms; KYC submit; all POST mutations |

## Merchant
| Inertia | Blade (frozen) |
|---------|----------------|
| Dashboard, Transactions, Wallets | **API key page (renders secret)**, Withdraw flow + methods, Profile/Password/2FA/KYC forms |

## Agent
| Inertia | Blade (frozen) |
|---------|----------------|
| Dashboard, Transactions, Wallets, Commission Log, Add-Money History | Money-In, Withdraw + commission withdrawal, Deposit, Profile/Password/2FA/KYC forms |

## Auth
| Inertia | Blade (frozen) |
|---------|----------------|
| Forgot-password, Code-verify, Reset-password (user + merchant + agent), native POST | **All login + register (user/merchant/agent/admin)** and **admin login** — render `<x-captcha>`; deferred to avoid changing captcha logic |

## Admin
| Inertia (read-only) | Blade (frozen) |
|---------------------|----------------|
| Dashboard (+ charts via unchanged `admin.chart.*` JSON), Users/Agents/Merchants lists, Deposits list, Withdrawals list, Support Tickets list, Transaction report, Login history (+ by-IP) | All detail/KYC pages; ticket reply/close/delete; approve/reject (deposit/withdraw/KYC); status/balance mutations; gateway/payment-method/system/general/notification/cron/role/extension/plugin/frontend settings; notification-history + email-details (sensitive payloads); admin login |

## Frozen lists (do not migrate without a separate audited effort)
- **Money forms:** deposit, withdraw, transfer, exchange, money-in/out, make-payment, request-money, invoice, voucher, escrow, milestone — all guards. See `MERCHANT-SENSITIVE-FORMS-MIGRATION-AUDIT.md`, `AGENT-SENSITIVE-FORMS-MIGRATION-AUDIT.md`.
- **Admin sensitive actions:** see `ADMIN-SENSITIVE-ACTIONS-FREEZE-LIST.md` (approvals, settings, secrets, system/cron, roles, deletes).
- **KYC / upload / approval:** KYC submit (all guards) + admin KYC approve/reject + KYC document viewers.
- **Gateway / IPN / webhook / checkout / escrow:** untouched end-to-end (`Gateway\*`, `routes/ipn.php`, checkout templates, `work/escrow-atomicity`).

## Blade fallbacks retained (rollback)
Every migrated GET method keeps a one-line Blade-view rollback comment; the corresponding Blade
views are all still present (verified by `SensitiveRoutesRemainBladeTest` + per-phase scans). No
Blade view was deleted in any phase.

## Client route exposure
Ziggy serializes only the `public` group (`config/ziggy.php`); all in-app links are server-resolved
`route()` props. No panel/money/gateway/auth-mutation route names reach the client
(`ZiggyExposureTest`). Route count steady at **798** across F0–F7.

## Security posture
`InertiaPropExposureTest` + `INERTIA-PROPS-SECURITY-AUDIT.md`: no password/remember_token/2FA-secret/
api-secret/gateway-credential/raw-KYC-path/cross-actor data in any prop.

## Remaining high-risk phases & recommended order
> **F8 (safety foundation, docs+tests only — no behavior change) is complete.** It audited and
> designed the prerequisites for phase 1 below: `RBAC-PERMISSION-AUDIT.md` (F8a, admin is flat),
> `ADMIN-MUTATION-CONTRACT-AUDIT.md` (F8b), `ADMIN-AUDIT-LOG-READINESS.md` (F8c, metadata-only),
> and `ADMIN-SAFE-MUTATION-ENDPOINT-DESIGN.md` (F8d, target contract + implementation order).
>
> **F9 (RBAC enforcement foundation) is complete — allow-by-default, no behavior change.** Schema
> + models + `PermissionCatalog` + `rbac:install` seeder + `permission:<slug>` middleware applied to
> high-risk admin routes, with `config('rbac.enforce')=false` (legacy admins keep full access). See
> `RBAC-SCHEMA.md`, `RBAC-SEEDING.md`, and `RBAC-DENY-BY-DEFAULT-READINESS.md`. Still pending:
> enabling deny-by-default (owner-gated), the DB audit-log foundation, then mutation-UI migration.

1. **RBAC + admin mutation endpoints** — role/permission enforcement scaffolding **landed in F9**
   (allow-by-default). Next: complete the deny-by-default readiness checklist (owner-gated), build the
   audit-log foundation, extract admin inline mutations into dedicated audited endpoints, THEN migrate
   admin detail/approval pages to Inertia. **See the F8d design + order and the F9 RBAC docs.**
2. **Sanitized notification/email viewer** — safe read-only viewer before migrating notification
   history / email details.
3. **In-SPA captcha strategy** — verify reCAPTCHA/custom-captcha rendering in Vue, then migrate
   login + register (all guards) and admin login.
4. **Money forms (highest risk)** — only after full money-flow regression coverage; may remain Blade
   indefinitely by policy. Gateway/IPN/webhook/checkout/escrow stay frozen.
